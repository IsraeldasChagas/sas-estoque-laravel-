<?php

namespace App\Support\SasIa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto do usuário logado: permissões, unidade e limites diários.
 * O sistema é single-tenant (Grupo Sabor Paraense); escopo por unidade_id do usuário.
 */
final class SasIaContext
{
    public object $usuario;

    /** @var string[] */
    public array $permissoesMenu;

    public ?int $unidadeScope;

    public ?int $unidadeFiltro;

    public function __construct(object $usuario, ?int $unidadeFiltro = null)
    {
        $this->usuario = $usuario;
        $this->permissoesMenu = self::parsePermissoesMenu($usuario);
        $this->unidadeScope = self::resolveUnidadeScope($usuario);
        $this->unidadeFiltro = self::resolveUnidadeFiltro($usuario, $unidadeFiltro);
    }

    public function usuarioId(): int
    {
        return (int) $this->usuario->id;
    }

    public function perfil(): string
    {
        return strtoupper(trim((string) ($this->usuario->perfil ?? '')));
    }

    public function isAdmin(): bool
    {
        return $this->perfil() === 'ADMIN';
    }

    public function isGestor(): bool
    {
        return in_array($this->perfil(), ['ADMIN', 'GERENTE', 'FINANCEIRO', 'ASSISTENTE_ADMINISTRATIVO'], true);
    }

    /** Limite diário de perguntas conforme perfil. */
    public function limiteDiario(): int
    {
        if ($this->isAdmin()) {
            return (int) (config('openai.limit_admin') ?? config('services.openai.limit_admin', 300));
        }
        if ($this->isGestor()) {
            return (int) (config('openai.limit_gestor') ?? config('services.openai.limit_gestor', 100));
        }

        return (int) (config('openai.limit_usuario') ?? config('services.openai.limit_usuario', 20));
    }

    /** Quantas mensagens de usuário foram enviadas hoje. */
    public function perguntasHoje(): int
    {
        if (! Schema::hasTable('ai_messages') || ! Schema::hasTable('ai_conversations')) {
            return 0;
        }

        return (int) DB::table('ai_messages')
            ->join('ai_conversations', 'ai_messages.conversation_id', '=', 'ai_conversations.id')
            ->where('ai_conversations.usuario_id', $this->usuarioId())
            ->where('ai_messages.role', 'user')
            ->whereDate('ai_messages.created_at', today())
            ->count();
    }

    public function podePerguntar(): bool
    {
        return $this->perguntasHoje() < $this->limiteDiario();
    }

    public function restanteHoje(): int
    {
        return max(0, $this->limiteDiario() - $this->perguntasHoje());
    }

    /** Verifica se o usuário pode executar a ferramenta (via módulos do menu). */
    public function podeUsarFerramenta(string $toolName): bool
    {
        if ($toolName === 'consultar_manual_documentacao') {
            return true;
        }

        if ($toolName === 'consultar_logs_recentes') {
            return in_array($this->perfil(), ['ADMIN', 'GERENTE'], true)
                || $this->temModulo('logs');
        }

        if ($toolName === 'consultar_resumo_usuarios') {
            return $this->isAdmin() || $this->temModulo('usuarios');
        }

        $modulos = SasIaToolRegistry::modulosDaFerramenta($toolName);

        return $modulos !== [] && $this->temAlgumModulo($modulos);
    }

    public function temModulo(string $modulo): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if (count($this->permissoesMenu)) {
            return in_array($modulo, $this->permissoesMenu, true);
        }

        return self::moduloPadraoPerfil($this->perfil(), $modulo);
    }

    /** @param string[] $modulos */
    public function temAlgumModulo(array $modulos): bool
    {
        foreach ($modulos as $m) {
            if ($this->temModulo($m)) {
                return true;
            }
        }

        return false;
    }

    /** Unidade efetiva para consultas (filtro explícito ou escopo do usuário). */
    public function unidadeEfetiva(): ?int
    {
        return $this->unidadeFiltro ?? $this->unidadeScope;
    }

    /** @return string[] */
    private static function parsePermissoesMenu(object $usuario): array
    {
        $pm = $usuario->permissoes_menu ?? null;
        if (is_string($pm)) {
            $decoded = json_decode($pm, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($pm) ? $pm : [];
    }

    /** Usuários com unidade fixa só veem dados da própria unidade. */
    private static function resolveUnidadeScope(object $usuario): ?int
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if (in_array($perfil, ['ADMIN', 'GERENTE'], true)) {
            return null;
        }
        $uid = $usuario->unidade_id ?? null;

        return ($uid !== null && $uid !== '') ? (int) $uid : null;
    }

    private static function resolveUnidadeFiltro(object $usuario, ?int $solicitada): ?int
    {
        $perfil = strtoupper(trim((string) ($usuario->perfil ?? '')));
        if ($solicitada === null || $solicitada < 1) {
            return null;
        }
        if (in_array($perfil, ['ADMIN', 'GERENTE'], true)) {
            return $solicitada;
        }
        $scope = self::resolveUnidadeScope($usuario);
        if ($scope !== null && $scope !== $solicitada) {
            return $scope;
        }

        return $solicitada;
    }

    private static function moduloPadraoPerfil(string $perfil, string $modulo): bool
    {
        $padroes = [
            'ADMIN' => true,
            'GERENTE' => in_array($modulo, ['dashboard', 'estoque', 'produtos', 'lotes', 'movimentacoes', 'compras', 'fornecedores', 'fechamento', 'fechamentoDash', 'financeiroDashboard', 'logs', 'sasIa'], true),
            'FINANCEIRO' => in_array($modulo, ['dashboard', 'boletao', 'fechamento', 'financeiroDashboard', 'financeiroDre', 'sasIa'], true),
            'ESTOQUISTA' => in_array($modulo, ['dashboard', 'estoque', 'produtos', 'movimentacoes', 'compras', 'fornecedores', 'sasIa'], true),
        ];
        if ($perfil === 'ADMIN') {
            return true;
        }

        return $padroes[$perfil] ?? in_array($modulo, ['dashboard', 'sasIa'], true);
    }
}
