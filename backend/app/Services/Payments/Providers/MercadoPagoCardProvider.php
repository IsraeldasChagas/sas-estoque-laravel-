<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentCardProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MercadoPagoCardProvider implements PaymentCardProviderInterface
{
    public function codigo(): string
    {
        return 'mercado_pago';
    }

    public function rotulo(): string
    {
        return 'Mercado Pago';
    }

    public function criarCheckout(object $pedido, object $config, array $credenciais, array $urls): array
    {
        $token = trim((string) ($credenciais['token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'mensagem' => 'Token do Mercado Pago não configurado.'];
        }

        $email = trim((string) ($pedido->cliente_email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'cliente+'.Str::slug((string) ($pedido->codigo_publico ?? $pedido->id), '').'@saborparaense.local';
        }

        $body = [
            'items' => [[
                'title' => 'Pedido '.($pedido->codigo_publico ?? $pedido->id),
                'quantity' => 1,
                'unit_price' => round((float) $pedido->total, 2),
                'currency_id' => 'BRL',
            ]],
            'payer' => [
                'email' => $email,
                'name' => Str::limit((string) ($pedido->cliente_nome ?? 'Cliente'), 80, ''),
            ],
            'external_reference' => (string) ($pedido->codigo_publico ?? $pedido->id),
            'notification_url' => url('/api/integracoes/webhooks/mercado_pago'),
            'back_urls' => [
                'success' => (string) ($urls['success'] ?? ''),
                'failure' => (string) ($urls['failure'] ?? ''),
                'pending' => (string) ($urls['pending'] ?? ''),
            ],
            'auto_return' => 'approved',
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(25)
            ->post('https://api.mercadopago.com/checkout/preferences', $body);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'mensagem' => $this->extrairErro($response->json(), $response->body()),
                'raw' => $response->json(),
            ];
        }

        $data = $response->json();
        $sandbox = (bool) ($credenciais['sandbox'] ?? true);
        $checkoutUrl = $sandbox
            ? (string) ($data['sandbox_init_point'] ?? '')
            : (string) ($data['init_point'] ?? $data['sandbox_init_point'] ?? '');

        if ($checkoutUrl === '') {
            return ['ok' => false, 'mensagem' => 'Mercado Pago não retornou link de pagamento.'];
        }

        return [
            'ok' => true,
            'externo_id' => (string) ($data['id'] ?? ''),
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
            'raw' => $data,
        ];
    }

    public function consultarPagamento(object $pedido, array $credenciais): array
    {
        $token = trim((string) ($credenciais['token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'mensagem' => 'Gateway não configurado.'];
        }

        $referencia = (string) ($pedido->codigo_publico ?? $pedido->id);
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.mercadopago.com/v1/payments/search', [
                'external_reference' => $referencia,
                'sort' => 'date_created',
                'criteria' => 'desc',
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'mensagem' => $this->extrairErro($response->json(), $response->body())];
        }

        $results = data_get($response->json(), 'results', []);
        $payment = is_array($results) ? ($results[0] ?? null) : null;
        if (! is_array($payment)) {
            return ['ok' => true, 'status' => 'pending', 'pago' => false];
        }

        $status = strtolower((string) ($payment['status'] ?? ''));

        return [
            'ok' => true,
            'status' => $status,
            'pago' => in_array($status, ['approved', 'accredited'], true),
            'externo_id' => (string) ($payment['id'] ?? ''),
            'raw' => $payment,
        ];
    }

    public function interpretarWebhook(array $payload, array $credenciais, ?string $signature): array
    {
        return (new MercadoPagoPixProvider)->interpretarWebhook($payload, $credenciais, $signature);
    }

    /** @param  mixed  $json */
    private function extrairErro(mixed $json, string $body): string
    {
        if (is_array($json)) {
            $msg = data_get($json, 'message') ?? data_get($json, 'error');
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return Str::limit(trim($body) ?: 'Erro na API do Mercado Pago.', 200);
    }
}
