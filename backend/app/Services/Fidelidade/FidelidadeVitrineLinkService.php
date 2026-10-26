<?php

namespace App\Services\Fidelidade;

use App\Models\ReservaMesa;
use Illuminate\Http\Request;

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

        $url = $base.'/loja/'.$loja->slug.'/fidelidade?origem=reserva';
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

    /** A página pública de fidelidade ficava na loja do Delivery, que foi removida. */
    private function resolverLoja(int $unidadeId): ?object
    {
        return null;
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
