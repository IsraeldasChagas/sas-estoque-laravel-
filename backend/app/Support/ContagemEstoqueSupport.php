<?php

namespace App\Support;

use App\Services\EntradaEstoqueService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contagem de estoque (inventário físico): rascunho, saldo de referência, contagem (cega ou não),
 * divergências, recontagem, aprovação com ajuste no estoque e encerramento.
 *
 * A diferença de cada produto é a quantidade física menos o saldo do sistema no momento em que
 * ela foi lançada; assim as movimentações feitas durante a contagem já entram na conta.
 */
class ContagemEstoqueSupport
{
    public const SECOES = [
        'contagemDashboard', 'contagemNova', 'contagemRealizar', 'contagemConferencia',
        'contagemAprovacao', 'contagemHistorico', 'contagemConfiguracoes',
    ];

    private const PADRAO_PERFIL = [
        'GERENTE' => ['contagemDashboard', 'contagemNova', 'contagemRealizar', 'contagemConferencia', 'contagemAprovacao', 'contagemHistorico'],
        'ASSISTENTE_ADMINISTRATIVO' => ['contagemDashboard', 'contagemNova', 'contagemRealizar', 'contagemConferencia', 'contagemHistorico'],
        'ESTOQUISTA' => ['contagemDashboard', 'contagemNova', 'contagemRealizar', 'contagemHistorico'],
    ];

    private const PERFIS_TODAS_UNIDADES = ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO', 'FINANCEIRO'];

    private const STATUS_ABERTOS = ['em_contagem', 'em_conferencia'];

    private const UNIDADES_DECIMAIS = ['KG', 'G', 'L', 'ML', 'KL', 'LITRO'];

    private const TIPOS = ['completa', 'parcial', 'rotativa'];

    public static function tabelasOk(): bool
    {
        foreach (['contagem_inventarios', 'contagem_itens', 'contagem_lancamentos', 'contagem_decisoes', 'contagem_auditoria', 'contagem_configuracoes'] as $t) {
            if (! Schema::hasTable($t)) {
                return false;
            }
        }

        return true;
    }

    public static function usuario($uid): ?object
    {
        if (! $uid) {
            return null;
        }

        return DB::table('usuarios')->where('id', (int) $uid)->where('ativo', 1)->first();
    }

    public static function secoes(object $u): array
    {
        $perfil = strtoupper(trim((string) ($u->perfil ?? '')));
        if ($perfil === 'ADMIN') {
            return self::SECOES;
        }
        $pm = $u->permissoes_menu ?? null;
        if (is_string($pm)) {
            $decoded = json_decode($pm, true);
            $pm = is_array($decoded) ? $decoded : null;
        }
        if (is_array($pm) && count($pm)) {
            return array_values(array_intersect(self::SECOES, $pm));
        }

        return self::PADRAO_PERFIL[$perfil] ?? [];
    }

    public static function capacidades(object $u): array
    {
        $s = self::secoes($u);
        $tem = fn (string $sec) => in_array($sec, $s, true);

        return [
            'ver' => count($s) > 0,
            'abrir' => $tem('contagemNova'),
            'contar' => $tem('contagemNova') || $tem('contagemRealizar'),
            'conferir' => $tem('contagemConferencia') || $tem('contagemAprovacao'),
            'aprovar' => $tem('contagemAprovacao'),
            'configurar' => $tem('contagemConfiguracoes'),
        ];
    }

    /** null = todas as unidades. */
    public static function unidadesPermitidas(object $u): ?array
    {
        $perfil = strtoupper(trim((string) ($u->perfil ?? '')));
        if (in_array($perfil, self::PERFIS_TODAS_UNIDADES, true)) {
            return null;
        }
        $uid = (int) ($u->unidade_id ?? 0);

        return $uid > 0 ? [$uid] : null;
    }

    public static function podeUnidade(object $u, int $unidadeId): bool
    {
        $lista = self::unidadesPermitidas($u);

        return $lista === null || in_array($unidadeId, $lista, true);
    }

    public static function aceitaDecimal(?string $unidadeBase): bool
    {
        return in_array(strtoupper(trim((string) $unidadeBase)), self::UNIDADES_DECIMAIS, true);
    }

    public static function config(): array
    {
        $row = DB::table('contagem_configuracoes')->orderBy('id')->first();

        return ['cega_padrao' => (bool) ($row->cega_padrao ?? false)];
    }

    public static function salvarConfig(object $u, bool $cegaPadrao): void
    {
        self::exigir(self::capacidades($u)['configurar'], 'Só quem tem acesso às Configurações da contagem pode alterar isto.');
        $row = DB::table('contagem_configuracoes')->orderBy('id')->first();
        $dados = ['cega_padrao' => $cegaPadrao, 'updated_by' => $u->id, 'updated_at' => now()];
        if ($row) {
            DB::table('contagem_configuracoes')->where('id', $row->id)->update($dados);
        } else {
            DB::table('contagem_configuracoes')->insert($dados + ['created_at' => now()]);
        }
    }

    public static function meta(object $u): array
    {
        $permitidas = self::unidadesPermitidas($u);
        $unidades = DB::table('unidades')
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))
            ->orderBy('nome')
            ->get(['id', 'nome', 'ativo'])
            ->filter(fn ($x) => $x->ativo === null || (int) $x->ativo === 1)
            ->map(fn ($x) => ['id' => (int) $x->id, 'nome' => $x->nome])
            ->values();
        $usuarios = DB::table('usuarios')
            ->where('ativo', 1)
            ->orderBy('nome')
            ->get(['id', 'nome', 'perfil', 'unidade_id'])
            ->map(fn ($x) => ['id' => (int) $x->id, 'nome' => $x->nome, 'perfil' => $x->perfil, 'unidade_id' => $x->unidade_id ? (int) $x->unidade_id : null])
            ->values();
        $categorias = DB::table('produtos')
            ->whereNotNull('categoria')
            ->where('categoria', '<>', '')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria')
            ->map(fn ($c) => trim((string) $c))
            ->unique()
            ->values();

        return [
            'usuario_id' => (int) $u->id,
            'capacidades' => self::capacidades($u),
            'config' => self::config(),
            'unidades' => $unidades,
            'usuarios' => $usuarios,
            'categorias' => $categorias,
        ];
    }

    public static function produtosParaEscolha(object $u, int $unidadeId): array
    {
        self::exigir(self::podeUnidade($u, $unidadeId), 'Esta unidade não está liberada para você.');
        $saldos = self::saldosUnidade($unidadeId);

        return self::queryProdutosAtivos()
            ->orderBy('produtos.nome')
            ->get()
            ->map(function ($p) use ($saldos, $unidadeId) {
                $s = $saldos[(int) $p->id] ?? null;

                return [
                    'id' => (int) $p->id,
                    'nome' => $p->nome,
                    'codigo' => $p->codigo_barras,
                    'categoria' => $p->categoria,
                    'unidade_base' => $p->unidade_base,
                    'na_unidade' => (int) ($p->unidade_id ?? 0) === $unidadeId || ($s && $s['qtd'] > 0),
                ];
            })
            ->values()
            ->all();
    }

    public static function listar(object $u, array $filtros, bool $detalhe): array
    {
        $caps = self::capacidades($u);
        self::exigir($caps['ver'], 'Sem acesso à contagem de estoque.');
        $permitidas = self::unidadesPermitidas($u);

        $q = DB::table('contagem_inventarios')->orderByDesc('id');
        if ($permitidas !== null) {
            $q->whereIn('unidade_id', $permitidas);
        }
        if (! empty($filtros['unidade_id'])) {
            $q->where('unidade_id', (int) $filtros['unidade_id']);
        }
        if (! empty($filtros['status'])) {
            $lista = array_filter(array_map('trim', explode(',', (string) $filtros['status'])));
            if ($lista) {
                $q->whereIn('status', $lista);
            }
        }
        if (! empty($filtros['de'])) {
            $q->whereDate('aberto_em', '>=', $filtros['de']);
        }
        if (! empty($filtros['ate'])) {
            $q->whereDate('aberto_em', '<=', $filtros['ate']);
        }

        return self::montar($q->get(), $u, $caps, $detalhe);
    }

    public static function detalhe(object $u, int $id): array
    {
        $caps = self::capacidades($u);
        self::exigir($caps['ver'], 'Sem acesso à contagem de estoque.');
        $inv = self::inventario($id);
        self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');

        return self::montar(collect([$inv]), $u, $caps, true)[0];
    }

    public static function salvarRascunho(object $u, array $d): int
    {
        $caps = self::capacidades($u);
        self::exigir($caps['abrir'], 'Seu acesso não permite abrir contagem.');

        $unidadeId = (int) ($d['unidade_id'] ?? 0);
        self::exigir($unidadeId > 0 && DB::table('unidades')->where('id', $unidadeId)->exists(), 'Selecione a unidade da contagem.');
        self::exigir(self::podeUnidade($u, $unidadeId), 'Esta unidade não está liberada para você.');

        $tipo = (string) ($d['tipo'] ?? '');
        self::exigir(in_array($tipo, self::TIPOS, true), 'Selecione o tipo de inventário.');
        $produtoIds = array_values(array_unique(array_filter(array_map('intval', (array) ($d['produto_ids'] ?? [])))));
        $categoria = trim((string) ($d['categoria'] ?? ''));
        self::exigir($tipo !== 'parcial' || count($produtoIds) > 0, 'Selecione os produtos da contagem parcial.');
        self::exigir($tipo !== 'rotativa' || $categoria !== '', 'Selecione a categoria da contagem rotativa.');

        $equipe = array_map('intval', (array) ($d['equipe_ids'] ?? []));
        $equipe[] = (int) $u->id;
        $equipe = DB::table('usuarios')->whereIn('id', array_unique($equipe))->where('ativo', 1)->pluck('id')->map(fn ($x) => (int) $x)->values()->all();

        $obs = trim((string) ($d['observacoes'] ?? ''));
        $dados = [
            'unidade_id' => $unidadeId,
            'tipo' => $tipo,
            'cega' => ! empty($d['cega']),
            'categoria' => $tipo === 'rotativa' ? $categoria : null,
            'produto_ids' => $tipo === 'parcial' ? json_encode($produtoIds) : null,
            'equipe_ids' => json_encode($equipe),
            'observacoes' => $obs !== '' ? $obs : null,
            'updated_at' => now(),
        ];

        $id = (int) ($d['id'] ?? 0);
        if ($id > 0) {
            $atual = self::inventario($id);
            self::exigir($atual->status === 'rascunho', 'Só um rascunho pode ser alterado antes de iniciar.');
            self::exigir(self::podeUnidade($u, (int) $atual->unidade_id), 'Este inventário é de outra unidade.');
            DB::table('contagem_inventarios')->where('id', $id)->update($dados);
            self::auditar($id, (int) $u->id, 'rascunho_atualizado', $obs ?: 'Rascunho salvo.');

            return $id;
        }

        return DB::transaction(function () use ($dados, $u, $obs) {
            $id = DB::table('contagem_inventarios')->insertGetId($dados + [
                'numero' => self::proximoNumero(),
                'status' => 'rascunho',
                'aberto_por' => $u->id,
                'aberto_em' => now(),
                'created_at' => now(),
            ]);
            self::auditar($id, (int) $u->id, 'rascunho_criado', $obs ?: 'Rascunho salvo.');

            return $id;
        });
    }

    public static function excluirRascunho(object $u, int $id): void
    {
        $caps = self::capacidades($u);
        $inv = self::inventario($id);
        self::exigir($inv->status === 'rascunho', 'Só rascunho pode ser excluído. Contagem iniciada fica no histórico.');
        self::exigir((int) $inv->aberto_por === (int) $u->id || $caps['conferir'], 'Só quem abriu o rascunho pode excluí-lo.');
        DB::table('contagem_auditoria')->where('inventario_id', $id)->delete();
        DB::table('contagem_inventarios')->where('id', $id)->delete();
    }

    public static function iniciar(object $u, int $id): void
    {
        $caps = self::capacidades($u);
        self::exigir($caps['contar'], 'Seu acesso não permite iniciar contagem.');

        DB::transaction(function () use ($u, $id) {
            $inv = DB::table('contagem_inventarios')->where('id', $id)->lockForUpdate()->first();
            self::exigir((bool) $inv, 'Inventário não encontrado.', 404);
            self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');
            self::exigir($inv->status === 'rascunho', 'Esta contagem já foi iniciada.');
            $outraAberta = DB::table('contagem_inventarios')
                ->where('unidade_id', $inv->unidade_id)
                ->where('id', '<>', $id)
                ->whereIn('status', self::STATUS_ABERTOS)
                ->value('numero');
            self::exigir(! $outraAberta, "Já existe uma contagem aberta nesta unidade ({$outraAberta}). Finalize ou encerre antes de iniciar outra.");

            $produtos = self::escopo($inv);
            self::exigir($produtos->isNotEmpty(), 'Nenhum produto ativo entrou no escopo desta contagem.');

            $saldos = self::saldosUnidade((int) $inv->unidade_id);
            $agora = now();
            $linhas = $produtos->map(function ($p) use ($saldos, $id, $agora) {
                $s = $saldos[(int) $p->id] ?? ['qtd' => 0.0, 'valor' => 0.0];
                $custo = $s['qtd'] > 0.0001 ? $s['valor'] / $s['qtd'] : (float) ($p->custo_medio ?? 0);

                return [
                    'inventario_id' => $id,
                    'produto_id' => (int) $p->id,
                    'saldo_referencia' => round($s['qtd'], 4),
                    'custo_unitario' => round($custo, 4),
                    'status' => 'pendente',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ];
            })->all();
            foreach (array_chunk($linhas, 200) as $chunk) {
                DB::table('contagem_itens')->insert($chunk);
            }

            DB::table('contagem_inventarios')->where('id', $id)->update([
                'status' => 'em_contagem',
                'referencia_em' => $agora,
                'updated_at' => $agora,
            ]);
            self::auditar($id, (int) $u->id, 'iniciado', 'Saldo de referência registrado para ' . count($linhas) . ' produtos.');
        });
    }

    public static function lancar(object $u, int $id, array $d): string
    {
        $caps = self::capacidades($u);
        self::exigir($caps['contar'] || $caps['conferir'], 'Seu acesso não permite lançar quantidade.');

        return DB::transaction(function () use ($u, $caps, $id, $d) {
            $inv = self::inventario($id);
            self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');
            self::exigir($inv->status !== 'encerrado', 'Inventário encerrado não pode ser alterado.');
            self::exigir($inv->status !== 'rascunho', 'Inicie a contagem antes de lançar quantidades.');
            $equipe = self::idsJson($inv->equipe_ids);
            self::exigir(in_array((int) $u->id, $equipe, true) || $caps['conferir'], 'Você não está na equipe desta contagem.');

            $produtoId = (int) ($d['produto_id'] ?? 0);
            $item = DB::table('contagem_itens')->where('inventario_id', $id)->where('produto_id', $produtoId)->lockForUpdate()->first();
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            self::exigir($item && $produto, 'Este produto não faz parte da contagem.');

            $raw = $d['quantidade'] ?? null;
            self::exigir($raw !== null && $raw !== '' && is_numeric(str_replace(',', '.', (string) $raw)), 'Informe a quantidade encontrada. Campo vazio não vale zero.');
            $qtd = round((float) str_replace(',', '.', (string) $raw), 4);
            self::exigir($qtd >= 0, 'A quantidade física não pode ser negativa.');
            if (! self::aceitaDecimal($produto->unidade_base ?? null)) {
                self::exigir(abs($qtd - round($qtd)) < 0.0001, 'Esta unidade de medida não aceita quantidade quebrada.');
                $qtd = round($qtd);
            }

            $recontagem = $item->status === 'aguardando_recontagem';
            self::exigir($inv->status !== 'em_conferencia' || $recontagem, 'A contagem já foi para conferência. Peça uma recontagem para lançar de novo.');
            $justificativa = trim((string) ($d['justificativa'] ?? ''));
            self::exigir(! $recontagem || $justificativa !== '', 'Informe a justificativa da recontagem.');

            $rodadaAtual = (int) DB::table('contagem_lancamentos')->where('item_id', $item->id)->max('rodada');
            $saldoAgora = self::saldo($produtoId, (int) $inv->unidade_id);
            $obs = trim((string) ($d['observacao'] ?? ''));
            DB::table('contagem_lancamentos')->insert([
                'inventario_id' => $id,
                'item_id' => $item->id,
                'rodada' => $recontagem ? $rodadaAtual + 1 : max(1, $rodadaAtual),
                'quantidade' => $qtd,
                'saldo_sistema' => round($saldoAgora, 4),
                'usuario_id' => $u->id,
                'observacao' => $obs !== '' ? $obs : null,
                'justificativa' => $justificativa !== '' ? $justificativa : null,
                'contado_em' => now(),
            ]);
            if ($recontagem) {
                DB::table('contagem_itens')->where('id', $item->id)->update([
                    'status' => self::classificar($qtd - $saldoAgora),
                    'updated_at' => now(),
                ]);
            }
            self::auditar($id, (int) $u->id, $recontagem ? 'recontagem_lancada' : 'contagem_lancada', ($produto->nome ?? 'Produto') . ': ' . self::fmt($qtd));

            return $recontagem ? 'Recontagem registrada sem apagar a contagem anterior.' : 'Quantidade registrada.';
        });
    }

    public static function finalizar(object $u, int $id): void
    {
        $caps = self::capacidades($u);
        self::exigir($caps['contar'], 'Seu acesso não permite finalizar contagem.');

        DB::transaction(function () use ($u, $id) {
            $inv = DB::table('contagem_inventarios')->where('id', $id)->lockForUpdate()->first();
            self::exigir((bool) $inv, 'Inventário não encontrado.', 404);
            self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');
            self::exigir($inv->status === 'em_contagem', 'Só uma contagem em andamento pode ser finalizada.');

            $ultimos = self::ultimosLancamentos([$id]);
            self::exigir(count($ultimos) > 0, 'Conte pelo menos um produto. Produto sem quantidade não será zerado.');

            foreach ($ultimos as $itemId => $l) {
                DB::table('contagem_itens')->where('id', $itemId)->update([
                    'status' => self::classificar((float) $l->quantidade - (float) $l->saldo_sistema),
                    'updated_at' => now(),
                ]);
            }
            DB::table('contagem_inventarios')->where('id', $id)->update(['status' => 'em_conferencia', 'updated_at' => now()]);
            self::auditar($id, (int) $u->id, 'finalizado', 'Contagem enviada para conferência. Itens sem quantidade continuam pendentes.');
        });
    }

    public static function decidir(object $u, int $id, array $d): string
    {
        $caps = self::capacidades($u);
        $decisao = (string) ($d['decisao'] ?? '');
        self::exigir(in_array($decisao, ['aprovado', 'rejeitado', 'recontagem'], true), 'Decisão inválida.');
        self::exigir($decisao === 'recontagem' ? $caps['conferir'] : $caps['aprovar'], $decisao === 'recontagem'
            ? 'Seu acesso não permite pedir recontagem.'
            : 'Só quem tem acesso à Aprovação de ajustes pode aprovar ou rejeitar.');
        $justificativa = trim((string) ($d['justificativa'] ?? ''));
        self::exigir($justificativa !== '', 'Informe a justificativa.');

        return DB::transaction(function () use ($u, $id, $d, $decisao, $justificativa) {
            $inv = self::inventario($id);
            self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');
            self::exigir($inv->status !== 'encerrado', 'Inventário encerrado não pode ser alterado.');
            $produtoId = (int) ($d['produto_id'] ?? 0);
            $item = DB::table('contagem_itens')->where('inventario_id', $id)->where('produto_id', $produtoId)->lockForUpdate()->first();
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            $ultimo = $item ? (self::ultimosLancamentos([$id], [$item->id])[$item->id] ?? null) : null;
            self::exigir($item && $produto && $ultimo, 'Este produto ainda não foi contado.');
            self::exigir($item->status !== 'aprovado', 'Este ajuste já foi executado e não pode ser repetido.');
            self::exigir(! in_array($item->status, ['pendente', 'sem_divergencia'], true), 'Este produto não tem divergência para decidir.');
            $nome = $produto->nome ?? 'Produto';
            $agora = now();

            if ($decisao !== 'aprovado') {
                DB::table('contagem_decisoes')->insert([
                    'inventario_id' => $id,
                    'item_id' => $item->id,
                    'decisao' => $decisao,
                    'usuario_id' => $u->id,
                    'justificativa' => $justificativa,
                    'decidido_em' => $agora,
                ]);
                DB::table('contagem_itens')->where('id', $item->id)->update([
                    'status' => $decisao === 'recontagem' ? 'aguardando_recontagem' : 'rejeitado',
                    'updated_at' => $agora,
                ]);
                self::auditar($id, (int) $u->id, $decisao === 'recontagem' ? 'recontagem_solicitada' : 'ajuste_rejeitado', "{$nome}: {$justificativa}");

                return $decisao === 'recontagem'
                    ? 'Recontagem solicitada. A contagem anterior foi preservada.'
                    : 'Ajuste rejeitado. O estoque não foi alterado.';
            }

            self::exigir($item->status !== 'aguardando_recontagem', 'Aguarde a recontagem antes de aprovar.');
            $diferenca = round((float) $ultimo->quantidade - (float) $ultimo->saldo_sistema, 4);
            $valor = round($diferenca * (float) $item->custo_unitario, 2);
            $obs = "Ajuste do inventário {$inv->numero}. {$justificativa}";
            $movId = null;
            if (abs($diferenca) > 0.0001) {
                $movId = $diferenca > 0
                    ? self::lancarSobra($produto, (int) $inv->unidade_id, $diferenca, (float) $item->custo_unitario, (int) $u->id, $obs)
                    : self::lancarFalta($produto, (int) $inv->unidade_id, abs($diferenca), (int) $u->id, $obs);
            }
            DB::table('contagem_decisoes')->insert([
                'inventario_id' => $id,
                'item_id' => $item->id,
                'decisao' => 'aprovado',
                'usuario_id' => $u->id,
                'justificativa' => $justificativa,
                'diferenca' => $diferenca,
                'valor_financeiro' => $valor,
                'movimentacao_id' => $movId,
                'decidido_em' => $agora,
            ]);
            DB::table('contagem_itens')->where('id', $item->id)->update(['status' => 'aprovado', 'updated_at' => $agora]);
            self::auditar($id, (int) $u->id, 'ajuste_aprovado', "{$nome}: diferença " . self::fmt($diferenca) . '. Movimentação ' . ($movId ? "#{$movId}" : 'não necessária') . '.');

            return 'Ajuste aprovado e lançado no estoque uma única vez.';
        });
    }

    public static function encerrar(object $u, int $id): void
    {
        $caps = self::capacidades($u);
        self::exigir($caps['aprovar'], 'Só quem tem acesso à Aprovação de ajustes pode encerrar o inventário.');

        DB::transaction(function () use ($u, $id) {
            $inv = DB::table('contagem_inventarios')->where('id', $id)->lockForUpdate()->first();
            self::exigir((bool) $inv, 'Inventário não encontrado.', 404);
            self::exigir(self::podeUnidade($u, (int) $inv->unidade_id), 'Este inventário é de outra unidade.');
            self::exigir($inv->status === 'em_conferencia', 'A contagem precisa estar em conferência para ser encerrada.');
            $abertos = DB::table('contagem_itens')
                ->where('inventario_id', $id)
                ->whereIn('status', ['falta', 'sobra', 'aguardando_recontagem'])
                ->count();
            self::exigir($abertos === 0, 'Ainda há divergência sem aprovação, rejeição ou recontagem concluída.');
            DB::table('contagem_inventarios')->where('id', $id)->update([
                'status' => 'encerrado',
                'encerrado_em' => now(),
                'encerrado_por' => $u->id,
                'updated_at' => now(),
            ]);
            self::auditar($id, (int) $u->id, 'encerrado', 'Inventário encerrado. O histórico permanece.');
        });
    }

    // ——— Montagem das respostas ———

    private static function montar(Collection $invs, object $u, array $caps, bool $detalhe): array
    {
        if ($invs->isEmpty()) {
            return [];
        }
        $ids = $invs->pluck('id')->map(fn ($x) => (int) $x)->all();
        $unidades = DB::table('unidades')->whereIn('id', $invs->pluck('unidade_id')->unique()->all())->pluck('nome', 'id');

        $itens = DB::table('contagem_itens as i')
            ->leftJoin('produtos as p', 'p.id', '=', 'i.produto_id')
            ->whereIn('i.inventario_id', $ids)
            ->orderBy('p.nome')
            ->select('i.*', 'p.nome as produto_nome', 'p.codigo_barras', 'p.categoria', 'p.unidade_base', Schema::hasColumn('produtos', 'foto') ? 'p.foto' : DB::raw('NULL as foto'))
            ->get()
            ->groupBy('inventario_id');
        $lancamentos = DB::table('contagem_lancamentos')->whereIn('inventario_id', $ids)->orderBy('contado_em')->orderBy('id')->get()->groupBy('item_id');
        $decisoes = DB::table('contagem_decisoes')->whereIn('inventario_id', $ids)->orderBy('decidido_em')->orderBy('id')->get()->groupBy('item_id');

        $usuarioIds = $invs->pluck('aberto_por')
            ->merge($invs->flatMap(fn ($i) => self::idsJson($i->equipe_ids)))
            ->merge($lancamentos->flatten()->pluck('usuario_id'))
            ->merge($decisoes->flatten()->pluck('usuario_id'))
            ->filter()->unique()->all();
        $nomes = DB::table('usuarios')->whereIn('id', $usuarioIds)->pluck('nome', 'id');
        $auditoria = $detalhe
            ? DB::table('contagem_auditoria')->whereIn('inventario_id', $ids)->orderBy('id')->get()->groupBy('inventario_id')
            : collect();

        return $invs->map(function ($inv) use ($u, $caps, $detalhe, $unidades, $itens, $lancamentos, $decisoes, $nomes, $auditoria) {
            $revelar = ! $inv->cega || $caps['conferir'];
            $equipe = self::idsJson($inv->equipe_ids);
            $linhas = ($itens[$inv->id] ?? collect())->map(function ($it) use ($u, $revelar, $lancamentos, $decisoes, $nomes) {
                $ls = $lancamentos[$it->id] ?? collect();
                $ultimo = $ls->last();
                $meu = $ls->filter(fn ($l) => (int) $l->usuario_id === (int) $u->id)->last();
                $dif = $ultimo ? round((float) $ultimo->quantidade - (float) $ultimo->saldo_sistema, 4) : null;
                $valor = $dif !== null ? round($dif * (float) $it->custo_unitario, 2) : 0.0;
                $decs = $decisoes[$it->id] ?? collect();

                return [
                    'id' => (int) $it->id,
                    'produto_id' => (int) $it->produto_id,
                    'produto_nome' => $it->produto_nome ?? "Produto #{$it->produto_id}",
                    'codigo' => $it->codigo_barras,
                    'categoria' => $it->categoria,
                    'unidade_base' => $it->unidade_base,
                    'foto' => $it->foto,
                    'aceita_decimal' => self::aceitaDecimal($it->unidade_base),
                    'status' => $it->status,
                    'contado' => (bool) $ultimo,
                    'saldo_referencia' => $revelar ? (float) $it->saldo_referencia : null,
                    'custo_unitario' => $revelar ? (float) $it->custo_unitario : null,
                    'sistema' => $revelar ? ($ultimo ? (float) $ultimo->saldo_sistema : (float) $it->saldo_referencia) : null,
                    'fisico' => $revelar && $ultimo ? (float) $ultimo->quantidade : null,
                    'diferenca' => $revelar ? $dif : null,
                    'valor' => $revelar ? $valor : null,
                    'minha_quantidade' => $meu ? (float) $meu->quantidade : null,
                    'ultimo_usuario' => $ultimo ? ($nomes[$ultimo->usuario_id] ?? null) : null,
                    'ultimo_usuario_id' => $ultimo ? (int) $ultimo->usuario_id : null,
                    'contado_em' => $ultimo->contado_em ?? null,
                    'lancamentos' => $ls->map(fn ($l) => [
                        'rodada' => (int) $l->rodada,
                        'quantidade' => $revelar || (int) $l->usuario_id === (int) $u->id ? (float) $l->quantidade : null,
                        'usuario_id' => (int) $l->usuario_id,
                        'usuario' => $nomes[$l->usuario_id] ?? null,
                        'contado_em' => $l->contado_em,
                        'observacao' => $l->observacao,
                        'justificativa' => $l->justificativa,
                    ])->values(),
                    'decisoes' => $decs->map(fn ($x) => [
                        'decisao' => $x->decisao,
                        'usuario' => $nomes[$x->usuario_id] ?? null,
                        'justificativa' => $x->justificativa,
                        'diferenca' => $revelar ? (float) $x->diferenca : null,
                        'valor_financeiro' => $revelar ? (float) $x->valor_financeiro : null,
                        'movimentacao_id' => $x->movimentacao_id ? (int) $x->movimentacao_id : null,
                        'decidido_em' => $x->decidido_em,
                    ])->values(),
                ];
            })->values();

            $contados = $linhas->where('contado', true);
            $base = [
                'id' => (int) $inv->id,
                'numero' => $inv->numero,
                'unidade_id' => (int) $inv->unidade_id,
                'unidade_nome' => $unidades[$inv->unidade_id] ?? "Unidade #{$inv->unidade_id}",
                'status' => $inv->status,
                'tipo' => $inv->tipo,
                'cega' => (bool) $inv->cega,
                'categoria' => $inv->categoria,
                'produto_ids' => self::idsJson($inv->produto_ids),
                'equipe_ids' => $equipe,
                'equipe' => array_values(array_filter(array_map(fn ($id) => $nomes[$id] ?? null, $equipe))),
                'na_equipe' => in_array((int) $u->id, $equipe, true),
                'observacoes' => $inv->observacoes,
                'aberto_por' => $inv->aberto_por ? (int) $inv->aberto_por : null,
                'aberto_por_nome' => $nomes[$inv->aberto_por] ?? null,
                'aberto_em' => $inv->aberto_em,
                'referencia_em' => $inv->referencia_em,
                'encerrado_em' => $inv->encerrado_em,
                'revelar' => $revelar,
                'total_itens' => $linhas->count(),
                'contados' => $contados->count(),
                'sem_divergencia' => $contados->where('status', 'sem_divergencia')->count(),
                'aguardando_decisao' => $linhas->whereIn('status', ['falta', 'sobra'])->count(),
                'aguardando_recontagem' => $linhas->where('status', 'aguardando_recontagem')->count(),
            ];
            if ($detalhe) {
                $base['itens'] = $linhas;
                $base['auditoria'] = ($auditoria[$inv->id] ?? collect())->map(fn ($a) => [
                    'acao' => $a->acao,
                    'detalhe' => $a->detalhe,
                    'usuario' => $nomes[$a->usuario_id] ?? null,
                    'em' => $a->created_at,
                ])->values();
            }

            return $base;
        })->values()->all();
    }

    // ——— Estoque ———

    private static function lancarSobra(object $produto, int $unidadeId, float $qtd, float $custo, int $usuarioId, string $obs): int
    {
        if ($custo <= 0) {
            $custo = (float) ($produto->custo_medio ?? 0);
        }
        $r = app(EntradaEstoqueService::class)->registrarEntrada([
            'produto_id' => (int) $produto->id,
            'unidade_id' => $unidadeId,
            'quantidade' => $qtd,
            'custo_unitario' => max(0, $custo),
            'usuario_id' => $usuarioId,
            'numero_lote' => 'INV-' . $produto->id . '-' . $unidadeId . '-' . now()->format('YmdHis'),
            'motivo' => 'AJUSTE_INVENTARIO',
            'observacao' => mb_substr($obs, 0, 1000),
            'origem' => 'INVENTARIO',
        ], false);

        return (int) $r['movimentacao_id'];
    }

    private static function lancarFalta(object $produto, int $unidadeId, float $qtd, int $usuarioId, string $obs): int
    {
        $saldo = self::saldo((int) $produto->id, $unidadeId);
        self::exigir(
            $saldo + 0.0001 >= $qtd,
            'O saldo atual (' . self::fmt($saldo) . ') é menor que a falta (' . self::fmt($qtd) . '). Houve saída depois da contagem; peça uma recontagem.'
        );
        $baixa = ProducaoEstoqueSupport::baixarFifo((int) $produto->id, $unidadeId, $qtd, null, true);
        $mov = [
            'produto_id' => (int) $produto->id,
            'lote_id' => $baixa['lote_id'],
            'usuario_id' => $usuarioId,
            'tipo' => 'SAIDA',
            'qtd' => $qtd,
            'unidade' => unidadeGravacaoMovimentacao(normalizarUnidadeMedidaSaida($produto->unidade_base ?? 'UND')),
            'custo_unitario' => $baixa['custo_medio'],
            'data_mov' => now(),
            'motivo' => 'AJUSTE_INVENTARIO',
            'observacao' => mb_substr($obs, 0, 1000),
            'de_unidade_id' => $unidadeId,
            'para_unidade_id' => null,
        ];
        if (Schema::hasColumn('movimentacoes', 'origem')) {
            $mov['origem'] = 'INVENTARIO';
        }

        return (int) DB::table('movimentacoes')->insertGetId($mov);
    }

    private static function saldo(int $produtoId, int $unidadeId): float
    {
        return (float) DB::table('stock_lotes')
            ->where('produto_id', $produtoId)
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->sum('quantidade');
    }

    /** @return array<int, array{qtd: float, valor: float}> */
    private static function saldosUnidade(int $unidadeId): array
    {
        return DB::table('stock_lotes')
            ->where('unidade_id', $unidadeId)
            ->where('quantidade', '>', 0)
            ->groupBy('produto_id')
            ->select('produto_id', DB::raw('SUM(quantidade) as qtd'), DB::raw('SUM(quantidade * COALESCE(custo_unitario, 0)) as valor'))
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->produto_id => ['qtd' => (float) $r->qtd, 'valor' => (float) $r->valor]])
            ->all();
    }

    private static function queryProdutosAtivos()
    {
        $cols = ['produtos.id', 'produtos.nome', 'produtos.codigo_barras', 'produtos.categoria', 'produtos.unidade_base', 'produtos.unidade_id'];
        if (Schema::hasColumn('produtos', 'custo_medio')) {
            $cols[] = 'produtos.custo_medio';
        }

        return DB::table('produtos')
            ->where(fn ($q) => $q->whereNull('produtos.ativo')->orWhere('produtos.ativo', 1))
            ->select($cols);
    }

    private static function escopo(object $inv): Collection
    {
        $unidadeId = (int) $inv->unidade_id;
        $q = self::queryProdutosAtivos();
        if ($inv->tipo === 'parcial') {
            $q->whereIn('produtos.id', self::idsJson($inv->produto_ids));
        } else {
            $q->where(function ($w) use ($unidadeId) {
                $w->where('produtos.unidade_id', $unidadeId)
                    ->orWhereExists(function ($s) use ($unidadeId) {
                        $s->select(DB::raw(1))
                            ->from('stock_lotes')
                            ->whereColumn('stock_lotes.produto_id', 'produtos.id')
                            ->where('stock_lotes.unidade_id', $unidadeId)
                            ->where('stock_lotes.quantidade', '>', 0);
                    });
            });
            if ($inv->tipo === 'rotativa') {
                $q->whereRaw('UPPER(TRIM(produtos.categoria)) = ?', [mb_strtoupper(trim((string) $inv->categoria))]);
            }
        }

        return $q->orderBy('produtos.nome')->get();
    }

    // ——— Utilitários ———

    /** @return array<int, object> último lançamento por item_id */
    private static function ultimosLancamentos(array $inventarioIds, ?array $itemIds = null): array
    {
        $q = DB::table('contagem_lancamentos')->whereIn('inventario_id', $inventarioIds)->orderBy('contado_em')->orderBy('id');
        if ($itemIds !== null) {
            $q->whereIn('item_id', $itemIds);
        }
        $out = [];
        foreach ($q->get() as $l) {
            $out[(int) $l->item_id] = $l;
        }

        return $out;
    }

    private static function classificar(float $diferenca): string
    {
        if (abs($diferenca) <= 0.0001) {
            return 'sem_divergencia';
        }

        return $diferenca < 0 ? 'falta' : 'sobra';
    }

    private static function inventario(int $id): object
    {
        $inv = DB::table('contagem_inventarios')->where('id', $id)->first();
        self::exigir((bool) $inv, 'Inventário não encontrado.', 404);

        return $inv;
    }

    private static function proximoNumero(): string
    {
        $max = 0;
        foreach (DB::table('contagem_inventarios')->pluck('numero') as $n) {
            $v = (int) preg_replace('/\D/', '', (string) $n);
            $max = max($max, $v);
        }

        return 'INV-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function auditar(int $inventarioId, int $usuarioId, string $acao, string $detalhe): void
    {
        DB::table('contagem_auditoria')->insert([
            'inventario_id' => $inventarioId,
            'usuario_id' => $usuarioId,
            'acao' => $acao,
            'detalhe' => mb_substr($detalhe, 0, 2000),
            'created_at' => now(),
        ]);
    }

    private static function idsJson($raw): array
    {
        $arr = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($arr) ? array_values(array_map('intval', $arr)) : [];
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 3, ',', '.'), '0'), ',');
    }

    private static function exigir(bool $condicao, string $mensagem, int $status = 422): void
    {
        if (! $condicao) {
            throw new RegraNegocioException($mensagem, $status);
        }
    }
}
