<?php

namespace App\Services\Fidelidade;

use App\Models\ReservaMesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gera link público da vitrine fidelidade para o cliente consultar selos.
 */
final class FidelidadeVitrineLinkService
{
    /**
     * @return array{url:?string,slug:?string,nome_loja:?string,whatsapp_url:?string,mensagem_whatsapp:?string}
     */
    public function paraReserva(ReservaMesa $reserva, ?Request $request = null): array
    {
        $base = $this->baseUrl($request);
        $loja = $this->resolverLoja((int) $reserva->unidade_id);
        if ($loja === null) {
            return $this->vazio();
        }

        $url = $base.'/loja/'.$loja->slug.'/fidelidade';
        $nomeCliente = trim((string) ($reserva->fidelidade_nome ?: $reserva->nome_cliente ?: ''));
        $saudacao = $nomeCliente !== '' ? 'Olá, '.$nomeCliente.'!' : 'Olá!';
        $nomeLoja = trim((string) ($loja->nome_loja ?: 'Sabor Paraense'));
        $mensagem = $saudacao."\n\nSeu cartão fidelidade ".$nomeLoja." está ativo.\nConsulte seus selos neste link:\n".$url;

        return [
            'url' => $url,
            'slug' => (string) $loja->slug,
            'nome_loja' => $nomeLoja,
            'whatsapp_url' => $this->whatsappUrl((string) $reserva->telefone_cliente, $mensagem),
            'mensagem_whatsapp' => $mensagem,
        ];
    }

    /**
     * @return array{url:?string,slug:?string,nome_loja:?string}
     */
    public function resolverPorUnidade(int $unidadeId, ?string $baseUrl = null): array
    {
        $loja = $this->resolverLoja($unidadeId);
        if ($loja === null) {
            return ['url' => null, 'slug' => null, 'nome_loja' => null];
        }

        $base = rtrim($baseUrl ?: (string) config('app.url'), '/');

        return [
            'url' => $base.'/loja/'.$loja->slug.'/fidelidade',
            'slug' => (string) $loja->slug,
            'nome_loja' => trim((string) ($loja->nome_loja ?: 'Loja')),
        ];
    }

    public function whatsappUrl(?string $telefone, string $mensagem): ?string
    {
        $tel = FidelidadeNormalizer::telefone($telefone);
        if (strlen($tel) < 10) {
            return null;
        }
        if (! str_starts_with($tel, '55') && strlen($tel) <= 11) {
            $tel = '55'.$tel;
        }

        return 'https://wa.me/'.$tel.'?text='.rawurlencode($mensagem);
    }

    private function baseUrl(?Request $request): string
    {
        if ($request !== null) {
            return rtrim($request->getSchemeAndHttpHost(), '/');
        }

        return rtrim((string) config('app.url'), '/');
    }

    private function resolverLoja(int $unidadeId): ?object
    {
        if ($unidadeId <= 0 || ! Schema::hasTable('dlv_loja_config')) {
            return null;
        }

        $query = DB::table('dlv_loja_config')->where('ativo', 1);

        if (Schema::hasColumn('dlv_loja_config', 'unidade_fidelidade_id')) {
            $loja = (clone $query)->where('unidade_fidelidade_id', $unidadeId)->orderBy('id')->first();
            if ($loja && ! empty($loja->slug)) {
                return $loja;
            }
        }

        $loja = DB::table('dlv_loja_config')
            ->where('ativo', 1)
            ->where('unidade_id', $unidadeId)
            ->orderBy('id')
            ->first();

        return ($loja && ! empty($loja->slug)) ? $loja : null;
    }

    /** @return array{url:?string,slug:?string,nome_loja:?string,whatsapp_url:?string,mensagem_whatsapp:?string} */
    private function vazio(): array
    {
        return [
            'url' => null,
            'slug' => null,
            'nome_loja' => null,
            'whatsapp_url' => null,
            'mensagem_whatsapp' => null,
        ];
    }
}
