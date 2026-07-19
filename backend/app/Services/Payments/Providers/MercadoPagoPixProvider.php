<?php

namespace App\Services\Payments\Providers;

use App\Contracts\Payments\PaymentPixProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MercadoPagoPixProvider implements PaymentPixProviderInterface
{
    public function codigo(): string
    {
        return 'mercado_pago';
    }

    public function rotulo(): string
    {
        return 'Mercado Pago';
    }

    public function criarCobranca(object $pedido, object $config, array $credenciais): array
    {
        $token = trim((string) ($credenciais['token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'mensagem' => 'Token do Mercado Pago não configurado.'];
        }

        $email = trim((string) ($pedido->cliente_email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'cliente+'.Str::slug((string) ($pedido->codigo_publico ?? $pedido->id), '').'@saborparaense.local';
        }

        $minutos = max(5, (int) ($config->pix_expiracao_minutos ?? 30));
        $expira = now()->addMinutes($minutos);

        $body = [
            'transaction_amount' => round((float) $pedido->total, 2),
            'description' => 'Pedido '.($pedido->codigo_publico ?? $pedido->id),
            'payment_method_id' => 'pix',
            'external_reference' => (string) ($pedido->codigo_publico ?? $pedido->id),
            'date_of_expiration' => $expira->format('Y-m-d\TH:i:s.000P'),
            'payer' => [
                'email' => $email,
                'first_name' => Str::limit((string) ($pedido->cliente_nome ?? 'Cliente'), 80, ''),
            ],
        ];

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(25)
            ->post('https://api.mercadopago.com/v1/payments', $body);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'mensagem' => $this->extrairErro($response->json(), $response->body()),
                'raw' => $response->json(),
            ];
        }

        $data = $response->json();
        $payload = data_get($data, 'point_of_interaction.transaction_data.qr_code')
            ?: data_get($data, 'point_of_interaction.transaction_data.qr_code_base64');

        if (! is_string($payload) || trim($payload) === '') {
            return [
                'ok' => false,
                'mensagem' => 'Mercado Pago não retornou QR/copia e cola PIX.',
                'raw' => $data,
            ];
        }

        return [
            'ok' => true,
            'externo_id' => (string) ($data['id'] ?? ''),
            'payload' => trim($payload),
            'expira_em' => $expira,
            'status' => (string) ($data['status'] ?? 'pending'),
            'raw' => $data,
        ];
    }

    public function consultarCobranca(object $pedido, array $credenciais): array
    {
        $token = trim((string) ($credenciais['token'] ?? ''));
        $externoId = trim((string) ($pedido->pagamento_externo_id ?? ''));
        if ($token === '' || $externoId === '') {
            return ['ok' => false, 'mensagem' => 'Cobrança externa não encontrada.'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.mercadopago.com/v1/payments/'.$externoId);

        if (! $response->successful()) {
            return ['ok' => false, 'mensagem' => $this->extrairErro($response->json(), $response->body())];
        }

        $data = $response->json();
        $status = strtolower((string) ($data['status'] ?? ''));

        return [
            'ok' => true,
            'status' => $status,
            'pago' => in_array($status, ['approved', 'accredited'], true),
            'externo_id' => (string) ($data['id'] ?? $externoId),
            'raw' => $data,
        ];
    }

    public function interpretarWebhook(array $payload, array $credenciais, ?string $signature): array
    {
        $tipo = strtolower((string) ($payload['type'] ?? $payload['action'] ?? ''));
        $paymentId = (string) (data_get($payload, 'data.id') ?? $payload['id'] ?? '');

        if ($paymentId === '') {
            return ['ok' => false, 'mensagem' => 'Webhook sem ID de pagamento.'];
        }

        if (! in_array($tipo, ['payment', 'payment.updated', 'payment_created'], true)
            && ! str_contains($tipo, 'payment')) {
            return ['ok' => false, 'mensagem' => 'Evento ignorado.', 'status' => 'ignored'];
        }

        $token = trim((string) ($credenciais['token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'mensagem' => 'Gateway não configurado.'];
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.mercadopago.com/v1/payments/'.$paymentId);

        if (! $response->successful()) {
            return ['ok' => false, 'mensagem' => 'Não foi possível consultar pagamento '.$paymentId];
        }

        $data = $response->json();
        $status = strtolower((string) ($data['status'] ?? ''));

        return [
            'ok' => true,
            'externo_id' => (string) ($data['id'] ?? $paymentId),
            'pago' => in_array($status, ['approved', 'accredited'], true),
            'status' => $status,
            'referencia' => (string) ($data['external_reference'] ?? ''),
        ];
    }

    /** @param  mixed  $json */
    private function extrairErro(mixed $json, string $body): string
    {
        if (is_array($json)) {
            $msg = data_get($json, 'message') ?? data_get($json, 'error');
            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
            $causes = data_get($json, 'cause.0.description');
            if (is_string($causes) && $causes !== '') {
                return $causes;
            }
        }

        return Str::limit(trim($body) ?: 'Erro na API do Mercado Pago.', 200);
    }
}
