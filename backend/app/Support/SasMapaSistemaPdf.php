<?php

namespace App\Support;

/**
 * Gera documentação UML / mapa de módulos do SAS-Estoque em HTML para PDF.
 */
final class SasMapaSistemaPdf
{
    public static function renderHtml(): string
    {
        $modulos = self::modulos();
        $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $css = self::css();

        $body = '<div class="cover">
            <h1>SAS-Estoque</h1>
            <p class="sub">Grupo Sabor Paraense — Mapa completo do sistema</p>
            <p class="meta">Documentação UML por módulo · '.date('d/m/Y H:i').'</p>
            <p class="meta">Stack: Laravel API + SPA (index.html / app.js)</p>
        </div>';

        $body .= '<div class="page-break"></div><h2>Índice de módulos</h2><ol class="indice">';
        foreach ($modulos as $i => $m) {
            $body .= '<li><strong>'.$h($m['grupo']).'</strong> — '.$h($m['nome']).'</li>';
        }
        $body .= '</ol>';

        $body .= '<div class="page-break"></div>';
        $body .= self::diagramaArquitetura($h);

        $body .= '<div class="page-break"></div>';
        $body .= self::diagramaIntegracoes($h);

        foreach ($modulos as $m) {
            $body .= '<div class="page-break"></div>';
            $body .= self::renderModulo($m, $h);
        }

        $body .= '<div class="page-break"></div>';
        $body .= self::tabelasBanco($h);

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"/><title>SAS-Estoque — Mapa UML</title>'
            .'<style>'.$css.'</style></head><body>'.$body.'</body></html>';
    }

    private static function renderModulo(array $m, callable $h): string
    {
        $html = '<div class="modulo">';
        $html .= '<div class="modulo-header"><span class="grupo">'.$h($m['grupo']).'</span>';
        $html .= '<h2>'.$h($m['nome']).'</h2></div>';
        $html .= '<p class="desc">'.$h($m['descricao']).'</p>';

        $html .= '<h3>Telas (Frontend)</h3><ul>';
        foreach ($m['telas'] as $t) {
            $html .= '<li><code>'.$h($t['id']).'</code> — '.$h($t['label']).'</li>';
        }
        $html .= '</ul>';

        $html .= '<h3>Diagrama de classes (entidades)</h3>';
        $html .= self::classDiagram($m['entidades'], $h);

        $html .= '<h3>Casos de uso</h3><ul class="casos">';
        foreach ($m['casos_uso'] as $c) {
            $html .= '<li>'.$h($c).'</li>';
        }
        $html .= '</ul>';

        $html .= '<h3>API principal</h3><table class="api"><thead><tr><th>Método</th><th>Rota</th><th>Descrição</th></tr></thead><tbody>';
        foreach ($m['api'] as $a) {
            $html .= '<tr><td>'.$h($a[0]).'</td><td><code>'.$h($a[1]).'</code></td><td>'.$h($a[2]).'</td></tr>';
        }
        $html .= '</tbody></table>';

        if (! empty($m['arquivos'])) {
            $html .= '<h3>Arquivos</h3><ul>';
            foreach ($m['arquivos'] as $f) {
                $html .= '<li><code>'.$h($f).'</code></li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function classDiagram(array $entidades, callable $h): string
    {
        $html = '<div class="uml-classes">';
        foreach ($entidades as $e) {
            $html .= '<div class="uml-class"><div class="uml-class-name">'.$h($e['nome']).'</div>';
            if (! empty($e['attrs'])) {
                $html .= '<div class="uml-class-attrs">';
                foreach ($e['attrs'] as $a) {
                    $html .= '<div>'.$h($a).'</div>';
                }
                $html .= '</div>';
            }
            if (! empty($e['rels'])) {
                $html .= '<div class="uml-class-rels">';
                foreach ($e['rels'] as $r) {
                    $html .= '<div>→ '.$h($r).'</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function diagramaArquitetura(callable $h): string
    {
        return '<h2>Arquitetura geral</h2>
        <div class="arch">
            <div class="arch-box arch-fe"><strong>Frontend SPA</strong><br/>index.html · app.js<br/>energia.js · patrimonio.js<br/>investimento.js · rh-rescisao.js</div>
            <div class="arch-arrow">HTTP/JSON ↕</div>
            <div class="arch-box arch-api"><strong>API Laravel</strong><br/>routes/api.php<br/>+ energia · patrimonio · investimento · rh_rescisao</div>
            <div class="arch-arrow">SQL ↕</div>
            <div class="arch-box arch-db"><strong>MySQL</strong><br/>~50 tabelas<br/>Estoque · RH · Financeiro · Patrimônio</div>
        </div>
        <h3>Camadas</h3>
        <table class="api"><thead><tr><th>Camada</th><th>Responsabilidade</th></tr></thead><tbody>
        <tr><td>View (HTML/JS)</td><td>Formulários, tabelas, dashboards, PDF no navegador</td></tr>
        <tr><td>API REST</td><td>CRUD, cálculos, relatórios PDF, upload de arquivos</td></tr>
        <tr><td>Support/Controllers</td><td>Regras de negócio (Rescisão, Patrimônio, etc.)</td></tr>
        <tr><td>Banco</td><td>Persistência, FK entre unidades, funcionários, produtos</td></tr>
        </tbody></table>';
    }

    private static function diagramaIntegracoes(callable $h): string
    {
        return '<h2>Integrações entre módulos</h2>
        <div class="integracao">
            <p><strong>Unidades</strong> → filtro transversal em estoque, RH, financeiro, energia, investimento, patrimônio, kanban</p>
            <p><strong>Funcionários</strong> → proventos, vale/consumo, folha de ponto, rescisão, patrimônio (responsável), usuário do sistema</p>
            <p><strong>Produtos</strong> → lotes, movimentações, lista de compras, fichas técnicas</p>
            <p><strong>Lista de compras</strong> → gera movimentações de entrada no estoque</p>
            <p><strong>Fornecedores</strong> → lotes, movimentações, boletos</p>
            <p><strong>Recrutamento</strong> → cadastro manual de funcionário após aprovação</p>
            <p><strong>Investimento</strong> → reservas vinculam carteira e resgates</p>
        </div>
        <div class="uml-flow">
            <div class="flow-row"><span>Unidades</span><span class="flow-arr">→</span><span>Estoque · RH · Financeiro · Patrimônio</span></div>
            <div class="flow-row"><span>Funcionários</span><span class="flow-arr">→</span><span>Proventos · Rescisão · Folha · Vale</span></div>
            <div class="flow-row"><span>Produtos</span><span class="flow-arr">→</span><span>Lotes · Movimentações · Compras</span></div>
            <div class="flow-row"><span>Compras</span><span class="flow-arr">→</span><span>Movimentações (entrada)</span></div>
        </div>';
    }

    private static function tabelasBanco(callable $h): string
    {
        $grupos = [
            'Estoque' => ['produtos', 'lotes', 'stock_lotes', 'movimentacoes', 'locais', 'fichas_tecnicas', 'fornecedores', 'listas_compras', 'listas_itens'],
            'Cadastros' => ['unidades', 'usuarios'],
            'RH' => ['funcionarios', 'funcionarios_salarios', 'rh_vagas', 'rh_candidatos', 'rh_entrevistas', 'rh_folhas_ponto', 'rh_rescisoes', 'rh_rescisao_cenarios'],
            'Financeiro' => ['boletos', 'alvaras', 'fechamentos_caixa', 'proventos', 'despesas_fixas', 'financeiro_vale_consumo', 'recibos_ajuda_custo'],
            'Investimento' => ['investimento_reservas', 'investimento_carteira', 'investimento_resgates'],
            'Patrimônio' => ['patrimonios', 'patrimonio_categorias', 'patrimonio_movimentacoes', 'patrimonio_manutencoes', 'patrimonio_inventario'],
            'Operacional' => ['kanban_tasks', 'mesas', 'reservas_mesas', 'energia_equipamentos_consumo', 'audit_logs'],
        ];
        $html = '<h2>Tabelas do banco (por domínio)</h2>';
        foreach ($grupos as $nome => $tabs) {
            $html .= '<h3>'.$h($nome).'</h3><p>'.implode(' · ', array_map($h, $tabs)).'</p>';
        }

        return $html;
    }

    /** @return list<array<string,mixed>> */
    private static function modulos(): array
    {
        return [
            self::mod('Sistema', 'Autenticação e conta', 'Login, sessão, minha conta e permissões de menu.',
                [['id' => 'boasVindas', 'label' => 'Boas-vindas'], ['id' => 'minhaConta', 'label' => 'Minha conta']],
                [['nome' => 'Usuario', 'attrs' => ['+ id', '+ nome, email', '+ perfil, ativo', '+ permissoes_menu (JSON)'], 'rels' => ['unidade_id → Unidade']]],
                ['Fazer login', 'Alterar senha', 'Controlar permissões por seção do menu'],
                [['POST', '/login', 'Autenticar'], ['PUT', '/usuarios/me/senha', 'Alterar senha']],
                ['frontend/app.js', 'backend/routes/api.php']),

            self::mod('Estoque', 'Dashboard', 'Visão geral: estoque baixo, perdas, lotes a vencer.',
                [['id' => 'dashboard', 'label' => 'Dashboard']],
                [['nome' => 'Produto', 'attrs' => ['+ estoque_minimo'], 'rels' => ['unidade']], ['nome' => 'Lote', 'attrs' => ['+ validade'], 'rels' => ['produto_id']]],
                ['Ver alertas de estoque', 'Entrada/saída rápida'],
                [['GET', '/estoque-abaixo-minimo', 'Produtos abaixo do mínimo'], ['GET', '/lotes-a-vencer', 'Lotes próximos do vencimento']]),

            self::mod('Estoque', 'Produtos', 'Cadastro de produtos com categoria, unidade base e foto.',
                [['id' => 'produtos', 'label' => 'Produtos']],
                [['nome' => 'Produto', 'attrs' => ['+ nome, categoria', '+ unidade_base', '+ estoque_minimo', '+ ativo'], 'rels' => []]],
                ['CRUD produtos', 'Ativar/desativar', 'Upload foto'],
                [['GET/POST', '/produtos', 'Listar/criar'], ['PUT/DELETE', '/produtos/{id}', 'Editar/excluir']]),

            self::mod('Estoque', 'Ficha técnica', 'Receitas e modo de preparo com exportação PDF.',
                [['id' => 'fechaTecnica', 'label' => 'Ficha técnica']],
                [['nome' => 'FichaTecnica', 'attrs' => ['+ produto_id', '+ ingredientes JSON', '+ modo_preparo'], 'rels' => ['produto']]],
                ['CRUD fichas', 'Gerar PDF'],
                [['GET/POST', '/fichas-tecnicas', 'CRUD'], ['GET', '/fichas-tecnicas/{id}/pdf', 'PDF']]),

            self::mod('Estoque', 'Consulta estoque', 'Saldo por produto e unidade.',
                [['id' => 'estoque', 'label' => 'Consulta Estoque']],
                [['nome' => 'StockLote', 'attrs' => ['+ quantidade'], 'rels' => ['produto', 'unidade', 'lote']]],
                ['Consultar saldo', 'Filtrar por unidade'],
                [['GET', '/estoque/resumo', 'Resumo'], ['GET', '/produtos/{id}/estoque', 'Detalhe']]),

            self::mod('Estoque', 'Lotes', 'Controle de lotes, validade e etiquetas.',
                [['id' => 'lotes', 'label' => 'Lotes']],
                [['nome' => 'Lote', 'attrs' => ['+ codigo', '+ validade', '+ fornecedor_id'], 'rels' => ['produto', 'unidade']]],
                ['CRUD lotes', 'Imprimir etiqueta PDF'],
                [['GET/POST', '/lotes', 'CRUD'], ['GET', '/lotes/{id}/etiqueta.pdf', 'Etiqueta']]),

            self::mod('Estoque', 'Locais', 'Locais físicos de armazenamento.',
                [['id' => 'locais', 'label' => 'Locais']],
                [['nome' => 'Local', 'attrs' => ['+ nome, tipo'], 'rels' => ['unidade_id']]],
                ['CRUD locais'],
                [['GET/POST/PUT', '/locais', 'CRUD']]),

            self::mod('Estoque', 'Movimentações', 'Entradas, saídas, perdas e produção.',
                [['id' => 'movimentacoes', 'label' => 'Movimentações']],
                [['nome' => 'Movimentacao', 'attrs' => ['+ tipo, quantidade', '+ origem'], 'rels' => ['produto', 'lote', 'usuario']]],
                ['Registrar entrada/saída', 'Auditar histórico', 'Excluir com log'],
                [['GET', '/movimentacoes', 'Listar'], ['POST', '/entrada', 'Entrada'], ['POST', '/saida', 'Saída']]),

            self::mod('Compras', 'Lista de compras', 'Listas por unidade com itens e integração ao estoque.',
                [['id' => 'compras', 'label' => 'Lista de Compras']],
                [['nome' => 'ListaCompra', 'attrs' => ['+ status'], 'rels' => ['unidade', 'itens[]']], ['nome' => 'ListaItem', 'attrs' => ['+ quantidade'], 'rels' => ['produto']]],
                ['Criar lista', 'Finalizar', 'Gerar entrada no estoque', 'Sugestões automáticas'],
                [['GET/POST', '/listas', 'CRUD'], ['POST', '/listas/{id}/estoque', 'Entrada estoque'], ['GET', '/sugestoes-compras', 'Sugestões']]),

            self::mod('Compras', 'Fornecedores', 'Cadastro de fornecedores.',
                [['id' => 'fornecedores', 'label' => 'Fornecedores']],
                [['nome' => 'Fornecedor', 'attrs' => ['+ razao, cnpj', '+ ativo'], 'rels' => []]],
                ['CRUD fornecedores', 'Histórico de compras'],
                [['GET/POST', '/fornecedores', 'CRUD']]),

            self::mod('Cadastros', 'Unidades', 'Filiais/lojas do grupo — entidade central.',
                [['id' => 'unidades', 'label' => 'Unidades']],
                [['nome' => 'Unidade', 'attrs' => ['+ nome, cnpj', '+ endereco'], 'rels' => []]],
                ['CRUD unidades'],
                [['GET/POST/PUT', '/unidades', 'CRUD']]),

            self::mod('Cadastros', 'Usuários', 'Usuários do sistema e permissões.',
                [['id' => 'usuarios', 'label' => 'Usuários']],
                [['nome' => 'Usuario', 'attrs' => ['+ perfil (ADMIN...)', '+ permissoes_menu'], 'rels' => ['unidade_id']]],
                ['CRUD usuários', 'Vincular permissões', 'Inativar (vermelho na lista)'],
                [['GET/POST/PUT', '/usuarios', 'CRUD']]),

            self::mod('Operacional', 'Kanban Administrativo', 'Tarefas administrativas por status.',
                [['id' => 'kanbanAdministrativo', 'label' => 'Kanban']],
                [['nome' => 'KanbanTask', 'attrs' => ['+ titulo, status', '+ prioridade'], 'rels' => ['unidade_id']]],
                ['CRUD tarefas', 'Arrastar status'],
                [['GET/POST', '/kanban-tasks', 'CRUD'], ['PATCH', '/kanban-tasks/{id}/status', 'Status']]),

            self::mod('Operacional', 'Reserva de mesas', 'Reservas e histórico.',
                [['id' => 'reservaMesa', 'label' => 'Mesa'], ['id' => 'historicoReservas', 'label' => 'Histórico']],
                [['nome' => 'Mesa', 'attrs' => ['+ numero, capacidade'], 'rels' => ['unidade']], ['nome' => 'ReservaMesa', 'attrs' => ['+ data, hora', '+ status'], 'rels' => ['mesa']]],
                ['Reservar mesa', 'Cancelar', 'Histórico'],
                [['GET/POST', '/reservas-mesas', 'CRUD'], ['GET/POST', '/mesas', 'Mesas']]),

            self::mod('RH', 'Funcionários', 'Cadastro completo com salário e histórico de reajustes.',
                [['id' => 'funcionarios', 'label' => 'Funcionários']],
                [['nome' => 'Funcionario', 'attrs' => ['+ nome, cpf, cargo', '+ salario_base', '+ status'], 'rels' => ['unidade', 'usuario?']], ['nome' => 'FuncionarioSalario', 'attrs' => ['+ valor, vigencia', '+ motivo'], 'rels' => ['funcionario']]],
                ['CRUD funcionários', 'Registrar aumento salarial', 'Inativar (vermelho)', 'Vincular acesso sistema'],
                [['GET/POST', '/funcionarios', 'CRUD'], ['GET/POST', '/funcionarios/{id}/salarios', 'Histórico salário']]),

            self::mod('RH', 'Folha de ponto', 'Folhas mensais com PDF.',
                [['id' => 'rhFolhaPonto', 'label' => 'Folha de ponto']],
                [['nome' => 'RhFolhaPonto', 'attrs' => ['+ competencia', '+ dados JSON'], 'rels' => ['funcionario', 'unidade']]],
                ['CRUD folhas', 'Gerar PDF'],
                [['GET/POST', '/rh/folhas-ponto', 'CRUD'], ['GET', '/rh/folhas-ponto/{id}/pdf', 'PDF']]),

            self::mod('RH', 'Recrutamento', 'Vagas, candidatos, entrevistas e banco de talentos.',
                [['id' => 'rhDashboard', 'label' => 'Dashboard'], ['id' => 'rhVagas', 'label' => 'Vagas'], ['id' => 'rhCandidatos', 'label' => 'Candidatos'], ['id' => 'rhEntrevistas', 'label' => 'Entrevistas'], ['id' => 'rhBancoTalentos', 'label' => 'Banco talentos']],
                [['nome' => 'RhVaga', 'attrs' => ['+ titulo, slug', '+ status'], 'rels' => ['unidade']], ['nome' => 'RhCandidato', 'attrs' => ['+ nome, status'], 'rels' => ['vaga']], ['nome' => 'RhEntrevista', 'attrs' => ['+ data'], 'rels' => ['candidato']]],
                ['Publicar vaga', 'Candidatura pública', 'Agendar entrevista', 'Aprovar/reprovar'],
                [['GET/POST', '/rh/vagas', 'Vagas'], ['GET/POST', '/rh/candidatos', 'Candidatos'], ['GET/POST', '/rh/entrevistas', 'Entrevistas']], ['frontend/app.js', 'backend/routes/web.php (público)']),

            self::mod('RH', 'Rescisão Trabalhista', 'Cálculo TRCT, simulador, comparativo e PDF.',
                [['id' => 'rhRescisaoDashboard', 'label' => 'Dashboard'], ['id' => 'rhRescisaoSimulador', 'label' => 'Simulador'], ['id' => 'rhRescisaoCalculo', 'label' => 'Cálculo'], ['id' => 'rhRescisaoComparativo', 'label' => 'Comparativo'], ['id' => 'rhRescisaoHistorico', 'label' => 'Histórico'], ['id' => 'rhRescisaoRelatorios', 'label' => 'Relatórios']],
                [['nome' => 'RhRescisao', 'attrs' => ['+ salario_base', '+ tipo_rescisao', '+ total_liquido', '+ detalhes_calculo JSON'], 'rels' => ['funcionario', 'unidade']], ['nome' => 'RhRescisaoCenario', 'attrs' => ['+ tipo_cenario'], 'rels' => ['rescisao']]],
                ['Calcular rescisão TRCT', 'Simular cenários', 'Salvar/confirmar', 'PDF TRCT 3 páginas'],
                [['POST', '/rh/rescisoes/calcular', 'Cálculo'], ['POST', '/rh/rescisoes/comparar', 'Cenários'], ['GET', '/rh/rescisoes/{id}/pdf', 'PDF TRCT']],
                ['frontend/rh-rescisao.js', 'backend/app/Support/Rh/RhRescisaoCalculo.php', 'backend/routes/rh_rescisao_routes.php']),

            self::mod('RH', 'Relatório RH', 'PDF de contatos dos funcionários.',
                [['id' => 'rhRelatorios', 'label' => 'Relatório']],
                [['nome' => 'Funcionario', 'attrs' => [], 'rels' => []]],
                ['Gerar PDF contatos'],
                [['GET', '/funcionarios/relatorio/contatos.pdf', 'PDF']]),

            self::mod('Financeiro', 'Fechamento de caixa', 'Auditoria e dashboard de fechamentos.',
                [['id' => 'fechamento', 'label' => 'Auditoria'], ['id' => 'fechamentoDash', 'label' => 'Dashboard']],
                [['nome' => 'FechamentoCaixa', 'attrs' => ['+ data, totais', '+ conferido'], 'rels' => ['unidade', 'usuario']]],
                ['Lançar fechamento', 'Dashboard gráficos', 'PDF resumo'],
                [['GET/POST', '/fechamentos-caixa', 'CRUD']]),

            self::mod('Financeiro', 'Boletos', 'Contas a pagar com anexos.',
                [['id' => 'boletao', 'label' => 'Boleto']],
                [['nome' => 'Boleto', 'attrs' => ['+ valor, vencimento', '+ status, pago'], 'rels' => ['fornecedor?']], ['nome' => 'BoletoAnexo', 'rels' => ['boleto']]],
                ['CRUD boletos', 'Anexos', 'Resumo e economia'],
                [['GET/POST', '/boletos', 'CRUD'], ['GET', '/boletos/resumo', 'Resumo']]),

            self::mod('Financeiro', 'Documentos empresa', 'Alvarás e licenças.',
                [['id' => 'alvara', 'label' => 'Documentos empresa']],
                [['nome' => 'Alvara', 'attrs' => ['+ tipo, validade'], 'rels' => ['unidade?']]],
                ['CRUD documentos', 'Upload anexo'],
                [['GET/POST', '/alvaras', 'CRUD']]),

            self::mod('Financeiro', 'Proventos', 'Pagamentos avulsos com workflow e assinatura.',
                [['id' => 'proventos', 'label' => 'Proventos']],
                [['nome' => 'Provento', 'attrs' => ['+ valor, tipo', '+ status workflow'], 'rels' => ['funcionario', 'unidade']], ['nome' => 'ProventoAssinatura', 'rels' => ['provento']]],
                ['Criar provento', 'Autorizar', 'Assinar', 'Finalizar', 'PDF recibo'],
                [['GET/POST', '/proventos', 'CRUD'], ['GET', '/proventos/{id}/recibo.pdf', 'PDF']]),

            self::mod('Financeiro', 'Despesas fixas', 'Despesas recorrentes por categoria.',
                [['id' => 'despesasFixas', 'label' => 'Despesas fixas']],
                [['nome' => 'DespesaFixa', 'attrs' => ['+ valor, dia_venc'], 'rels' => ['categoria', 'unidade?']]],
                ['CRUD despesas', 'Categorias'],
                [['GET/POST', '/despesas-fixas', 'CRUD']]),

            self::mod('Financeiro', 'Vale / consumo', 'Controle de vale e consumo por funcionário.',
                [['id' => 'valeConsumo', 'label' => 'Vale / consumo']],
                [['nome' => 'ValeConsumo', 'attrs' => ['+ competencia', '+ valor_vale, valor_consumo'], 'rels' => ['funcionario']]],
                ['Lançar vale/consumo', 'Relatórios'],
                [['GET/POST', '/financeiro/vale-consumo', 'CRUD']]),

            self::mod('Financeiro', 'Recibo ajuda de custo', 'Recibos com PDF.',
                [['id' => 'reciboAjuda', 'label' => 'Recibo ajuda de custo']],
                [['nome' => 'ReciboAjuda', 'attrs' => ['+ valor, periodo'], 'rels' => ['funcionario', 'unidade']]],
                ['CRUD recibos', 'PDF'],
                [['GET/POST', '/recibos-ajuda', 'CRUD'], ['GET', '/recibos-ajuda/{id}/pdf', 'PDF']]),

            self::mod('Manutenção', 'Energia', 'Equipamentos, consumo e projeção.',
                [['id' => 'energiaDashboard', 'label' => 'Dashboard'], ['id' => 'energiaEquipamentos', 'label' => 'Equipamentos'], ['id' => 'energiaProjecao', 'label' => 'Projeção'], ['id' => 'energiaRelatorios', 'label' => 'Relatórios']],
                [['nome' => 'EnergiaEquipamento', 'attrs' => ['+ nome, potencia_w', '+ horas_dia'], 'rels' => ['unidade']]],
                ['CRUD equipamentos', 'Dashboard consumo', 'Relatório PDF/CSV'],
                [['GET/POST', '/energia/equipamentos', 'CRUD'], ['GET', '/energia/dashboard', 'Dashboard']],
                ['frontend/energia.js', 'backend/routes/energia_routes.php']),

            self::mod('Investimento', 'Investimentos', 'Reservas, carteira, simulador e resgates.',
                [['id' => 'investimentoDashboard', 'label' => 'Dashboard'], ['id' => 'investimentoReservas', 'label' => 'Reservas'], ['id' => 'investimentoSimulador', 'label' => 'Simulador'], ['id' => 'investimentoCarteira', 'label' => 'Carteira'], ['id' => 'investimentoResgates', 'label' => 'Resgates'], ['id' => 'investimentoRelatorios', 'label' => 'Relatórios']],
                [['nome' => 'InvestimentoReserva', 'attrs' => ['+ meta, valor_atual'], 'rels' => ['unidade']], ['nome' => 'InvestimentoCarteira', 'attrs' => ['+ tipo, valor'], 'rels' => ['reserva?']], ['nome' => 'InvestimentoResgate', 'rels' => ['carteira']]],
                ['Simular investimento', 'CRUD reservas/carteira', 'Registrar resgate'],
                [['GET', '/investimento/dashboard', 'Dashboard'], ['POST', '/investimento/simular', 'Simulador'], ['GET/POST', '/investimento/reservas', 'Reservas']],
                ['frontend/investimento.js', 'backend/routes/investimento_routes.php']),

            self::mod('Patrimônio', 'Patrimônio', 'Bens, movimentações, manutenções e inventário.',
                [['id' => 'patrimonioDashboard', 'label' => 'Dashboard'], ['id' => 'patrimonios', 'label' => 'Patrimônios'], ['id' => 'patrimonioCategorias', 'label' => 'Categorias'], ['id' => 'patrimonioMovimentacoes', 'label' => 'Movimentações'], ['id' => 'patrimonioManutencoes', 'label' => 'Manutenções'], ['id' => 'patrimonioInventario', 'label' => 'Inventário'], ['id' => 'patrimonioRelatorios', 'label' => 'Relatórios'], ['id' => 'patrimonioConfiguracoes', 'label' => 'Configurações']],
                [['nome' => 'Patrimonio', 'attrs' => ['+ descricao, valor', '+ depreciacao, qr_token'], 'rels' => ['categoria', 'unidade', 'funcionario?']], ['nome' => 'PatrimonioInventario', 'attrs' => ['+ status'], 'rels' => ['itens[]']]],
                ['CRUD bens', 'QR code', 'Inventário', 'Relatórios PDF'],
                [['GET/POST', '/patrimonio/patrimonios', 'CRUD'], ['GET/POST', '/patrimonio/inventario', 'Inventário']],
                ['frontend/patrimonio.js', 'backend/routes/patrimonio_routes.php']),

            self::mod('Sistema', 'Logs e auditoria', 'Trilha de ações e logs de proventos.',
                [['id' => 'logs', 'label' => 'Logs e Auditoria'], ['id' => 'relatorios', 'label' => 'Relatórios estoque']],
                [['nome' => 'AuditLog', 'attrs' => ['+ acao, entidade', '+ usuario_id'], 'rels' => []]],
                ['Consultar logs', 'Exportar relatório movimentações'],
                [['GET', '/audit-logs', 'Auditoria'], ['GET', '/movimentacoes', 'Relatório']]),

            self::mod('Sistema', 'Backup', 'Backup e restauração do banco.',
                [],
                [],
                ['Gerar backup', 'Restaurar', 'Merge RH na restauração'],
                [['POST', '/admin/backup', 'Backup'], ['POST', '/admin/restaurar', 'Restaurar']]),
        ];
    }

    private static function mod(string $grupo, string $nome, string $desc, array $telas, array $entidades, array $casos, array $api, array $arquivos = []): array
    {
        return [
            'grupo' => $grupo,
            'nome' => $nome,
            'descricao' => $desc,
            'telas' => $telas,
            'entidades' => $entidades,
            'casos_uso' => $casos,
            'api' => $api,
            'arquivos' => $arquivos,
        ];
    }

    private static function css(): string
    {
        return '
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #263238; line-height: 1.35; }
        h1 { font-size: 22pt; color: #3949ab; margin: 0 0 8px; }
        h2 { font-size: 14pt; color: #3949ab; border-bottom: 2px solid #3949ab; padding-bottom: 4px; margin-top: 0; }
        h3 { font-size: 11pt; color: #455a64; margin: 12px 0 6px; }
        .cover { text-align: center; padding-top: 80px; }
        .cover .sub { font-size: 13pt; color: #607d8b; }
        .cover .meta { font-size: 9pt; color: #90a4ae; }
        .page-break { page-break-after: always; }
        .modulo-header .grupo { font-size: 8pt; text-transform: uppercase; color: #ff7043; letter-spacing: 0.05em; }
        .desc { color: #546e7a; margin-bottom: 10px; }
        .indice li { margin: 3px 0; }
        code { font-size: 8pt; background: #eceff1; padding: 1px 4px; border-radius: 3px; }
        table.api { width: 100%; border-collapse: collapse; font-size: 8pt; margin: 6px 0; }
        table.api th, table.api td { border: 1px solid #cfd8dc; padding: 4px 6px; text-align: left; }
        table.api th { background: #eceff1; }
        .uml-classes { display: block; margin: 8px 0; }
        .uml-class { border: 1px solid #3949ab; border-radius: 4px; margin: 0 8px 8px 0; display: inline-block; vertical-align: top; min-width: 140px; max-width: 200px; font-size: 8pt; }
        .uml-class-name { background: #3949ab; color: #fff; padding: 4px 8px; font-weight: bold; }
        .uml-class-attrs, .uml-class-rels { padding: 4px 8px; border-top: 1px solid #cfd8dc; }
        .uml-class-rels { background: #f5f5f5; font-style: italic; }
        .casos li { margin: 2px 0; }
        .arch { text-align: center; margin: 20px 0; }
        .arch-box { border: 2px solid #3949ab; border-radius: 8px; padding: 12px; margin: 8px auto; max-width: 320px; }
        .arch-fe { background: #e8eaf6; }
        .arch-api { background: #fff3e0; border-color: #ff7043; }
        .arch-db { background: #e8f5e9; border-color: #2e7d32; }
        .arch-arrow { font-size: 14pt; color: #90a4ae; margin: 4px 0; }
        .integracao p { margin: 4px 0; font-size: 9pt; }
        .uml-flow { margin-top: 12px; }
        .flow-row { display: table; width: 100%; margin: 4px 0; font-size: 9pt; }
        .flow-row span { display: table-cell; }
        .flow-arr { width: 30px; text-align: center; color: #3949ab; font-weight: bold; }
        ul { margin: 4px 0; padding-left: 18px; }
        ';
    }
}
