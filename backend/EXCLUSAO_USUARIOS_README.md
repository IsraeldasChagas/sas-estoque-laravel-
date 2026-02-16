# Exclusão Segura de Usuários - Documentação

## 📋 Resumo da Implementação

Sistema de exclusão segura de usuários implementado com todas as regras de segurança solicitadas.

## ✅ Funcionalidades Implementadas

### 1. **Apenas ADMIN pode excluir**
- Verificação: `perfil === 'ADMIN'`
- Retorna 403 se não for ADMIN

### 2. **Impedir excluir a si mesmo**
- Verifica se `id_alvo == auth()->id()`
- Retorna erro se tentar excluir a si mesmo

### 3. **Impedir excluir ADMIN raiz/sistema**
- Bloqueia exclusão do usuário com ID = 1
- Bloqueia exclusão do primeiro ADMIN (menor ID com perfil ADMIN)
- Bloqueia exclusão de usuários com perfil 'SUPERADMIN' (se existir)

### 4. **Transferência automática de movimentações**
- Verifica se existem movimentações: `COUNT(*) FROM movimentacoes WHERE usuario_id = id_alvo`
- Se existir, transfere TODAS para o ADMIN logado: `UPDATE movimentacoes SET usuario_id = auth()->id() WHERE usuario_id = id_alvo`

### 5. **Desativação ao invés de exclusão**
- Preferencialmente DESATIVA: `UPDATE usuarios SET ativo = 0 WHERE id = id_alvo`
- Se a tabela não tiver coluna `ativo`, deleta (compatibilidade)

### 6. **Transação atômica**
- Tudo executado dentro de `DB::transaction()`
- Rollback automático em caso de erro

### 7. **Auditoria (Log)**
- Tabela `logs_usuarios` criada
- Registra: ator_id, alvo_id, acao, qtd_movimentacoes_transferidas, observacoes, created_at

### 8. **Mensagens claras**
- Frontend mostra mensagem de confirmação explicando transferência de movimentações
- Backend retorna mensagem detalhada sobre a operação

## 📁 Arquivos Criados/Modificados

### Migrations
1. `2026_01_15_000001_add_ativo_column_usuarios.php`
   - Adiciona coluna `ativo TINYINT(1) DEFAULT 1` na tabela `usuarios`

2. `2026_01_15_000002_create_logs_usuarios_table.php`
   - Cria tabela `logs_usuarios` para auditoria

### Backend
3. `backend/routes/api.php`
   - Rota `DELETE /usuarios/{id}` completamente reescrita com todas as regras

### Frontend
4. `frontend/app.js`
   - Mensagem de confirmação atualizada
   - Tratamento de resposta melhorado

## 🚀 Como Executar as Migrations

```bash
cd backend
php artisan migrate
```

Isso irá:
- Adicionar coluna `ativo` na tabela `usuarios` (se não existir)
- Criar tabela `logs_usuarios` (se não existir)

## 🧪 Como Testar

### Teste 1: Verificar que apenas ADMIN pode excluir
1. Faça login com um usuário que NÃO seja ADMIN
2. Tente excluir um usuário
3. **Esperado**: Erro 403 "Apenas administradores podem excluir usuários"

### Teste 2: Verificar que não pode excluir a si mesmo
1. Faça login com um ADMIN
2. Tente excluir seu próprio usuário
3. **Esperado**: Erro "Você não pode excluir a si mesmo"

### Teste 3: Verificar que não pode excluir ADMIN raiz
1. Faça login com um ADMIN
2. Tente excluir o usuário com ID = 1
3. **Esperado**: Erro "Não é permitido excluir o usuário raiz do sistema (ID: 1)"

### Teste 4: Verificar transferência de movimentações
1. Crie um usuário de teste
2. Crie algumas movimentações associadas a esse usuário
3. Faça login com um ADMIN
4. Exclua o usuário de teste
5. **Esperado**: 
   - Usuário desativado (ativo = 0)
   - Movimentações transferidas para o ADMIN logado
   - Mensagem mostrando quantidade de movimentações transferidas
   - Registro na tabela `logs_usuarios`

### Teste 5: Verificar desativação (não exclusão)
1. Exclua um usuário que tenha movimentações
2. Verifique no banco: `SELECT * FROM usuarios WHERE id = [id_excluido]`
3. **Esperado**: Usuário ainda existe, mas `ativo = 0`

### Teste 6: Verificar log de auditoria
1. Exclua um usuário
2. Verifique: `SELECT * FROM logs_usuarios ORDER BY created_at DESC LIMIT 1`
3. **Esperado**: Registro com ator_id, alvo_id, acao, qtd_movimentacoes_transferidas

## 📊 Estrutura da Tabela logs_usuarios

```sql
CREATE TABLE logs_usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ator_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do usuário que executou a ação',
    alvo_id BIGINT UNSIGNED NOT NULL COMMENT 'ID do usuário alvo da ação',
    acao VARCHAR(50) NOT NULL COMMENT 'Ação executada: DESATIVAR ou DELETE',
    qtd_movimentacoes_transferidas INT DEFAULT 0 COMMENT 'Quantidade de movimentações transferidas',
    observacoes TEXT NULL COMMENT 'Observações adicionais',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ator_id (ator_id),
    INDEX idx_alvo_id (alvo_id),
    INDEX idx_created_at (created_at)
);
```

## ⚠️ Observações Importantes

1. **Não usa FOREIGN_KEY_CHECKS**: A implementação não desabilita verificações de foreign key
2. **Não apaga movimentações**: Movimentações são transferidas, nunca deletadas
3. **Desativação preferencial**: Usuários são desativados (ativo = 0) ao invés de deletados
4. **Transação atômica**: Tudo ou nada - se houver erro, nada é alterado
5. **Log completo**: Todas as operações são registradas na tabela `logs_usuarios`

## 🔍 Verificações no Banco de Dados

### Verificar usuários desativados:
```sql
SELECT * FROM usuarios WHERE ativo = 0;
```

### Verificar movimentações transferidas:
```sql
SELECT * FROM movimentacoes WHERE usuario_id = [id_admin_logado];
```

### Verificar logs de exclusão:
```sql
SELECT * FROM logs_usuarios ORDER BY created_at DESC;
```

## 🐛 Troubleshooting

### Erro: "Column 'ativo' doesn't exist"
**Solução**: Execute a migration: `php artisan migrate`

### Erro: "Table 'logs_usuarios' doesn't exist"
**Solução**: Execute a migration: `php artisan migrate`

### Erro: "Foreign key constraint fails"
**Solução**: A implementação transfere as movimentações antes de desativar, então isso não deve acontecer. Se acontecer, verifique se a transferência está funcionando corretamente.




