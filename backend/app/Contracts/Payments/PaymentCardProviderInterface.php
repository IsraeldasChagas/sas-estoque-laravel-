<?php

namespace App\Contracts\Payments;

interface PaymentCardProviderInterface
{
    public function codigo(): string;

    public function rotulo(): string;

    /**
     * @param  array<string, mixed>  $credenciais
     * @param  array<string, mixed>  $urls
     * @return array{ok:bool,externo_id?:string,checkout_url?:string,status?:string,mensagem?:string,raw?:mixed}
     */
    public function criarCheckout(object $pedido, object $config, array $credenciais, array $urls): array;

    /**
     * @param  array<string, mixed>  $credenciais
     * @return array{ok:bool,status?:string,pago?:bool,externo_id?:string,mensagem?:string,raw?:mixed}
     */
    public function consultarPagamento(object $pedido, array $credenciais): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,externo_id?:string,pago?:bool,status?:string,mensagem?:string,referencia?:string}
     */
    public function interpretarWebhook(array $payload, array $credenciais, ?string $signature): array;
}
