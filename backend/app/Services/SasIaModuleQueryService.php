<?php

namespace App\Services;

use App\Support\Financeiro\FinanceiroGerencialCalculo;
use App\Support\SasIa\SasIaContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consultas read-only do SAS IA — ferramentas por módulo do menu.
 */
class SasIaModuleQueryService
{
    public function __construct(
        private SasIaDocumentService $documentService
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function executar(string $toolName, SasIaContext $ctx, array $args): array
    {
        return match ($toolName) {
            'consultar_lotes_proximos_vencer' => $this->lotesProximosVencer($ctx, $args),
            'consultar_locais_estoque' => $this->locaisEstoque($ctx, $args),
            'consultar_resumo_unidades' => $this->resumoUnidades($ctx),
            'consultar_resumo_usuarios' => $this->resumoUsuarios($ctx),
            'consultar_fechamentos_recentes' => $this->fechamentosRecentes($ctx, $args),
            'consultar_boletos_resumo' => $this->boletosResumo($ctx, $args),
            'consultar_alvaras_vencendo' => $this->alvarasVencendo($ctx, $args),
            'consultar_proventos_resumo' => $this->proventosResumo($ctx, $args),
            'consultar_despesas_fixas_resumo' => $this->despesasFixasResumo($ctx),
            'consultar_vale_consumo_recente' => $this->valeConsumoRecente($ctx, $args),
            'consultar_recibos_ajuda_resumo' => $this->recibosAjudaResumo($ctx, $args),
            'consultar_reservas_periodo' => $this->reservasPeriodo($ctx, $args),
            'consultar_mesas_resumo' => $this->mesasResumo($ctx, $args),
            'consultar_funcionarios_resumo' => $this->funcionariosResumo($ctx),
            'consultar_rh_recrutamento_resumo' => $this->rhRecrutamentoResumo($ctx),
            'consultar_vagas_rh' => $this->vagasRh($ctx, $args),
            'consultar_candidatos_rh' => $this->candidatosRh($ctx, $args),
            'consultar_folha_ponto_resumo' => $this->folhaPontoResumo($ctx, $args),
            'consultar_rescisoes_rh' => $this->rescisoesRh($ctx, $args),
            'consultar_energia_resumo' => $this->energiaResumo($ctx),
            'consultar_equipamentos_energia' => $this->equipamentosEnergia($ctx, $args),
            'consultar_patrimonio_resumo' => $this->patrimonioResumo($ctx),
            'consultar_patrimonio_manutencoes' => $this->patrimonioManutencoes($ctx, $args),
            'consultar_investimento_resumo' => $this->investimentoResumo($ctx),
            'consultar_kanban_resumo' => $this->kanbanResumo($ctx, $args),
            'consultar_manual_documentacao' => $this->manualDocumentacao($args),
            default => ['erro' => true, 'mensagem' => 'Ferramenta não implementada neste serviço.'],
        };
    }

    /** @param  array<string, mixed>  $args */
    private function lotesProximosVencer(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('lotes')) {
            return ['lotes' => [], 'total' => 0];
        }

        $dias = min(90, max(1, (int) ($args['dias'] ?? 15)));
        $limite = now()->addDays($dias)->format('Y-m-d');
        $hoje = now()->format('Y-m-d');

        $q = DB::table('lotes as l')
            ->leftJoin('produtos as p', 'l.produto_id', '=', 'p.id')
            ->leftJoin('unidades as u', 'l.unidade_id', '=', 'u.id')
            ->whereNotNull('l.data_validade')
            ->where('l.data_validade', '<=', $limite)
            ->select('l.id', 'p.nome as produto', 'l.numero_lote', 'l.data_validade', 'l.qtd_atual as quantidade', 'u.nome as unidade')
            ->orderBy('l.data_validade')
            ->limit(30);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('l.unidade_id', $uid);
        }

        $rows = $q->get()->map(fn ($r) => [
            'produto' => $r->produto,
            'lote' => $r->numero_lote,
            'validade' => $r->data_validade,
            'quantidade' => (float) ($r->quantidade ?? 0),
            'unidade' => $r->unidade,
            'vencido' => $r->data_validade < $hoje,
        ])->all();

        return ['dias' => $dias, 'total' => count($rows), 'lotes' => $rows];
    }

    /** @param  array<string, mixed>  $args */
    private function locaisEstoque(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('locais')) {
            return ['locais' => []];
        }

        $q = DB::table('locais as l')
            ->leftJoin('unidades as u', 'l.unidade_id', '=', 'u.id')
            ->select('l.id', 'l.nome', 'l.tipo', 'l.ativo', 'u.nome as unidade_nome')
            ->orderBy('u.nome')
            ->orderBy('l.nome')
            ->limit(40);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('l.unidade_id', $unidadeId);
        }

        $rows = $q->get();

        return [
            'total' => $rows->count(),
            'locais' => $rows->map(fn ($l) => [
                'id' => $l->id,
                'nome' => $l->nome,
                'tipo' => $l->tipo ?? null,
                'ativo' => (bool) ($l->ativo ?? true),
                'unidade' => $l->unidade_nome,
            ])->all(),
        ];
    }

    private function resumoUnidades(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('unidades')) {
            return ['unidades' => []];
        }

        $rows = DB::table('unidades')
            ->select('id', 'nome', 'ativo')
            ->orderBy('nome')
            ->limit(50)
            ->get();

        return [
            'total' => $rows->count(),
            'ativas' => $rows->where('ativo', 1)->count(),
            'unidades' => $rows->map(fn ($u) => ['id' => $u->id, 'nome' => $u->nome, 'ativo' => (bool) $u->ativo])->values()->all(),
        ];
    }

    private function resumoUsuarios(SasIaContext $ctx): array
    {
        if (! $ctx->isAdmin() && ! $ctx->temModulo('usuarios')) {
            return ['erro' => true, 'mensagem' => 'Não encontrei informação suficiente ou você não tem permissão para acessar esse dado.'];
        }

        if (! Schema::hasTable('usuarios')) {
            return ['total' => 0];
        }

        $porPerfil = DB::table('usuarios')
            ->where('ativo', 1)
            ->select('perfil', DB::raw('COUNT(*) as qtd'))
            ->groupBy('perfil')
            ->pluck('qtd', 'perfil');

        return [
            'total_ativos' => (int) DB::table('usuarios')->where('ativo', 1)->count(),
            'por_perfil' => $porPerfil,
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function fechamentosRecentes(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('fechamentos_caixa')) {
            return ['fechamentos' => []];
        }

        $limite = min(20, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('fechamentos_caixa as f')
            ->leftJoin('unidades as u', 'f.unidade_id', '=', 'u.id')
            ->select('f.id', 'f.data_fechamento', 'f.unidade_id', 'u.nome as unidade_nome')
            ->orderByDesc('f.data_fechamento')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('f.unidade_id', $uid);
        }

        return [
            'fechamentos' => $q->get()->map(fn ($f) => [
                'id' => $f->id,
                'data' => $f->data_fechamento,
                'unidade' => $f->unidade_nome,
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function boletosResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('boletos')) {
            return ['total' => 0];
        }

        $q = DB::table('boletos');
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        } elseif (isset($args['unidade_id'])) {
            $q->where('unidade_id', (int) $args['unidade_id']);
        }

        $porStatus = (clone $q)->select('status', DB::raw('COUNT(*) as qtd'), DB::raw('SUM(valor) as valor'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->status => ['qtd' => (int) $r->qtd, 'valor' => round((float) $r->valor, 2)]]);

        return ['por_status' => $porStatus];
    }

    /** @param  array<string, mixed>  $args */
    private function alvarasVencendo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('alvaras')) {
            return ['alvaras' => []];
        }

        $dias = min(365, max(7, (int) ($args['dias'] ?? 60)));
        $ate = now()->addDays($dias)->format('Y-m-d');

        $q = DB::table('alvaras as a')
            ->leftJoin('unidades as u', 'a.unidade_id', '=', 'u.id')
            ->where('a.data_vencimento', '<=', $ate)
            ->select('a.id', 'a.tipo', 'a.data_vencimento', 'u.nome as unidade')
            ->orderBy('a.data_vencimento')
            ->limit(25);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('a.unidade_id', $uid);
        }

        return ['dias' => $dias, 'alvaras' => $q->get()->all()];
    }

    /** @param  array<string, mixed>  $args */
    private function proventosResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('proventos')) {
            return ['total' => 0];
        }

        $mes = trim((string) ($args['mes'] ?? now()->format('Y-m')));
        $q = DB::table('proventos')->where('data_provento', 'like', $mes.'%');
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        $porStatus = (clone $q)->select('status', DB::raw('COUNT(*) as qtd'), DB::raw('SUM(valor) as valor'))
            ->groupBy('status')
            ->get();

        return [
            'mes' => $mes,
            'por_status' => $porStatus->map(fn ($r) => [
                'status' => $r->status,
                'qtd' => (int) $r->qtd,
                'valor' => round((float) $r->valor, 2),
            ])->all(),
        ];
    }

    private function despesasFixasResumo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('despesas_fixas')) {
            return ['total' => 0, 'valor_mensal' => 0];
        }

        $q = DB::table('despesas_fixas')->where('ativo', 1);
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        return [
            'total_cadastradas' => (int) $q->count(),
            'valor_mensal_total' => round((float) (clone $q)->sum('valor'), 2),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function valeConsumoRecente(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('financeiro_vale_consumo')) {
            return ['lancamentos' => []];
        }

        $limite = min(20, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('financeiro_vale_consumo as v')
            ->leftJoin('funcionarios as f', 'v.funcionario_id', '=', 'f.id')
            ->select('v.id', 'v.tipo', 'v.valor', 'v.data_lancamento', 'f.nome_completo as funcionario')
            ->orderByDesc('v.data_lancamento')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('v.unidade_id', $uid);
        }

        return ['lancamentos' => $q->get()->all()];
    }

    /** @param  array<string, mixed>  $args */
    private function recibosAjudaResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('recibos_ajuda_custo')) {
            return ['recibos' => []];
        }

        $limite = min(20, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('recibos_ajuda_custo as r')
            ->leftJoin('funcionarios as f', 'r.funcionario_id', '=', 'f.id')
            ->select('r.id', 'f.nome_completo as funcionario', 'r.valor', 'r.competencia', 'r.finalidade', 'r.confirmado_em', 'r.created_at')
            ->orderByDesc('r.created_at')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        return ['recibos' => $q->get()->all()];
    }

    /** @param  array<string, mixed>  $args */
    private function reservasPeriodo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('reservas_mesas')) {
            return ['reservas' => []];
        }

        $data = trim((string) ($args['data'] ?? now()->format('Y-m-d')));
        $q = DB::table('reservas_mesas as r')
            ->leftJoin('unidades as u', 'r.unidade_id', '=', 'u.id')
            ->leftJoin('mesas as m', 'r.mesa_id', '=', 'm.id')
            ->whereDate('r.data_reserva', $data)
            ->select('r.id', 'r.nome_cliente', 'r.hora_reserva', 'r.qtd_pessoas', 'r.status', 'u.nome as unidade', 'm.numero_mesa')
            ->orderBy('r.hora_reserva')
            ->limit(30);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('r.unidade_id', $unidadeId);
        }

        $rows = $q->get();

        return ['data' => $data, 'total' => $rows->count(), 'reservas' => $rows->all()];
    }

    /** @param  array<string, mixed>  $args */
    private function mesasResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('mesas')) {
            return ['mesas' => []];
        }

        $q = DB::table('mesas as m')
            ->leftJoin('unidades as u', 'm.unidade_id', '=', 'u.id')
            ->where('m.ativo', 1);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('m.unidade_id', $unidadeId);
        }

        $porStatus = (clone $q)->select('m.status', DB::raw('COUNT(*) as qtd'))->groupBy('m.status')->pluck('qtd', 'status');

        return [
            'total_mesas' => (int) (clone $q)->count(),
            'por_status' => $porStatus,
        ];
    }

    private function funcionariosResumo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('funcionarios')) {
            return ['total' => 0];
        }

        $q = DB::table('funcionarios')->where('status', 'ativo');
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        $total = (int) $q->count();
        $porVinculo = Schema::hasColumn('funcionarios', 'tipo_vinculo')
            ? (clone $q)->select('tipo_vinculo', DB::raw('COUNT(*) as qtd'))->groupBy('tipo_vinculo')->pluck('qtd', 'tipo_vinculo')
            : [];

        return ['total_ativos' => $total, 'por_tipo_vinculo' => $porVinculo];
    }

    /** Mesmos totais do Dashboard RH (Recrutamento → Dashboard). */
    private function rhRecrutamentoResumo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return ['total_candidatos' => 0];
        }

        $totalCandidatos = (int) DB::table('rh_candidatos')->count();
        $totalCurriculosArquivos = Schema::hasTable('rh_curriculos')
            ? (int) DB::table('rh_curriculos')->count()
            : null;

        $out = [
            'fonte' => 'Dashboard RH — mesma consulta de /rh/dashboard/stats',
            'vagas_abertas' => Schema::hasTable('rh_vagas')
                ? (int) DB::table('rh_vagas')->where('status', 'aberta')->count()
                : 0,
            'total_candidatos' => $totalCandidatos,
            'candidatos_em_teste' => (int) DB::table('rh_candidatos')->where('status', 'em_teste')->count(),
            'candidatos_aprovados' => (int) DB::table('rh_candidatos')->whereIn('status', ['aprovado', 'em_contratacao'])->count(),
            'entrevistas_total' => Schema::hasTable('rh_entrevistas')
                ? (int) DB::table('rh_entrevistas')->count()
                : 0,
            'total_arquivos_curriculo' => $totalCurriculosArquivos,
            'observacao' => 'Para "quantos candidatos/currículos no recrutamento", use total_candidatos (card Candidatos do Dashboard). Não confunda com amostra limitada de consultar_candidatos_rh.',
        ];

        if (Schema::hasTable('rh_candidatos')) {
            $out['por_status'] = DB::table('rh_candidatos')
                ->select('status', DB::raw('COUNT(*) as qtd'))
                ->groupBy('status')
                ->pluck('qtd', 'status');
        }

        return $out;
    }

    /** @param  array<string, mixed>  $args */
    private function vagasRh(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('rh_vagas')) {
            return ['vagas' => []];
        }

        $q = DB::table('rh_vagas')->orderByDesc('updated_at')->limit(20);
        $status = trim((string) ($args['status'] ?? ''));
        if ($status !== '') {
            $q->where('status', $status);
        } else {
            $q->whereIn('status', ['aberta', 'pausada']);
        }

        return [
            'vagas' => $q->get(['id', 'titulo', 'unidade', 'setor', 'status', 'quantidade'])->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function candidatosRh(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('rh_candidatos')) {
            return ['total_candidatos' => 0, 'candidatos' => []];
        }

        $limite = min(25, max(1, (int) ($args['limite'] ?? 15)));
        $total = (int) DB::table('rh_candidatos')->count();

        $q = DB::table('rh_candidatos')
            ->select('id', 'nome', 'email', 'telefone', 'status', 'created_at')
            ->orderByDesc('created_at');

        if ($status = trim((string) ($args['status'] ?? ''))) {
            $q->where('status', $status);
            $total = (int) DB::table('rh_candidatos')->where('status', $status)->count();
        }

        $rows = $q->limit($limite)->get();

        return [
            'total_candidatos' => $total,
            'limite_amostra' => $limite,
            'observacao' => 'total_candidatos é o número real no sistema (Dashboard RH). A lista candidatos traz só os mais recentes.',
            'candidatos' => $rows->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function folhaPontoResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('rh_folhas_ponto')) {
            return ['folhas' => []];
        }

        $limite = min(15, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('rh_folhas_ponto as fp')
            ->leftJoin('funcionarios as f', 'fp.funcionario_id', '=', 'f.id')
            ->select('fp.id', 'fp.competencia', 'fp.status', 'f.nome_completo as funcionario')
            ->orderByDesc('fp.competencia')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('f.unidade_id', $uid);
        }

        return ['folhas' => $q->get()->all()];
    }

    /** @param  array<string, mixed>  $args */
    private function rescisoesRh(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('rh_rescisoes')) {
            return ['rescisoes' => []];
        }

        $limite = min(15, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('rh_rescisoes as r')
            ->leftJoin('funcionarios as f', 'r.funcionario_id', '=', 'f.id')
            ->select('r.id', 'f.nome_completo as funcionario', 'r.tipo_rescisao', 'r.data_demissao', 'r.total_liquido')
            ->orderByDesc('r.created_at')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('r.unidade_id', $uid);
        }

        return ['rescisoes' => $q->get()->all()];
    }

    private function energiaResumo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('energia_equipamentos_consumo')) {
            return ['equipamentos' => 0];
        }

        $q = DB::table('energia_equipamentos_consumo');
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        $porUnidade = DB::table('energia_equipamentos_consumo as e')
            ->join('unidades as u', 'e.unidade_id', '=', 'u.id')
            ->select('u.nome', DB::raw('COUNT(*) as equipamentos'), DB::raw('SUM(e.consumo_kwh) as consumo_kwh'))
            ->groupBy('u.id', 'u.nome');

        if ($uid = $ctx->unidadeEfetiva()) {
            $porUnidade->where('e.unidade_id', $uid);
        }

        return [
            'total_equipamentos' => (int) $q->count(),
            'por_unidade' => $porUnidade->get()->all(),
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function equipamentosEnergia(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('energia_equipamentos_consumo')) {
            return ['equipamentos' => []];
        }

        $q = DB::table('energia_equipamentos_consumo as e')
            ->leftJoin('unidades as u', 'e.unidade_id', '=', 'u.id')
            ->select('e.id', 'e.equipamento_nome', 'e.consumo_kwh', 'e.horas_por_dia', 'u.nome as unidade')
            ->orderBy('e.equipamento_nome')
            ->limit(25);

        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('e.unidade_id', $unidadeId);
        }

        return ['equipamentos' => $q->get()->all()];
    }

    private function patrimonioResumo(SasIaContext $ctx): array
    {
        if (! Schema::hasTable('patrimonios')) {
            return ['total' => 0];
        }

        $q = DB::table('patrimonios');
        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('unidade_id', $uid);
        }

        $porStatus = (clone $q)->select('situacao', DB::raw('COUNT(*) as qtd'))->groupBy('situacao')->pluck('qtd', 'situacao');

        return [
            'total' => (int) $q->count(),
            'valor_total_compra' => round((float) (clone $q)->sum('valor_compra'), 2),
            'por_situacao' => $porStatus,
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function patrimonioManutencoes(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('patrimonio_manutencoes')) {
            return ['manutencoes' => []];
        }

        $limite = min(20, max(1, (int) ($args['limite'] ?? 10)));
        $q = DB::table('patrimonio_manutencoes as mn')
            ->leftJoin('patrimonios as p', 'mn.patrimonio_id', '=', 'p.id')
            ->select('mn.id', 'p.nome as patrimonio', 'mn.tipo_manutencao', 'mn.data_manutencao', 'mn.custo', 'mn.proxima_manutencao')
            ->orderByDesc('mn.data_manutencao')
            ->limit($limite);

        if ($uid = $ctx->unidadeEfetiva()) {
            $q->where('p.unidade_id', $uid);
        }

        return ['manutencoes' => $q->get()->all()];
    }

    private function investimentoResumo(SasIaContext $ctx): array
    {
        $out = ['reservas' => 0, 'carteira' => 0, 'resgates' => 0, 'valor_carteira' => 0.0];

        if (Schema::hasTable('investimento_reservas')) {
            $out['reservas'] = (int) DB::table('investimento_reservas')->count();
        }
        if (Schema::hasTable('investimento_carteira')) {
            $out['carteira'] = (int) DB::table('investimento_carteira')->count();
            $out['valor_carteira'] = round((float) DB::table('investimento_carteira')->sum('valor_aplicado'), 2);
        }
        if (Schema::hasTable('investimento_resgates')) {
            $out['resgates'] = (int) DB::table('investimento_resgates')->count();
        }

        return $out;
    }

    /** @param  array<string, mixed>  $args */
    private function kanbanResumo(SasIaContext $ctx, array $args): array
    {
        if (! Schema::hasTable('kanban_tasks')) {
            return ['tarefas' => 0];
        }

        $q = DB::table('kanban_tasks');
        $unidadeId = isset($args['unidade_id']) ? (int) $args['unidade_id'] : $ctx->unidadeEfetiva();
        if ($unidadeId) {
            $q->where('unidade_id', $unidadeId);
        }

        $porStatus = (clone $q)->select('status', DB::raw('COUNT(*) as qtd'))->groupBy('status')->pluck('qtd', 'status');

        return ['total' => (int) $q->count(), 'por_status' => $porStatus];
    }

    /** @param  array<string, mixed>  $args */
    private function manualDocumentacao(array $args): array
    {
        $consulta = trim((string) ($args['consulta'] ?? ''));

        return $this->documentService->buscarParaIa($consulta);
    }
}
