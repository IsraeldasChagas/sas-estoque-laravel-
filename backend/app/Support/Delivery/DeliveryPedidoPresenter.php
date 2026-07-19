<?php

namespace App\Support\Delivery;

final class DeliveryPedidoPresenter
{
    /** @var array<string, string> */
    public const STATUS_ROTULOS = [
        'pendente_loja' => 'Pendente da loja',
        'recebido' => 'Recebido',
        'preparo' => 'Em preparo',
        'pronto' => 'Pronto',
        'rota' => 'Em rota',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
        'endereco_nao_encontrado' => 'Endereço não encontrado',
    ];

    public const PAGAMENTO_PIX = 'pix';

    public const PAGAMENTO_CARTAO_ONLINE = 'cartao_online';

    public const PAGAMENTO_STATUS_PENDENTE = 'pendente';

    public const PAGAMENTO_STATUS_PAGO = 'pago';

    /** @var array<string, string> */
    public const PAGAMENTO_ROTULOS = [
        'pix' => 'PIX',
        'cartao' => 'Cartão na entrega',
        'cartao_credito' => 'Cartão de crédito (na maquininha)',
        'cartao_debito' => 'Cartão de débito (na maquininha)',
        'cartao_credito_maquininha' => 'Cartão de crédito (na maquininha)',
        'cartao_debito_maquininha' => 'Cartão de débito (na maquininha)',
        'credito' => 'Cartão de crédito (na maquininha)',
        'debito' => 'Cartão de débito (na maquininha)',
        'dinheiro' => 'Dinheiro',
        'cartao_online' => 'Cartão online',
        'entrega' => 'Na entrega (combinar)',
    ];

    public static function rotuloStatus(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return self::STATUS_ROTULOS[$status] ?? $status;
    }

    public static function rotuloFulfillment(?string $fulfillment): string
    {
        return strtolower(trim((string) $fulfillment)) === 'entrega'
            ? 'Entrega'
            : 'Retirada na loja';
    }

    public static function rotuloFormaPagamento(?string $forma): string
    {
        $forma = strtolower(trim((string) $forma));

        return self::PAGAMENTO_ROTULOS[$forma] ?? ucfirst($forma ?: '—');
    }

    public static function descricaoPagamento(object $pedido): string
    {
        $forma = strtolower(trim((string) ($pedido->pagamento_forma ?? '')));
        $rotulo = self::rotuloFormaPagamento($pedido->pagamento_forma ?? null);

        if ($forma !== 'dinheiro') {
            return $rotulo;
        }

        $troco = $pedido->pagamento_troco_para ?? null;
        if ($troco === null || $troco === '') {
            return $rotulo.' — valor exato (sem troco)';
        }

        return $rotulo.' — troco para R$ '.number_format((float) $troco, 2, ',', '.');
    }

    public static function enderecoLinha(object $pedido): string
    {
        $texto = trim((string) ($pedido->endereco_texto ?? ''));
        if ($texto !== '') {
            return $texto;
        }

        return implode(', ', array_filter([
            trim((string) ($pedido->endereco_rua ?? '')),
            trim((string) ($pedido->endereco_numero ?? '')),
            trim((string) ($pedido->endereco_bairro ?? '')),
            trim((string) ($pedido->endereco_cidade ?? '')),
            trim((string) ($pedido->endereco_uf ?? '')),
        ], fn ($parte) => $parte !== ''));
    }

    /** @return array{adicionais: array<int, array<string, mixed>>, observacao: string, nota_produto: int} */
    public static function opcoesLinhaParaExibicao(mixed $opcoes): array
    {
        $opcoes = is_array($opcoes) ? $opcoes : [];
        $lista = is_array($opcoes['adicionais'] ?? null) ? $opcoes['adicionais'] : [];

        foreach (is_array($opcoes['retiradas'] ?? null) ? $opcoes['retiradas'] : [] as $ret) {
            $lista[] = [
                'nome' => (string) ($ret['nome'] ?? ''),
                'tipo' => 'retirar_ingrediente',
                'quantidade' => (int) ($ret['quantidade'] ?? 1),
                'preco' => 0.0,
            ];
        }

        return [
            'adicionais' => $lista,
            'observacao' => trim((string) ($opcoes['observacao'] ?? '')),
            'nota_produto' => (int) ($opcoes['nota_produto'] ?? 0),
        ];
    }

    public static function entregadorPodeRegistrarResultado(?string $status): bool
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, ['pronto', 'rota'], true);
    }

    public static function normalizarCodigoPublico(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        return ltrim($codigo, '#');
    }

    public static function codigoPublicoConfere(string $informado, string $esperado): bool
    {
        $a = self::normalizarCodigoPublico($informado);
        $b = self::normalizarCodigoPublico($esperado);

        return $a !== '' && hash_equals($b, $a);
    }

    public static function isPix(object $pedido): bool
    {
        return strtolower(trim((string) ($pedido->pagamento_forma ?? ''))) === self::PAGAMENTO_PIX;
    }

    public static function isCartaoOnline(object $pedido): bool
    {
        return strtolower(trim((string) ($pedido->pagamento_forma ?? ''))) === self::PAGAMENTO_CARTAO_ONLINE;
    }

    public static function isPagamentoGateway(object $pedido): bool
    {
        return self::isPix($pedido) || self::isCartaoOnline($pedido);
    }

    public static function isPixPago(object $pedido): bool
    {
        if (! self::isPix($pedido)) {
            return false;
        }

        return strtolower(trim((string) ($pedido->pagamento_status ?? ''))) === self::PAGAMENTO_STATUS_PAGO;
    }

    public static function isCartaoOnlinePago(object $pedido): bool
    {
        if (! self::isCartaoOnline($pedido)) {
            return false;
        }

        return strtolower(trim((string) ($pedido->pagamento_status ?? ''))) === self::PAGAMENTO_STATUS_PAGO;
    }

    public static function pagamentoGatewayPago(object $pedido): bool
    {
        return strtolower(trim((string) ($pedido->pagamento_status ?? ''))) === self::PAGAMENTO_STATUS_PAGO
            && self::isPagamentoGateway($pedido);
    }

    public static function pixPendenteConfirmacao(object $pedido): bool
    {
        return self::isPix($pedido) && ! self::isPixPago($pedido);
    }

    public static function cartaoOnlinePendente(object $pedido): bool
    {
        return self::isCartaoOnline($pedido) && ! self::isCartaoOnlinePago($pedido);
    }

    public static function pagamentoGatewayPendente(object $pedido): bool
    {
        return self::isPagamentoGateway($pedido) && ! self::pagamentoGatewayPago($pedido);
    }

    public static function rotuloPagamentoStatus(?object $pedido): ?string
    {
        if ($pedido === null || ! self::isPagamentoGateway($pedido)) {
            return null;
        }

        if (self::pagamentoGatewayPago($pedido)) {
            return self::isCartaoOnline($pedido) ? 'Cartão online confirmado' : 'PIX confirmado';
        }

        return self::isCartaoOnline($pedido) ? 'Cartão online pendente' : 'PIX pendente';
    }

    public static function exigirPixConfirmado(object $config): bool
    {
        return (bool) ($config->exigir_pix_confirmado ?? false);
    }

    public static function bloqueiaAceitePorPix(object $pedido, ?object $config): bool
    {
        if ($config === null || ! self::exigirPixConfirmado($config)) {
            return false;
        }

        return self::pagamentoGatewayPendente($pedido);
    }

    public static function logoUrl(?object $config): ?string
    {
        $path = trim((string) ($config->logo_path ?? ''));
        if ($path === '') {
            return null;
        }

        return '/'.ltrim($path, '/');
    }
}
