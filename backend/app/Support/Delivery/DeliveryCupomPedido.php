<?php

namespace App\Support\Delivery;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DeliveryCupomPedido
{
    private const TEXTO_WHATSAPP_MAX = 3600;

    /**
     * @param  iterable<object>  $itens
     */
    public static function textoCupomCompleto(object $pedido, object $config, iterable $itens): string
    {
        $larguraLinha = 32;
        $lines = [];

        $canal = strtolower(trim((string) ($pedido->canal ?? 'loja')));
        $subtituloMarca = match ($canal) {
            'admin', 'balcao' => 'Pedido balcão',
            'whatsapp' => 'Pedido WhatsApp',
            default => 'Pedido online',
        };

        $nomeLoja = trim((string) ($config->nome_loja ?? 'Loja'));
        $lines[] = $subtituloMarca;
        $lines[] = '*'.self::fixUpperNomeLoja($nomeLoja).'*';

        $end = trim((string) ($config->endereco_texto ?? ''));
        if ($end !== '') {
            $lines[] = $end;
        }

        $cepLoja = self::cepLoja((int) ($pedido->unidade_id ?? 0));
        if ($cepLoja !== null) {
            $lines[] = 'CEP '.substr($cepLoja, 0, 5).'-'.substr($cepLoja, 5);
        }
        $waLoja = trim((string) ($config->whatsapp ?? ''));
        if ($waLoja !== '') {
            $lines[] = 'WhatsApp loja: '.$waLoja;
        }
        $cnpjLoja = self::cnpjLoja((int) ($pedido->unidade_id ?? 0));
        if ($cnpjLoja !== null) {
            $lines[] = 'CNPJ '.$cnpjLoja;
        }

        $lines[] = self::linhaTracejada($larguraLinha);
        $lines[] = 'Cupom simplificado / comanda';
        $lines[] = 'Pedido *'.($pedido->codigo_publico ?? '').'*';
        $createdAt = Carbon::parse($pedido->created_at ?? now());
        $lines[] = $createdAt->format('d/m/Y H:i').' · '.DeliveryPedidoPresenter::rotuloStatus($pedido->status ?? null);
        $lines[] = DeliveryPedidoPresenter::rotuloFulfillment($pedido->fulfillment ?? null);
        $lines[] = self::linhaTracejada($larguraLinha);

        $lines[] = '*CLIENTE E ENTREGA*';
        if (trim((string) ($pedido->cliente_nome ?? '')) !== '') {
            $lines[] = (string) $pedido->cliente_nome;
        }
        if (trim((string) ($pedido->cliente_telefone ?? '')) !== '') {
            $lines[] = (string) $pedido->cliente_telefone;
        }
        if (trim((string) ($pedido->cliente_email ?? '')) !== '') {
            $lines[] = (string) $pedido->cliente_email;
        }
        $endereco = DeliveryPedidoPresenter::enderecoLinha($pedido);
        if ($endereco !== '') {
            $lines[] = $endereco;
        }
        if (trim((string) ($pedido->endereco_complemento ?? '')) !== '') {
            $lines[] = (string) $pedido->endereco_complemento;
        }
        $cep = preg_replace('/\D+/', '', (string) ($pedido->endereco_cep ?? ''));
        if (strlen($cep) === 8) {
            $lines[] = 'CEP '.substr($cep, 0, 5).'-'.substr($cep, 5);
        }

        $lines[] = self::linhaTracejada($larguraLinha);
        $lines[] = '*ITENS*';
        foreach ($itens as $it) {
            $opcoes = $it->opcoes_json ?? null;
            if (is_string($opcoes)) {
                $opcoes = json_decode($opcoes, true);
            }
            $nomeQtd = ($it->nome_produto ?? '').' × '.($it->quantidade ?? 1);
            $valor = 'R$ '.number_format((float) ($it->subtotal ?? 0), 2, ',', '.');
            $lines[] = self::linhaDuasColunas($nomeQtd, $valor, $larguraLinha);
            foreach (self::linhasOpcoesItemTexto(is_array($opcoes) ? $opcoes : []) as $lx) {
                $lines[] = '  '.$lx;
            }
        }

        $lines[] = self::linhaTracejada($larguraLinha);
        $lines[] = self::linhaDuasColunas('Subtotal', 'R$ '.number_format((float) ($pedido->subtotal ?? 0), 2, ',', '.'), $larguraLinha);
        $rotTaxa = strtolower(trim((string) ($pedido->fulfillment ?? 'entrega'))) === 'entrega'
            ? 'Taxa de entrega'
            : 'Retirada (sem frete)';
        $lines[] = self::linhaDuasColunas($rotTaxa, 'R$ '.number_format((float) ($pedido->frete_valor ?? 0), 2, ',', '.'), $larguraLinha);
        $lines[] = self::linhaDuasColunas('*TOTAL*', '*R$ '.number_format((float) ($pedido->total ?? 0), 2, ',', '.').'*', $larguraLinha);

        $lines[] = self::linhaTracejada($larguraLinha);
        $lines[] = '*PAGAMENTO*';
        $lines[] = DeliveryPedidoPresenter::descricaoPagamento($pedido);

        if (trim((string) ($pedido->observacoes ?? '')) !== '') {
            $lines[] = self::linhaTracejada($larguraLinha);
            $lines[] = '*OBSERVAÇÕES*';
            $lines[] = (string) $pedido->observacoes;
        }

        $slug = trim((string) ($config->slug ?? ''));
        $codigo = trim((string) ($pedido->codigo_publico ?? ''));
        $clienteToken = trim((string) ($pedido->cliente_token ?? ''));
        if ($slug !== '' && $codigo !== '' && strlen($clienteToken) === 64) {
            $lines[] = self::linhaTracejada($larguraLinha);
            $lines[] = '*ACOMPANHAR PEDIDO*';
            $lines[] = route('delivery.public.order', ['slug' => $slug, 'codigo' => $codigo, 'token' => $clienteToken], absolute: true);
        }

        $lines[] = self::linhaTracejada($larguraLinha);
        $lines[] = 'Obrigado pela preferência!';
        $lines[] = '_'.config('app.name').'_';

        return implode("\n", $lines);
    }

    /**
     * @param  iterable<object>  $itens
     */
    public static function urlWhatsAppCupom(object $pedido, object $config, iterable $itens): ?string
    {
        $telefone = trim((string) ($pedido->cliente_whatsapp ?? ''));
        if ($telefone === '') {
            $telefone = trim((string) ($pedido->cliente_telefone ?? ''));
        }

        $text = self::textoCupomCompleto($pedido, $config, $itens);
        if (strlen($text) > self::TEXTO_WHATSAPP_MAX) {
            $cut = function_exists('mb_substr')
                ? mb_substr($text, 0, self::TEXTO_WHATSAPP_MAX - 120, 'UTF-8')
                : substr($text, 0, self::TEXTO_WHATSAPP_MAX - 120);
            $text = $cut."\n\n...(mensagem limitada — imprima o cupom completo no painel.)";
        }

        return DeliveryWhatsAppHelper::urlComTexto($telefone, $text);
    }

    private static function linhaTracejada(int $largura): string
    {
        return str_repeat('-', max(8, $largura));
    }

    private static function linhaDuasColunas(string $esq, string $dir, int $largura): string
    {
        $tamEsq = self::larguraVisual($esq);
        $tamDir = self::larguraVisual($dir);
        $espacos = $largura - $tamEsq - $tamDir;
        if ($espacos < 1) {
            $espacos = 1;
        }

        return $esq.str_repeat(' ', $espacos).$dir;
    }

    private static function larguraVisual(string $texto): int
    {
        $semMarca = preg_replace('/(?<!\\\\)([\*_~])(.+?)(?<!\\\\)\\1/u', '$2', $texto) ?? $texto;
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($semMarca, 'UTF-8');
        }

        return strlen($semMarca);
    }

    /** @return array<int, string> */
    private static function linhasOpcoesItemTexto(array $opcoes): array
    {
        $linha = DeliveryPedidoPresenter::opcoesLinhaParaExibicao($opcoes);
        $out = [];
        if ($linha['observacao'] !== '') {
            $out[] = 'Obs.: '.$linha['observacao'];
        }
        foreach ($linha['adicionais'] as $op) {
            $tipo = (string) ($op['tipo'] ?? '');
            $nome = (string) ($op['nome'] ?? '');
            if ($tipo === 'retirar' || $tipo === 'retirar_ingrediente') {
                $qRet = (int) ($op['quantidade'] ?? 1);
                $out[] = '- '.$nome.($qRet > 1 ? ' x'.$qRet : '');
            } else {
                $qOp = (int) ($op['quantidade'] ?? 1);
                $preco = (float) ($op['preco'] ?? 0);
                $s = '+ '.$nome.($qOp > 1 ? ' x'.$qOp : '');
                if ($preco > 0) {
                    $s .= ' (+R$ '.number_format($preco * max(1, $qOp), 2, ',', '.').')';
                }
                $out[] = $s;
            }
        }

        return $out;
    }

    private static function cepLoja(int $unidadeId): ?string
    {
        if ($unidadeId <= 0 || ! Schema::hasTable('unidades') || ! Schema::hasColumn('unidades', 'cep')) {
            return null;
        }

        $cepDigits = preg_replace('/\D+/', '', (string) DB::table('unidades')->where('id', $unidadeId)->value('cep'));

        return strlen($cepDigits) === 8 ? $cepDigits : null;
    }

    private static function cnpjLoja(int $unidadeId): ?string
    {
        if ($unidadeId > 0 && Schema::hasTable('unidades') && Schema::hasColumn('unidades', 'cnpj')) {
            $cnpj = trim((string) DB::table('unidades')->where('id', $unidadeId)->value('cnpj'));
            if ($cnpj !== '') {
                return $cnpj;
            }
        }

        if (! Schema::hasTable('sistema_configuracoes')) {
            return null;
        }

        $cnpjCfg = trim((string) DB::table('sistema_configuracoes')->where('chave', 'empresa_cnpj')->value('valor'));

        return $cnpjCfg !== '' ? $cnpjCfg : null;
    }

    private static function fixUpperNomeLoja(string $nome): string
    {
        if ($nome === '') {
            return 'LOJA';
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($nome, 'UTF-8');
        }

        return strtoupper($nome);
    }
}
