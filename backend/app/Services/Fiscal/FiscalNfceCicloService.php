<?php

namespace App\Services\Fiscal;

use App\Models\FiscalEmissaoConfig;
use App\Support\FiscalCadastroSupport;
use App\Support\FiscalEmissaoConfigSupport;
use App\Support\VendaFiscalSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cancelamento, inutilização e transmissão de NFC-e em contingência (Focus). */
final class FiscalNfceCicloService
{
    public const CONSULTA_SVRS_URL = 'https://dfe-portal.svrs.rs.gov.br/NFCe/Consulta';

    /**
     * @return array<string, mixed>
     */
    public static function cancelar(int $vendaId, string $justificativa): array
    {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 15 || mb_strlen($justificativa) > 255) {
            return [
                'ok' => false,
                'mensagem' => 'Justificativa do cancelamento deve ter entre 15 e 255 caracteres.',
            ];
        }

        $ctx = self::contextoVenda($vendaId);
        if (isset($ctx['error'])) {
            return ['ok' => false, 'mensagem' => $ctx['error']];
        }
        $venda = $ctx['venda'];
        $st = strtolower(trim((string) ($venda->status_documento ?? '')));
        if (in_array($st, ['cancelado', 'cancelada'], true)) {
            return ['ok' => true, 'skipped' => true, 'status' => 'cancelado', 'mensagem' => 'NFC-e já cancelada.'];
        }
        if (! in_array($st, ['autorizado', 'autorizada'], true) && empty($venda->chave_acesso)) {
            return ['ok' => false, 'mensagem' => 'Só é possível cancelar NFC-e autorizada.'];
        }
        $ref = trim((string) ($venda->emissao_ref ?? ''));
        if ($ref === '') {
            return ['ok' => false, 'mensagem' => 'Venda sem referência Focus para cancelar.'];
        }

        $out = $ctx['emitter']->cancelarNfce($ref, $justificativa);
        self::registrarLog((int) $venda->id, (int) $ctx['empresa_id'], $ref, $out);

        if (! ($out['success'] ?? false)) {
            return [
                'ok' => false,
                'status' => $out['status'] ?? 'erro',
                'mensagem' => (string) ($out['error'] ?? 'Falha ao cancelar na Focus/SEFAZ.'),
            ];
        }

        DB::table('vendas')->where('id', $vendaId)->update([
            'status_documento' => 'cancelado',
            'emissao_mensagem' => mb_substr('Cancelada: '.$justificativa, 0, 2000),
            'updated_at' => now(),
        ]);

        return [
            'ok' => true,
            'status' => 'cancelado',
            'venda_id' => $vendaId,
            'mensagem' => $out['mensagem'] ?? 'NFC-e cancelada.',
        ];
    }

    /**
     * Consulta a Focus e promove contingência para autorizado quando a SEFAZ efetivar.
     *
     * @return array<string, mixed>
     */
    public static function transmitirContingencia(int $vendaId): array
    {
        $ctx = self::contextoVenda($vendaId);
        if (isset($ctx['error'])) {
            return ['ok' => false, 'emitida' => false, 'mensagem' => $ctx['error']];
        }
        $venda = $ctx['venda'];
        $st = strtolower(trim((string) ($venda->status_documento ?? '')));
        if (in_array($st, ['autorizado', 'autorizada'], true)) {
            return [
                'ok' => true,
                'emitida' => true,
                'skipped' => true,
                'status' => 'autorizado',
                'chave' => $venda->chave_acesso ?? null,
                'mensagem' => 'NFC-e já autorizada.',
                'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
            ];
        }
        if ($st !== 'contingencia' && empty($venda->emissao_ref)) {
            return ['ok' => false, 'emitida' => false, 'mensagem' => 'Esta venda não está em contingência.'];
        }
        $ref = trim((string) ($venda->emissao_ref ?? ''));
        if ($ref === '') {
            return ['ok' => false, 'emitida' => false, 'mensagem' => 'Venda sem referência Focus para transmitir.'];
        }

        $out = $ctx['emitter']->consultarNfce($ref);
        self::registrarLog((int) $venda->id, (int) $ctx['empresa_id'], $ref, $out);

        $efetivada = ! empty($out['contingencia_offline_efetivada']);
        $autorizado = ($out['success'] ?? false) && in_array(strtolower((string) ($out['status'] ?? '')), ['autorizado', 'autorizada'], true);

        if ($autorizado || $efetivada) {
            FiscalEmissaoService::persistirDocumento($vendaId, $ctx['config'], $ref, array_merge($out, [
                'status' => 'autorizado',
            ]));

            return [
                'ok' => true,
                'emitida' => true,
                'status' => 'autorizado',
                'chave' => $out['chave'] ?? $venda->chave_acesso ?? null,
                'numero' => $out['numero'] ?? $venda->numero_documento ?? null,
                'serie' => $out['serie'] ?? $venda->serie_documento ?? null,
                'venda_id' => $vendaId,
                'documentos' => FiscalDocumentoService::rotasRelativas($vendaId),
                'mensagem' => 'NFC-e transmitida e autorizada pela SEFAZ.',
            ];
        }

        if ($out['success'] ?? false) {
            return [
                'ok' => true,
                'emitida' => true,
                'status' => 'contingencia',
                'chave' => $out['chave'] ?? $venda->chave_acesso ?? null,
                'venda_id' => $vendaId,
                'mensagem' => 'Ainda em contingência. A Focus reenvia à SEFAZ automaticamente — tente de novo em alguns minutos.',
            ];
        }

        return [
            'ok' => false,
            'emitida' => false,
            'status' => $out['status'] ?? 'erro',
            'mensagem' => (string) ($out['error'] ?? 'SEFAZ ainda não autorizou a nota em contingência.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inutilizar(int $empresaId, string $serie, string $numeroInicial, string $numeroFinal, string $justificativa): array
    {
        $justificativa = trim($justificativa);
        if (mb_strlen($justificativa) < 15 || mb_strlen($justificativa) > 255) {
            return ['ok' => false, 'mensagem' => 'Justificativa deve ter entre 15 e 255 caracteres.'];
        }
        $serie = preg_replace('/\D+/', '', $serie) ?? '';
        $numeroInicial = preg_replace('/\D+/', '', $numeroInicial) ?? '';
        $numeroFinal = preg_replace('/\D+/', '', $numeroFinal) ?? '';
        if ($serie === '' || $numeroInicial === '' || $numeroFinal === '') {
            return ['ok' => false, 'mensagem' => 'Informe série, número inicial e número final.'];
        }
        if ((int) $numeroFinal < (int) $numeroInicial) {
            return ['ok' => false, 'mensagem' => 'Número final deve ser maior ou igual ao inicial.'];
        }

        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        if (! $empresa) {
            return ['ok' => false, 'mensagem' => 'Empresa não encontrada.'];
        }
        $cnpj = FiscalCadastroSupport::normalizarCnpj($empresa->cnpj ?? null);
        if (! $cnpj) {
            return ['ok' => false, 'mensagem' => 'CNPJ da empresa inválido.'];
        }
        $config = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
        if (! $config || empty($config->api_token)) {
            return ['ok' => false, 'mensagem' => 'Configure o token Focus desta empresa.'];
        }

        $emitter = self::emitterDoConfig($config);
        $payload = [
            'cnpj' => $cnpj,
            'serie' => (string) (int) $serie,
            'numero_inicial' => (string) (int) $numeroInicial,
            'numero_final' => (string) (int) $numeroFinal,
            'justificativa' => $justificativa,
        ];
        $out = $emitter->inutilizarNfce($payload);
        self::registrarLog(null, $empresaId, 'inut-'.$serie.'-'.$numeroInicial.'-'.$numeroFinal, $out);

        if (! ($out['success'] ?? false)) {
            return [
                'ok' => false,
                'status' => $out['status'] ?? 'erro',
                'mensagem' => (string) ($out['error'] ?? 'Falha na inutilização.'),
            ];
        }

        return [
            'ok' => true,
            'status' => $out['status'] ?? 'inutilizado',
            'mensagem' => $out['mensagem'] ?? 'Numeração inutilizada na SEFAZ.',
        ];
    }

    /**
     * @return array{processadas: int, autorizadas: int, pendentes: int, erros: int}
     */
    public static function transmitirPendentes(int $limite = 50): array
    {
        $stats = ['processadas' => 0, 'autorizadas' => 0, 'pendentes' => 0, 'erros' => 0];
        if (! Schema::hasTable('vendas')) {
            return $stats;
        }
        $ids = DB::table('vendas')
            ->where('status_documento', 'contingencia')
            ->orderBy('id')
            ->limit(max(1, $limite))
            ->pluck('id');
        foreach ($ids as $id) {
            $stats['processadas']++;
            $out = self::transmitirContingencia((int) $id);
            if (($out['status'] ?? '') === 'autorizado') {
                $stats['autorizadas']++;
            } elseif ($out['ok'] ?? false) {
                $stats['pendentes']++;
            } else {
                $stats['erros']++;
            }
        }

        return $stats;
    }

    /**
     * @return array{venda: object, empresa_id: int, config: FiscalEmissaoConfig, emitter: FocusNfeDocumentEmitter}|array{error: string}
     */
    private static function contextoVenda(int $vendaId): array
    {
        if (! Schema::hasTable('vendas')) {
            return ['error' => 'Módulo de vendas não migrado.'];
        }
        $venda = DB::table('vendas')->where('id', $vendaId)->first();
        if (! $venda) {
            return ['error' => 'Venda não encontrada.'];
        }
        $empresaId = (int) ($venda->empresa_id ?? 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (VendaFiscalSupport::resolverEmpresaUnidade((int) ($venda->unidade_id ?? 0)) ?? 0);
        }
        if ($empresaId <= 0) {
            return ['error' => 'Venda sem empresa vinculada.'];
        }
        $config = FiscalEmissaoConfig::query()->where('empresa_id', $empresaId)->first();
        if (! $config || empty($config->api_token)) {
            return ['error' => 'Token Focus não configurado.'];
        }

        return [
            'venda' => $venda,
            'empresa_id' => $empresaId,
            'config' => $config,
            'emitter' => self::emitterDoConfig($config),
        ];
    }

    private static function emitterDoConfig(FiscalEmissaoConfig $config): FocusNfeDocumentEmitter
    {
        $baseUrl = $config->api_url ?: FiscalEmissaoConfigSupport::focusBaseUrl((string) $config->environment);

        return new FocusNfeDocumentEmitter(new FocusNfeClient($baseUrl, (string) $config->api_token));
    }

    /** @param array<string, mixed> $out */
    private static function registrarLog(?int $vendaId, int $empresaId, string $ref, array $out): void
    {
        if (! Schema::hasTable('fiscal_emissao_logs')) {
            return;
        }
        DB::table('fiscal_emissao_logs')->insert([
            'venda_id' => $vendaId,
            'empresa_id' => $empresaId,
            'provider' => 'focus_nfe',
            'ref' => mb_substr($ref, 0, 80),
            'status' => $out['status'] ?? ($out['success'] ?? false ? 'ok' : 'erro'),
            'mensagem' => mb_substr((string) ($out['error'] ?? $out['mensagem'] ?? ''), 0, 65000),
            'resposta_json' => json_encode($out),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
