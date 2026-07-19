<?php

namespace App\Contracts\Payments;

interface PaymentPixProviderInterface
{
    public function codigo(): string;

    public function rotulo(): string;

    /**
     * @param  array<string, mixed>  $credenciais
     * @return array{ok:bool,externo_id?:string,payload?:string,expira_em?:?\DateTimeInterface,status?:string,mensagem?:string,raw?:mixed}
     */
    public function criarCobranca(object $pedido, object $config, array $credenciais): array;

    /**
     * @param  array<string, mixed>  $credenciais
     * @return array{ok:bool,status?:string,pago?:bool,externo_id?:string,mensagem?:string,raw?:mixed}
     */
    public function consultarCobranca(object $pedido, array $credenciais): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,externo_id?:string,pago?:bool,status?:string,mensagem?:string}
     */
    public function interpretarWebhook(array $payload, array $credenciais, ?string $signature): array;
}
