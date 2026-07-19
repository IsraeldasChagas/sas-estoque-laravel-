<?php

namespace App\Support\Delivery;

final class DeliveryPedidoPresenter
{
    /** @var array<string, string> */
    public const STATUS_ROTULOS = [
        'pendente_loja' => 'Pendente da loja',
        'recebido' => 'Aceito',
        'preparo' => 'Em preparo',
        'pronto' => 'Pronto',
        'rota' => 'Em rota',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
        'endereco_nao_encontrado' => 'Endereço não encontrado',
    ];

    /** @var array<string, string> */
    public const PAGAMENTO_ROTULOS = [
        'pix' => 'PIX',
        'cartao' => 'Cartão',
        'cartao_credito_maquininha' => 'Cartão de crédito (na maquininha)',
        'cartao_debito_maquininha' => 'Cartão de débito (na maquininha)',
        'dinheiro' => 'Dinheiro',
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
        return self::rotuloFormaPagamento($pedido->pagamento_forma ?? null);
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

    public static function logoUrl(?object $config): ?string
    {
        $path = trim((string) ($config->logo_path ?? ''));
        if ($path === '') {
            return null;
        }

        return '/'.ltrim($path, '/');
    }
}
