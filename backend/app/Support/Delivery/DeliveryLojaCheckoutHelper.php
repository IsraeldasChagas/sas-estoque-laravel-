<?php

namespace App\Support\Delivery;

final class DeliveryLojaCheckoutHelper
{
    public const PAGAMENTO_PIX = 'pix';

    public const PAGAMENTO_CARTAO_CREDITO = 'cartao_credito';

    public const PAGAMENTO_CARTAO_DEBITO = 'cartao_debito';

    public const PAGAMENTO_DINHEIRO = 'dinheiro';

    /** @var array<string, string> */
    public const FORMAS_ROTULOS = [
        self::PAGAMENTO_PIX => 'PIX',
        self::PAGAMENTO_CARTAO_CREDITO => 'Cartão de crédito (na maquininha)',
        self::PAGAMENTO_CARTAO_DEBITO => 'Cartão de débito (na maquininha)',
        self::PAGAMENTO_DINHEIRO => 'Dinheiro',
        'cartao' => 'Cartão na entrega',
        'credito' => 'Cartão de crédito (na maquininha)',
        'debito' => 'Cartão de débito (na maquininha)',
    ];

    /** @return array<string, string> */
    public static function formasPagamentoLojaPublica(object $config): array
    {
        $opcoes = collect(self::FORMAS_ROTULOS)
            ->only([
                self::PAGAMENTO_PIX,
                self::PAGAMENTO_CARTAO_CREDITO,
                self::PAGAMENTO_CARTAO_DEBITO,
                self::PAGAMENTO_DINHEIRO,
            ]);

        if (! self::pixConfiguradaParaCheckout($config)) {
            $opcoes = $opcoes->except([self::PAGAMENTO_PIX]);
        }

        return $opcoes->all();
    }

    public static function pixConfiguradaParaCheckout(object $config): bool
    {
        $instrucoes = trim((string) ($config->pix_instrucoes ?? ''));
        $copia = trim((string) ($config->pix_copia_cola ?? ''));
        $chave = trim((string) ($config->pix_chave ?? ''));

        return $instrucoes !== '' || $copia !== '' || $chave !== '';
    }

    public static function pixChaveRotuloTipo(object $config): string
    {
        $tipos = [
            'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'email' => 'E-mail',
            'telefone' => 'Telefone',
            'aleatoria' => 'Chave aleatória',
            'phone' => 'Telefone',
            'random' => 'Chave aleatória',
        ];
        $tipo = strtolower(trim((string) ($config->pix_tipo ?? '')));

        return $tipos[$tipo] ?? ($tipo !== '' ? ucfirst($tipo) : 'Chave');
    }

    public static function pixQrCodeDataUri(object $config): ?string
    {
        $copia = trim((string) ($config->pix_copia_cola ?? ''));
        if ($copia === '') {
            return null;
        }

        return GeradorQrCodePix::dataUriSvg($copia);
    }

    public static function normalizarFormaPagamento(string $forma): string
    {
        $forma = strtolower(trim($forma));

        return match ($forma) {
            'credito', 'cartao_credito_maquininha' => self::PAGAMENTO_CARTAO_CREDITO,
            'debito', 'cartao_debito_maquininha' => self::PAGAMENTO_CARTAO_DEBITO,
            'cartao' => self::PAGAMENTO_CARTAO_CREDITO,
            default => $forma,
        };
    }

    public static function fulfillmentFromTipoEntrega(string $tipo): string
    {
        return in_array(strtolower(trim($tipo)), ['balcao', 'retirada', 'pickup'], true)
            ? 'retirada'
            : 'entrega';
    }

    public static function tipoEntregaFromFulfillment(string $fulfillment): string
    {
        return in_array(strtolower(trim($fulfillment)), ['retirada', 'pickup'], true)
            ? 'balcao'
            : 'entrega';
    }
}
