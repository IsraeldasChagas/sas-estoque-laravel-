<?php

namespace App\Services\Fidelidade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Textos e cálculos de apresentação do programa (vitrine, reserva).
 */
class FidelidadeProgramaApresentacaoService
{
    public const BASE_GASTO_ACUMULADO = 'gasto_acumulado_meta';

    public const TIPO_PRODUTO = 'produto';

    public const TIPO_BRINDE = 'brinde';

    public const TIPO_DESCONTO_VALOR = 'desconto_valor';

    public const TIPO_DESCONTO_PERCENTUAL = 'desconto_percentual';

    public const TIPO_CATALOGO = 'catalogo';

    public const TIPO_CATALOGO_CONSULTA = 'catalogo_consulta';

    public function __construct(
        private FidelidadeCatalogoConsultaService $catalogoConsulta,
    ) {}

    /**
     * Bloco "Como funciona" da vitrine — regras gerais + recompensa conforme tipo configurado.
     *
     * @return array{
     *   tipo:string,
     *   regras:list<string>,
     *   recompensa_titulo:string,
     *   recompensa_linhas:list<string>,
     *   catalogo_qtd_escolhas:?int,
     *   catalogo_produtos:list<array{id:int,nome:string}>
     * }
     */
    public function comoFuncionaVitrine(
        object $programa,
        string $nomeUnidade,
        ?int $contaId = null,
        ?int $unidadeFidelidadeContexto = null,
        ?string $nomeLoja = null,
    ): array {
        $meta = max(1, (int) ($programa->pedidos_meta ?? 10));
        $unidade = trim($nomeUnidade) !== '' ? trim($nomeUnidade) : 'unidade';
        $loja = trim((string) ($nomeLoja ?? '')) !== '' ? trim((string) $nomeLoja) : $unidade;
        $resumo = $this->resumoRecompensa($programa, $contaId, $unidadeFidelidadeContexto);

        return [
            'tipo' => $resumo['tipo'],
            'regras' => [
                'Você pode ter o cartão fidelidade tanto na reserva de mesa quanto nas compras no delivery da loja '.$loja.'.',
                'A cada reserva de mesa na unidade '.$unidade.', você ganha 1 selo após o pagamento da conta.',
                'A cada compra no delivery da loja '.$loja.', você também ganha 1 selo.',
                'O cartão e os selos pertencem ao cliente (mesmo telefone, CPF e e-mail informados na reserva ou no pedido).',
                'Meta: '.$meta.' selo(s) para resgatar a recompensa.',
                'Use o mesmo telefone da reserva ou do pedido e confirme com um código de 6 dígitos para consultar seu saldo.',
            ],
            'recompensa_titulo' => $this->tituloRecompensaVitrine($resumo['tipo']),
            'recompensa_linhas' => $resumo['linhas'],
            'catalogo_qtd_escolhas' => $resumo['catalogo_qtd_escolhas'],
            'catalogo_produtos' => $resumo['catalogo_produtos'],
        ];
    }

    /**
     * @return list<string>
     */
    public function linhasComoFunciona(object $programa, string $nomeUnidade, ?int $contaId = null): array
    {
        $bloco = $this->comoFuncionaVitrine($programa, $nomeUnidade, $contaId);
        $linhas = $bloco['regras'];
        foreach ($bloco['recompensa_linhas'] as $linha) {
            $linhas[] = $linha;
        }

        return $linhas;
    }

    /**
     * @return list<string>
     */
    public function linhasRecompensa(object $programa, ?int $contaId = null, ?int $unidadeFidelidadeContexto = null): array
    {
        $tipo = (string) ($programa->tipo_recompensa_padrao ?? self::TIPO_CATALOGO_CONSULTA);
        if ($tipo === self::TIPO_PRODUTO) {
            $tipo = self::TIPO_CATALOGO_CONSULTA;
        }
        $meta = max(1, (int) ($programa->pedidos_meta ?? 10));
        $texto = trim((string) ($programa->texto_recompensa ?? ''));

        return match ($tipo) {
            self::TIPO_DESCONTO_VALOR => $this->linhasDescontoValor($programa),
            self::TIPO_DESCONTO_PERCENTUAL => $this->linhasDescontoPercentual($programa, $meta, $contaId),
            self::TIPO_CATALOGO => ['Recompensa: escolha entre as opções do catálogo ao resgatar na loja.'],
            self::TIPO_CATALOGO_CONSULTA => $this->linhasCatalogoConsulta($programa, $unidadeFidelidadeContexto),
            self::TIPO_BRINDE => $texto !== ''
                ? ['Brinde ao completar a meta: '.$texto]
                : ['Brinde ao completar a meta — a loja informará o item na hora do resgate.'],
            default => $texto !== ''
                ? ['Recompensa ao completar a meta: '.$texto]
                : ['Recompensa ao completar a meta — consulte a loja para saber o benefício.'],
        };
    }

    /**
     * @return array{
     *   tipo:string,
     *   titulo:string,
     *   linhas:list<string>,
     *   desconto_percentual:?float,
     *   valor_desconto:?float,
     *   gasto_acumulado:?float,
     *   desconto_estimado:?float,
     *   catalogo_qtd_escolhas:?int,
     *   catalogo_produtos:list<array{id:int,nome:string}>
     * }
     */
    public function resumoRecompensa(object $programa, ?int $contaId = null, ?int $unidadeFidelidadeContexto = null): array
    {
        $tipo = (string) ($programa->tipo_recompensa_padrao ?? self::TIPO_CATALOGO_CONSULTA);
        if ($tipo === self::TIPO_PRODUTO) {
            $tipo = self::TIPO_CATALOGO_CONSULTA;
        }
        $meta = max(1, (int) ($programa->pedidos_meta ?? 10));
        $linhas = $this->linhasRecompensa($programa, $contaId, $unidadeFidelidadeContexto);
        $gasto = null;
        $descontoEstimado = null;
        $pct = $this->percentual($programa);
        $valorFixo = $this->valorDesconto($programa);
        $catalogoQtd = null;
        $catalogoProdutos = [];

        if ($tipo === self::TIPO_CATALOGO_CONSULTA) {
            $catalogoQtd = max(1, (int) ($programa->catalogo_qtd_escolhas ?? 1));
            $catalogoProdutos = $this->produtosCatalogoConsulta($programa, $unidadeFidelidadeContexto);
        }

        if ($tipo === self::TIPO_DESCONTO_PERCENTUAL && $contaId > 0 && $pct > 0) {
            $gasto = $this->gastoAcumuladoSelos($contaId, $meta);
            $descontoEstimado = round($gasto * ($pct / 100), 2);
        }

        $titulo = match ($tipo) {
            self::TIPO_DESCONTO_VALOR => 'Desconto em valor',
            self::TIPO_DESCONTO_PERCENTUAL => 'Desconto percentual',
            self::TIPO_BRINDE => 'Brinde',
            self::TIPO_CATALOGO => 'Catálogo de recompensas',
            self::TIPO_CATALOGO_CONSULTA => 'Catálogo (consulta)',
            default => 'Produto / benefício',
        };

        return [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'linhas' => $linhas,
            'desconto_percentual' => $tipo === self::TIPO_DESCONTO_PERCENTUAL ? $pct : null,
            'valor_desconto' => $tipo === self::TIPO_DESCONTO_VALOR ? $valorFixo : null,
            'gasto_acumulado' => $gasto,
            'desconto_estimado' => $descontoEstimado,
            'catalogo_qtd_escolhas' => $catalogoQtd,
            'catalogo_produtos' => $catalogoProdutos,
        ];
    }

    public function gastoAcumuladoSelos(int $contaId, int $meta): float
    {
        if ($contaId <= 0 || ! Schema::hasTable('fid_ledger') || ! Schema::hasTable('reservas_mesas')) {
            return 0.0;
        }

        $meta = max(1, $meta);
        $ultimoResgate = DB::table('fid_ledger')
            ->where('conta_id', $contaId)
            ->where('tipo', 'debito_resgate')
            ->whereNull('reverso_de_id')
            ->orderByDesc('created_at')
            ->value('created_at');

        $query = DB::table('fid_ledger')
            ->where('conta_id', $contaId)
            ->where('tipo', 'selo')
            ->where('referencia_tipo', 'reserva_mesa')
            ->whereNull('reverso_de_id')
            ->where('delta_selos', '>', 0);

        if ($ultimoResgate) {
            $query->where('created_at', '>', $ultimoResgate);
        }

        $reservaIds = $query
            ->orderByDesc('created_at')
            ->limit($meta)
            ->pluck('referencia_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($reservaIds === []) {
            return 0.0;
        }

        return (float) DB::table('reservas_mesas')
            ->whereIn('id', $reservaIds)
            ->sum('valor_conta');
    }

    private function linhasDescontoValor(object $programa): array
    {
        $valor = $this->valorDesconto($programa);
        if ($valor <= 0) {
            return ['Recompensa: desconto em valor fixo na conta (valor definido pela loja).'];
        }

        return ['Recompensa: desconto de R$ '.number_format($valor, 2, ',', '.').' na conta ao resgatar os selos.'];
    }

    /** @return list<string> */
    private function linhasDescontoPercentual(object $programa, int $meta, ?int $contaId): array
    {
        $pct = $this->percentual($programa);
        if ($pct <= 0) {
            return ['Recompensa: desconto percentual sobre o gasto das visitas que geraram os selos (percentual definido pela loja).'];
        }

        $pctFmt = rtrim(rtrim(number_format($pct, 2, ',', '.'), '0'), ',');
        $linhas = [
            'Recompensa: '.$pctFmt.'% de desconto sobre o total gasto nas '.$meta.' reserva(s) que completarem os selos deste ciclo.',
            'O desconto é calculado somando o valor pago em cada conta das visitas que geraram os selos (desde o último resgate).',
        ];

        if ($contaId > 0) {
            $gasto = $this->gastoAcumuladoSelos($contaId, $meta);
            if ($gasto > 0) {
                $estimado = round($gasto * ($pct / 100), 2);
                $linhas[] = 'Gasto acumulado neste ciclo: R$ '.number_format($gasto, 2, ',', '.')
                    .' · desconto estimado ao completar a meta: R$ '.number_format($estimado, 2, ',', '.').'.';
            }
        }

        return $linhas;
    }

    private function percentual(object $programa): float
    {
        $pct = (float) ($programa->desconto_percentual ?? 0);

        return max(0.0, min(100.0, $pct));
    }

    private function valorDesconto(object $programa): float
    {
        return max(0.0, (float) ($programa->valor_desconto ?? 0));
    }

    /** @return list<string> */
    private function linhasCatalogoConsulta(object $programa, ?int $unidadeFidelidadeContexto = null): array
    {
        $qtd = max(1, (int) ($programa->catalogo_qtd_escolhas ?? 1));
        $produtos = $this->produtosCatalogoConsulta($programa, $unidadeFidelidadeContexto);
        $nomes = array_values(array_filter(array_map(
            fn ($item) => trim((string) ($item['nome'] ?? '')),
            $produtos
        )));

        if ($nomes === []) {
            return [
                'Entre os produtos do cardápio abaixo, escolha '.$qtd.' item(ns) como recompensa ao completar a meta.',
            ];
        }

        $meta = max(1, (int) ($programa->pedidos_meta ?? 10));
        $escolha = $qtd === 1 ? 'um deles' : ($qtd.' deles');
        $linhas = [
            'Entre estes produtos, você pode escolher '.$escolha.' como sua recompensa ao completar os '.$meta.' selos:',
        ];
        foreach ($nomes as $nome) {
            $linhas[] = $nome;
        }
        if ($qtd > 1) {
            $linhas[] = 'Pode repetir o mesmo produto até atingir o limite de '.$qtd.'.';
        }

        return $linhas;
    }

    /** @return list<array{id:int,nome:string,preco?:float}> */
    private function produtosCatalogoConsulta(object $programa, ?int $unidadeFidelidadeContexto = null): array
    {
        $contexto = $unidadeFidelidadeContexto > 0
            ? $unidadeFidelidadeContexto
            : $this->catalogoConsulta->unidadeFidelidadeCanonica((int) ($programa->unidade_id ?? 0));

        return $this->catalogoConsulta->produtosDoPrograma($programa, $contexto);
    }

    private function tituloRecompensaVitrine(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_DESCONTO_VALOR => 'Desconto na conta',
            self::TIPO_DESCONTO_PERCENTUAL => 'Desconto percentual',
            self::TIPO_BRINDE => 'Brinde',
            self::TIPO_CATALOGO => 'Catálogo de recompensas',
            self::TIPO_CATALOGO_CONSULTA => 'Catálogo (consulta)',
            default => 'Recompensa',
        };
    }
}
