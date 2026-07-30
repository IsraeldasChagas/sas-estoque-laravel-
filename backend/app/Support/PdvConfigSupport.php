<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PdvConfigSupport
{
    /** @return array{exigir_nsu_cartao: bool, exigir_autorizacao_cartao: bool, exigir_bandeira_cartao: bool, exigir_identificador_pix: bool, pode_editar: bool} */
    public static function opcoesPublicas(?object $usuario = null): array
    {
        $cfg = self::carregar();

        return array_merge($cfg, [
            'pode_editar' => self::usuarioPodeEditar($usuario),
        ]);
    }

    /** @return array{exigir_nsu_cartao: bool, exigir_autorizacao_cartao: bool, exigir_bandeira_cartao: bool, exigir_identificador_pix: bool} */
    public static function carregar(): array
    {
        if (! Schema::hasTable('pdv_configuracoes')) {
            return self::defaults();
        }

        $row = DB::table('pdv_configuracoes')->orderBy('id')->first();
        if (! $row) {
            return self::defaults();
        }

        return [
            'exigir_nsu_cartao' => (bool) ($row->exigir_nsu_cartao ?? false),
            'exigir_autorizacao_cartao' => (bool) ($row->exigir_autorizacao_cartao ?? false),
            'exigir_bandeira_cartao' => (bool) ($row->exigir_bandeira_cartao ?? false),
            'exigir_identificador_pix' => (bool) ($row->exigir_identificador_pix ?? false),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function salvar(array $data, ?int $usuarioId = null): array
    {
        if (! Schema::hasTable('pdv_configuracoes')) {
            throw new \RuntimeException('Configuração PDV indisponível (migração pendente).');
        }

        $payload = [
            'exigir_nsu_cartao' => ! empty($data['exigir_nsu_cartao']),
            'exigir_autorizacao_cartao' => ! empty($data['exigir_autorizacao_cartao']),
            'exigir_bandeira_cartao' => ! empty($data['exigir_bandeira_cartao']),
            'exigir_identificador_pix' => ! empty($data['exigir_identificador_pix']),
            'updated_at' => now(),
        ];
        if ($usuarioId > 0 && Schema::hasColumn('pdv_configuracoes', 'updated_by')) {
            $payload['updated_by'] = $usuarioId;
        }

        $row = DB::table('pdv_configuracoes')->orderBy('id')->first();
        if ($row) {
            DB::table('pdv_configuracoes')->where('id', $row->id)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('pdv_configuracoes')->insert($payload);
        }

        return self::carregar();
    }

    public static function isFormaCartao(string $forma): bool
    {
        $f = mb_strtolower(trim($forma));

        return str_contains($f, 'crédito') || str_contains($f, 'credito')
            || str_contains($f, 'débito') || str_contains($f, 'debito')
            || str_contains($f, 'cartão') || str_contains($f, 'cartao');
    }

    public static function isFormaPix(string $forma): bool
    {
        return mb_strtolower(trim($forma)) === 'pix';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function validarDadosPagamento(string $forma, array $payload, ?array $config = null): ?string
    {
        $cfg = $config ?? self::carregar();
        $forma = trim($forma);

        if (self::isFormaCartao($forma)) {
            if (! empty($cfg['exigir_nsu_cartao']) && trim((string) ($payload['pagamento_nsu'] ?? '')) === '') {
                return 'Informe o NSU do cartão (exigido pela configuração do PDV).';
            }
            if (! empty($cfg['exigir_autorizacao_cartao']) && trim((string) ($payload['pagamento_autorizacao'] ?? '')) === '') {
                return 'Informe o código de autorização do cartão (exigido pela configuração do PDV).';
            }
            if (! empty($cfg['exigir_bandeira_cartao']) && trim((string) ($payload['pagamento_bandeira'] ?? '')) === '') {
                return 'Informe a bandeira do cartão (exigida pela configuração do PDV).';
            }
        }

        if (self::isFormaPix($forma) && ! empty($cfg['exigir_identificador_pix'])
            && trim((string) ($payload['pagamento_pix_id'] ?? '')) === '') {
            return 'Informe o identificador da transação PIX (exigido pela configuração do PDV).';
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    public static function extrairCamposPagamentoVenda(array $payload): array
    {
        $out = [];
        if (Schema::hasColumn('vendas', 'pagamento_nsu') && isset($payload['pagamento_nsu'])) {
            $v = trim((string) $payload['pagamento_nsu']);
            $out['pagamento_nsu'] = $v !== '' ? mb_substr($v, 0, 32) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_autorizacao') && isset($payload['pagamento_autorizacao'])) {
            $v = trim((string) $payload['pagamento_autorizacao']);
            $out['pagamento_autorizacao'] = $v !== '' ? mb_substr($v, 0, 32) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_bandeira') && isset($payload['pagamento_bandeira'])) {
            $v = trim((string) $payload['pagamento_bandeira']);
            $out['pagamento_bandeira'] = $v !== '' ? mb_substr($v, 0, 40) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_parcelas') && isset($payload['pagamento_parcelas'])) {
            $p = (int) $payload['pagamento_parcelas'];
            $out['pagamento_parcelas'] = $p > 0 ? min(99, $p) : null;
        }
        if (Schema::hasColumn('vendas', 'pagamento_pix_id') && isset($payload['pagamento_pix_id'])) {
            $v = trim((string) $payload['pagamento_pix_id']);
            $out['pagamento_pix_id'] = $v !== '' ? mb_substr($v, 0, 120) : null;
        }

        return $out;
    }

    public static function usuarioPodeEditar(?object $usuario): bool
    {
        if (! $usuario) {
            return false;
        }
        $p = strtoupper(trim((string) ($usuario->perfil ?? '')));

        return in_array($p, ['ADMIN', 'ADMINISTRADOR', 'GERENTE'], true);
    }

    /** @return array{exigir_nsu_cartao: bool, exigir_autorizacao_cartao: bool, exigir_bandeira_cartao: bool, exigir_identificador_pix: bool} */
    private static function defaults(): array
    {
        return [
            'exigir_nsu_cartao' => false,
            'exigir_autorizacao_cartao' => false,
            'exigir_bandeira_cartao' => false,
            'exigir_identificador_pix' => false,
        ];
    }
}
