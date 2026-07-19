<?php

namespace App\Support\Delivery;

final class DeliveryWhatsAppAvisoStatus
{
    public static function url(object $pedido, object $config, string $statusNovo): ?string
    {
        $canal = strtolower(trim((string) ($pedido->canal ?? 'loja')));
        if ($canal !== 'loja') {
            return null;
        }

        $digits = DeliveryWhatsAppHelper::normalizarTelefoneBr($pedido->cliente_telefone ?? null);
        if ($digits === null) {
            return null;
        }

        $slug = trim((string) ($config->slug ?? ''));
        $codigo = trim((string) ($pedido->codigo_publico ?? ''));
        $token = trim((string) ($pedido->cliente_token ?? ''));
        if ($slug === '' || $codigo === '' || $token === '') {
            return null;
        }

        $rotulo = DeliveryPedidoPresenter::rotuloStatus($statusNovo);
        $linkPedido = route('delivery.public.order', [$slug, $codigo, $token], absolute: true);
        $nomeCliente = str_replace('*', '', trim((string) ($pedido->cliente_nome ?? '')));
        $nomeLoja = str_replace('*', '', trim((string) ($config->nome_loja ?? 'Loja')));
        $codigoEsc = str_replace('*', '', $codigo);

        $iconLoja = "\u{1F3EA}";
        $iconCliente = "\u{1F464}";
        $iconSacola = "\u{1F6CD}\u{FE0F}";
        $iconStatus = match (strtolower(trim($statusNovo))) {
            'preparo' => "\u{1F373}\u{2009}\u{1F944}",
            'endereco_nao_encontrado' => "\u{1F4CD}\u{2009}\u{274C}",
            default => "\u{2796}",
        };
        $iconLupa = "\u{1F50D}";

        $msg = $iconLoja.' *'.$nomeLoja."*\n\n";
        $msg .= $iconCliente.' ';
        $msg .= $nomeCliente !== '' ? '*'.$nomeCliente.'*' : 'Cliente';
        $msg .= "\n\n";
        $msg .= $iconSacola.' Código: *'.$codigoEsc."*\n\n";
        $msg .= $iconStatus.' '.$rotulo."\n\n";
        $msg .= $iconLupa." Acompanhar seu pedido\n".$linkPedido;

        return DeliveryWhatsAppHelper::urlComTexto($pedido->cliente_telefone, $msg);
    }
}
