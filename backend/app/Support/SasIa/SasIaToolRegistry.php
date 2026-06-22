<?php

namespace App\Support\SasIa;

/**
 * Catálogo de ferramentas SAS IA — uma (ou mais) por área do menu lateral.
 * Cada ferramenta exige permissão em pelo menos um módulo listado em modulos.
 */
final class SasIaToolRegistry
{
    /** @return array<string, string[]> tool => módulos do menu (permissoes_menu) */
    public static function mapaModulos(): array
    {
        return [
            // Estoque / operacional
            'consultar_resumo_produtos' => ['dashboard', 'estoque', 'produtos'],
            'consultar_produtos_abaixo_estoque_minimo' => ['dashboard', 'estoque', 'produtos'],
            'consultar_produto_por_nome' => ['dashboard', 'estoque', 'produtos'],
            'consultar_estoque_por_unidade' => ['dashboard', 'estoque', 'produtos', 'lotes'],
            'consultar_lotes_proximos_vencer' => ['lotes', 'estoque', 'produtos'],
            'consultar_locais_estoque' => ['locais', 'estoque'],
            'consultar_movimentacoes_recentes' => ['movimentacoes', 'estoque', 'dashboard'],
            'consultar_compras_recentes' => ['compras'],
            'consultar_fornecedores' => ['fornecedores'],
            'consultar_resumo_unidades' => ['unidades', 'dashboard'],
            'consultar_cadastro_geral' => ['unidades', 'fornecedores', 'funcionarios', 'produtos', 'reservaMesa', 'dashboard'],
            'consultar_resumo_usuarios' => ['usuarios'],

            // Financeiro / fechamento
            'consultar_vendas_do_dia' => ['fechamento', 'fechamentoDash', 'financeiroDashboard'],
            'consultar_fechamentos_recentes' => ['fechamento', 'fechamentoDash'],
            'consultar_resumo_financeiro' => ['financeiroDashboard', 'financeiroDre', 'financeiroFluxoCaixa', 'boletao'],
            'consultar_boletos_resumo' => ['boletao', 'financeiroDashboard'],
            'consultar_alvaras_vencendo' => ['alvara'],
            'consultar_proventos_resumo' => ['proventos'],
            'consultar_despesas_fixas_resumo' => ['despesasFixas', 'financeiroDashboard'],
            'consultar_vale_consumo_recente' => ['valeConsumo', 'financeiroDashboard'],
            'consultar_recibos_ajuda_resumo' => ['reciboAjuda'],

            // Reservas
            'consultar_reservas_periodo' => ['reservaMesa', 'historicoReservas'],
            'consultar_mesas_resumo' => ['reservaMesa'],

            // RH
            'consultar_funcionarios_resumo' => ['funcionarios', 'rhDashboard', 'rhRelatorios'],
            'consultar_rh_recrutamento_resumo' => ['rhDashboard', 'rhCandidatos', 'rhVagas', 'rhBancoTalentos'],
            'consultar_vagas_rh' => ['rhVagas', 'rhDashboard', 'rhCandidatos'],
            'consultar_candidatos_rh' => ['rhCandidatos', 'rhBancoTalentos', 'rhDashboard'],
            'consultar_folha_ponto_resumo' => ['rhFolhaPonto', 'funcionarios'],
            'consultar_rescisoes_rh' => ['rhRescisaoDashboard', 'rhRescisaoHistorico', 'rhRescisaoRelatorios', 'funcionarios'],

            // Energia
            'consultar_energia_resumo' => ['energiaDashboard', 'energiaEquipamentos', 'energiaRelatorios'],
            'consultar_equipamentos_energia' => ['energiaEquipamentos', 'energiaDashboard'],

            // Patrimônio
            'consultar_patrimonio_resumo' => ['patrimonioDashboard', 'patrimonios', 'patrimonioRelatorios'],
            'consultar_patrimonio_manutencoes' => ['patrimonioManutencoes', 'patrimonioDashboard'],

            // Investimento
            'consultar_investimento_resumo' => ['investimentoDashboard', 'investimentoCarteira', 'investimentoReservas', 'investimentoRelatorios'],

            // Admin / sistema
            'consultar_kanban_resumo' => ['kanbanAdministrativo', 'dashboard'],
            'consultar_logs_recentes' => ['logs'],
            'consultar_manual_documentacao' => ['sasIa', 'iaAssistente'],
        ];
    }

    /** @return string[] */
    public static function modulosDaFerramenta(string $toolName): array
    {
        return self::mapaModulos()[$toolName] ?? [];
    }

    /** Definições JSON Schema para a OpenAI. */
    public static function definitions(): array
    {
        return [
            // —— Estoque ——
            self::t('consultar_resumo_produtos', 'Total de produtos cadastrados (ativos), com estoque e zerados.', []),
            self::t('consultar_produtos_abaixo_estoque_minimo', 'Produtos com saldo abaixo do estoque mínimo.', []),
            self::t('consultar_produto_por_nome', 'Busca produto pelo nome.', ['nome' => ['type' => 'string', 'description' => 'Nome ou parte do nome']], ['nome']),
            self::t('consultar_estoque_por_unidade', 'Saldo em lotes por unidade (produtos distintos com saldo).', ['unidade_id' => ['type' => 'integer', 'description' => 'ID da unidade (opcional)']]),
            self::t('consultar_lotes_proximos_vencer', 'Lotes com validade próxima ou vencidos.', ['dias' => ['type' => 'integer', 'description' => 'Dias à frente (padrão 15)']]),
            self::t('consultar_locais_estoque', 'Locais de armazenamento cadastrados.', ['unidade_id' => ['type' => 'integer', 'description' => 'Filtrar unidade (opcional)']]),
            self::t('consultar_movimentacoes_recentes', 'Últimas movimentações de estoque.', ['dias' => ['type' => 'integer', 'description' => 'Dias (padrão 7)'], 'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),
            self::t('consultar_compras_recentes', 'Listas de compras recentes.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),
            self::t('consultar_fornecedores', 'Fornecedores ativos com CNPJ/CPF, contato e endereço.', ['busca' => ['type' => 'string', 'description' => 'Filtrar nome ou CNPJ (opcional)']]),
            self::t('consultar_resumo_unidades', 'Unidades/empresas cadastradas com CNPJ, endereço, telefone e e-mail. Use para CNPJ das lojas.', ['busca' => ['type' => 'string', 'description' => 'Nome ou CNPJ (opcional)']]),
            self::t('consultar_resumo_usuarios', 'Usuários do sistema (quantidade por perfil).', []),

            // —— Financeiro ——
            self::t('consultar_vendas_do_dia', 'Faturamento do fechamento de caixa em uma data.', ['data' => ['type' => 'string', 'description' => 'YYYY-MM-DD'], 'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),
            self::t('consultar_fechamentos_recentes', 'Fechamentos de caixa recentes.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),
            self::t('consultar_resumo_financeiro', 'Resumo financeiro do período (faturamento, entradas, saídas, CMV).', ['de' => ['type' => 'string', 'description' => 'Início YYYY-MM-DD'], 'ate' => ['type' => 'string', 'description' => 'Fim YYYY-MM-DD'], 'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),
            self::t('consultar_boletos_resumo', 'Boletos/contas a pagar: pendentes, vencidos e pagos.', ['unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),
            self::t('consultar_alvaras_vencendo', 'Alvarás e licenças próximos do vencimento.', ['dias' => ['type' => 'integer', 'description' => 'Dias à frente (padrão 60)']]),
            self::t('consultar_proventos_resumo', 'Proventos/vales do mês (por status).', ['mes' => ['type' => 'string', 'description' => 'YYYY-MM (opcional, padrão mês atual)']]),
            self::t('consultar_despesas_fixas_resumo', 'Despesas fixas cadastradas e totais.', []),
            self::t('consultar_vale_consumo_recente', 'Lançamentos recentes de vale/consumo.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),
            self::t('consultar_recibos_ajuda_resumo', 'Recibos de ajuda de custo recentes.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),

            // —— Reservas ——
            self::t('consultar_reservas_periodo', 'Reservas de mesa ativas (pendente/confirmada). Sem data: busca de hoje até 30 dias à frente. Use sempre para perguntas sobre reserva de mesa.', [
                'data' => ['type' => 'string', 'description' => 'Dia único YYYY-MM-DD (opcional)'],
                'de' => ['type' => 'string', 'description' => 'Início do período YYYY-MM-DD (opcional)'],
                'ate' => ['type' => 'string', 'description' => 'Fim do período YYYY-MM-DD (opcional)'],
                'unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)'],
                'busca_cliente' => ['type' => 'string', 'description' => 'Nome do cliente (opcional)'],
            ]),
            self::t('consultar_mesas_resumo', 'Mesas cadastradas e status (livre, reservada, ocupada).', ['unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),

            // —— RH ——
            self::t('consultar_funcionarios_resumo', 'Funcionários ativos, por unidade e tipo de vínculo.', []),
            self::t('consultar_rh_recrutamento_resumo', 'Totais do Dashboard RH (vagas, candidatos, entrevistas, aprovados). Use para "quantos candidatos/currículos no recrutamento" — retorna total_candidatos igual ao card do Dashboard.', []),
            self::t('consultar_vagas_rh', 'Vagas de emprego abertas ou pausadas.', ['status' => ['type' => 'string', 'description' => 'aberta, pausada ou encerrada (opcional)']]),
            self::t('consultar_candidatos_rh', 'Lista recente de candidatos (amostra). Para o TOTAL use consultar_rh_recrutamento_resumo.', [
                'limite' => ['type' => 'integer', 'description' => 'Máximo na amostra (padrão 15)'],
                'status' => ['type' => 'string', 'description' => 'Filtrar status (opcional)'],
            ]),
            self::t('consultar_folha_ponto_resumo', 'Folhas de ponto registradas (quantidade recente).', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),
            self::t('consultar_rescisoes_rh', 'Rescisões trabalhistas calculadas recentemente.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),

            // —— Energia ——
            self::t('consultar_energia_resumo', 'Resumo de consumo energético por unidade.', []),
            self::t('consultar_equipamentos_energia', 'Equipamentos cadastrados no módulo de energia.', ['unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),

            // —— Patrimônio ——
            self::t('consultar_patrimonio_resumo', 'Patrimônios cadastrados, valor total e por status.', []),
            self::t('consultar_patrimonio_manutencoes', 'Manutenções de patrimônio pendentes ou recentes.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 10)']]),

            // —— Investimento ——
            self::t('consultar_investimento_resumo', 'Reservas, carteira e resgates do módulo investimento.', []),

            // —— Admin ——
            self::t('consultar_kanban_resumo', 'Tarefas do kanban administrativo por status.', ['unidade_id' => ['type' => 'integer', 'description' => 'Unidade (opcional)']]),
            self::t('consultar_logs_recentes', 'Logs de auditoria recentes.', ['limite' => ['type' => 'integer', 'description' => 'Máximo (padrão 20)']]),
            self::t('consultar_manual_documentacao', 'Manual e documentos internos do sistema.', ['consulta' => ['type' => 'string', 'description' => 'Termo ou pergunta']], ['consulta']),
            self::t('consultar_cadastro_geral', 'Busca cadastros do sistema por nome, CNPJ ou CPF (unidades, fornecedores, funcionários, produtos).', [
                'busca' => ['type' => 'string', 'description' => 'Nome, CNPJ, CPF ou parte do texto'],
                'tipo' => ['type' => 'string', 'description' => 'unidade, fornecedor, funcionario, produto ou todos (padrão todos)'],
            ], ['busca']),
        ];
    }

    /** @param  array<string, array<string, mixed>>  $props
     * @param  string[]  $required
     */
    private static function t(string $name, string $description, array $props, array $required = []): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => $props === [] ? (object) [] : $props,
        ];
        if ($required !== []) {
            $parameters['required'] = $required;
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => $parameters,
            ],
        ];
    }
}
