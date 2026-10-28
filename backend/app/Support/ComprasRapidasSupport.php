<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de compras rápida: nome, produtos digitados (com ou sem cadastro), quantidade, tipo e observação.
 * Cabeçalho com logo da unidade, usuário, data e hora; situação aberta/feita.
 */
class ComprasRapidasSupport
{
    public const TIPOS = ['KG', 'G', 'UN', 'CAIXA', 'PACOTE', 'LITRO', 'ML', 'SACHE', 'FARDO', 'SACO'];

    private const PERFIS_PADRAO = ['ADMIN', 'GERENTE', 'ESTOQUISTA', 'COZINHA', 'BAR', 'ASSISTENTE_ADMINISTRATIVO'];

    private const PERFIS_TODAS_UNIDADES = ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO', 'FINANCEIRO'];

    private const PERFIS_GESTAO = ['ADMIN', 'GERENTE', 'ASSISTENTE_ADMINISTRATIVO'];

    public static function tabelasOk(): bool
    {
        return Schema::hasTable('compras_rapidas') && Schema::hasTable('compras_rapidas_itens');
    }

    public static function usuario($uid): ?object
    {
        return $uid ? DB::table('usuarios')->where('id', (int) $uid)->where('ativo', 1)->first() : null;
    }

    private static function perfil(object $u): string
    {
        return strtoupper(trim((string) ($u->perfil ?? '')));
    }

    public static function podeUsar(object $u): bool
    {
        if (self::perfil($u) === 'ADMIN') {
            return true;
        }
        $pm = $u->permissoes_menu ?? null;
        if (is_string($pm)) {
            $decoded = json_decode($pm, true);
            $pm = is_array($decoded) ? $decoded : null;
        }
        if (is_array($pm) && count($pm)) {
            return in_array('comprasRapidas', $pm, true) || in_array('compras', $pm, true);
        }

        return in_array(self::perfil($u), self::PERFIS_PADRAO, true);
    }

    /** null = todas as unidades. */
    private static function unidadesPermitidas(object $u): ?array
    {
        if (in_array(self::perfil($u), self::PERFIS_TODAS_UNIDADES, true)) {
            return null;
        }
        $uid = (int) ($u->unidade_id ?? 0);

        return $uid > 0 ? [$uid] : null;
    }

    private static function podeGerir(object $u, object $lista): bool
    {
        return in_array(self::perfil($u), self::PERFIS_GESTAO, true) || (int) $lista->usuario_id === (int) $u->id;
    }

    public static function meta(object $u): array
    {
        self::exigir(self::podeUsar($u), 'Sem acesso à lista de compras.', 403);
        $permitidas = self::unidadesPermitidas($u);
        $unidades = DB::table('unidades')
            ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas))
            ->orderBy('nome')
            ->get()
            ->filter(fn ($x) => ! isset($x->ativo) || $x->ativo === null || (int) $x->ativo === 1)
            ->map(fn ($x) => ['id' => (int) $x->id, 'nome' => $x->nome, 'logo' => $x->logo ?? null])
            ->values();
        $produtos = DB::table('produtos')
            ->where(fn ($q) => $q->whereNull('ativo')->orWhere('ativo', 1))
            ->orderBy('nome')
            ->get(['id', 'nome', 'unidade_base'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'nome' => $p->nome, 'unidade_base' => $p->unidade_base])
            ->values();

        return [
            'usuario' => ['id' => (int) $u->id, 'nome' => $u->nome, 'unidade_id' => $u->unidade_id ? (int) $u->unidade_id : null],
            'unidades' => $unidades,
            'produtos' => $produtos,
            'tipos' => self::TIPOS,
        ];
    }

    public static function listar(object $u, array $f): array
    {
        self::exigir(self::podeUsar($u), 'Sem acesso à lista de compras.', 403);
        $permitidas = self::unidadesPermitidas($u);
        $q = DB::table('compras_rapidas')->orderByDesc('id');
        if ($permitidas !== null) {
            $q->where(fn ($w) => $w->whereIn('unidade_id', $permitidas)->orWhere('usuario_id', $u->id));
        }
        if (! empty($f['unidade_id'])) {
            $q->where('unidade_id', (int) $f['unidade_id']);
        }
        $status = $f['status'] ?? 'aberta';
        if (in_array($status, ['aberta', 'feita'], true)) {
            $q->where('status', $status);
        }

        return self::montar($q->limit(300)->get(), $u);
    }

    public static function salvar(object $u, array $d, ?int $id = null): array
    {
        self::exigir(self::podeUsar($u), 'Sem acesso à lista de compras.', 403);
        $atual = $id ? DB::table('compras_rapidas')->where('id', $id)->first() : null;
        if ($id) {
            self::exigir((bool) $atual, 'Lista não encontrada.', 404);
            self::exigir(self::podeGerir($u, $atual), 'Só quem criou a lista (ou a gestão) pode alterá-la.', 403);
        }

        $dados = ['updated_at' => now()];
        if (! $id || array_key_exists('nome', $d)) {
            $nome = trim((string) ($d['nome'] ?? ''));
            self::exigir($nome !== '', 'Informe o nome da lista.');
            $dados['nome'] = mb_substr($nome, 0, 160);
        }
        if (! $id || array_key_exists('unidade_id', $d)) {
            $unidadeId = (int) ($d['unidade_id'] ?? 0) ?: (int) ($u->unidade_id ?? 0);
            $permitidas = self::unidadesPermitidas($u);
            self::exigir($unidadeId > 0, 'Selecione a unidade.');
            self::exigir($permitidas === null || in_array($unidadeId, $permitidas, true), 'Esta unidade não está liberada para você.', 403);
            $dados['unidade_id'] = $unidadeId;
        }
        if (array_key_exists('status', $d)) {
            $status = (string) $d['status'];
            self::exigir(in_array($status, ['aberta', 'feita'], true), 'Situação inválida.');
            $dados['status'] = $status;
            $dados['feita_em'] = $status === 'feita' ? now() : null;
        }

        $itens = null;
        if (! $id || array_key_exists('itens', $d)) {
            $itens = self::normalizarItens((array) ($d['itens'] ?? []));
            self::exigir(count($itens) > 0, 'Informe pelo menos um produto.');
        }

        $listaId = DB::transaction(function () use ($id, $dados, $itens, $u) {
            if ($id) {
                DB::table('compras_rapidas')->where('id', $id)->update($dados);
            } else {
                $id = DB::table('compras_rapidas')->insertGetId($dados + [
                    'usuario_id' => $u->id,
                    'status' => 'aberta',
                    'created_at' => now(),
                ]);
            }
            if ($itens !== null) {
                DB::table('compras_rapidas_itens')->where('lista_id', $id)->delete();
                foreach ($itens as $i => $item) {
                    DB::table('compras_rapidas_itens')->insert($item + ['lista_id' => $id, 'ordem' => $i]);
                }
            }

            return $id;
        });

        return self::montar(DB::table('compras_rapidas')->where('id', $listaId)->get(), $u)[0];
    }

    public static function excluir(object $u, int $id): void
    {
        $lista = DB::table('compras_rapidas')->where('id', $id)->first();
        self::exigir((bool) $lista, 'Lista não encontrada.', 404);
        self::exigir(self::podeGerir($u, $lista), 'Só quem criou a lista (ou a gestão) pode excluí-la.', 403);
        DB::transaction(function () use ($id) {
            DB::table('compras_rapidas_itens')->where('lista_id', $id)->delete();
            DB::table('compras_rapidas')->where('id', $id)->delete();
        });
    }

    /** Logo da unidade embutido (data URL), para PDF e imagem do WhatsApp sem bloqueio de origem. */
    public static function logoDataUrl(?int $unidadeId): ?string
    {
        if (! $unidadeId || ! Schema::hasColumn('unidades', 'logo')) {
            return null;
        }
        $path = DB::table('unidades')->where('id', $unidadeId)->value('logo');
        if (! $path) {
            return null;
        }
        $arquivo = public_path(ltrim((string) $path, '/'));
        if (! is_file($arquivo)) {
            return null;
        }
        $mime = mime_content_type($arquivo) ?: 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($arquivo));
    }

    public static function salvarLogoUnidade(object $u, int $unidadeId, ?UploadedFile $arquivo): ?string
    {
        self::exigir(self::perfil($u) === 'ADMIN' || sasPodeOperarModulo($u, 'unidades'), 'Só quem gerencia unidades pode trocar o logo.', 403);
        self::exigir(Schema::hasColumn('unidades', 'logo'), 'Rode php artisan migrate no servidor para liberar o logo da unidade.', 503);
        $unidade = DB::table('unidades')->where('id', $unidadeId)->first();
        self::exigir((bool) $unidade, 'Unidade não encontrada.', 404);

        $antigo = $unidade->logo ?? null;
        if ($antigo && str_starts_with($antigo, 'uploads/') && is_file(public_path($antigo))) {
            @unlink(public_path($antigo));
        }
        if (! $arquivo) {
            DB::table('unidades')->where('id', $unidadeId)->update(['logo' => null]);

            return null;
        }
        self::exigir($arquivo->isValid() && str_starts_with((string) $arquivo->getMimeType(), 'image/'), 'Envie uma imagem (PNG ou JPG).');
        self::exigir($arquivo->getSize() <= 3 * 1024 * 1024, 'A imagem pode ter no máximo 3 MB.');
        $dir = public_path('uploads/unidades');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $ext = strtolower($arquivo->getClientOriginalExtension() ?: 'png');
        $nome = 'unidade-' . $unidadeId . '-' . time() . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $arquivo->move($dir, $nome);
        $path = 'uploads/unidades/' . $nome;
        DB::table('unidades')->where('id', $unidadeId)->update(['logo' => $path]);

        return $path;
    }

    private static function normalizarItens(array $itens): array
    {
        $out = [];
        foreach ($itens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $nome = trim((string) ($item['produto_nome'] ?? ''));
            if ($nome === '') {
                continue;
            }
            $qtd = (float) str_replace(',', '.', (string) ($item['quantidade'] ?? 0));
            self::exigir($qtd > 0, "Informe a quantidade de {$nome}.");
            $tipo = strtoupper(trim((string) ($item['tipo'] ?? 'UN')));
            $obs = trim((string) ($item['observacao'] ?? ''));
            $out[] = [
                'produto_id' => ! empty($item['produto_id']) ? (int) $item['produto_id'] : null,
                'produto_nome' => mb_substr($nome, 0, 200),
                'quantidade' => round($qtd, 3),
                'tipo' => in_array($tipo, self::TIPOS, true) ? $tipo : 'UN',
                'observacao' => $obs !== '' ? mb_substr($obs, 0, 255) : null,
            ];
        }

        return $out;
    }

    private static function montar($listas, object $u): array
    {
        if ($listas->isEmpty()) {
            return [];
        }
        $ids = $listas->pluck('id')->all();
        $itens = DB::table('compras_rapidas_itens')->whereIn('lista_id', $ids)->orderBy('ordem')->orderBy('id')->get()->groupBy('lista_id');
        $unidades = DB::table('unidades')->whereIn('id', $listas->pluck('unidade_id')->filter()->unique()->all())->get()->keyBy('id');
        $usuarios = DB::table('usuarios')->whereIn('id', $listas->pluck('usuario_id')->filter()->unique()->all())->pluck('nome', 'id');

        return $listas->map(function ($l) use ($itens, $unidades, $usuarios, $u) {
            $unidade = $unidades[$l->unidade_id] ?? null;

            return [
                'id' => (int) $l->id,
                'nome' => $l->nome,
                'status' => $l->status,
                'unidade_id' => $l->unidade_id ? (int) $l->unidade_id : null,
                'unidade_nome' => $unidade->nome ?? 'Sem unidade',
                'unidade_logo' => $unidade->logo ?? null,
                'usuario_id' => $l->usuario_id ? (int) $l->usuario_id : null,
                'usuario_nome' => $usuarios[$l->usuario_id] ?? 'Usuário',
                'pode_gerir' => self::podeGerir($u, $l),
                'created_at' => $l->created_at,
                'updated_at' => $l->updated_at,
                'feita_em' => $l->feita_em,
                'itens' => ($itens[$l->id] ?? collect())->map(fn ($i) => [
                    'id' => (int) $i->id,
                    'produto_id' => $i->produto_id ? (int) $i->produto_id : null,
                    'produto_nome' => $i->produto_nome,
                    'quantidade' => (float) $i->quantidade,
                    'tipo' => $i->tipo,
                    'observacao' => $i->observacao,
                ])->values(),
            ];
        })->values()->all();
    }

    private static function exigir(bool $ok, string $mensagem, int $status = 422): void
    {
        if (! $ok) {
            throw new RegraNegocioException($mensagem, $status);
        }
    }
}
