# 🔍 DIAGNÓSTICO COMPLETO DO PROBLEMA DE LOGIN

## 📋 Passo a Passo para Diagnosticar

### 1. Verifique se os servidores estão rodando:

Abra 2 terminais PowerShell e execute:

**Terminal 1 - Backend:**
```powershell
cd C:\gruposaborparaense\sas-estoque-laravel\backend
php artisan serve --host=0.0.0.0 --port=5000
```

**Terminal 2 - Frontend:**
```powershell
cd C:\gruposaborparaense\sas-estoque-laravel\frontend
php -S localhost:8000
```

### 2. Teste a API diretamente:

Acesse no navegador: **http://localhost:8000/test-login.html**

Este arquivo testa a API de login diretamente, sem passar pelo código principal.

### 3. Teste o sistema principal:

Acesse: **http://localhost:8000**

### 4. Abra o Console do Navegador (F12):

- Vá na aba **Console**
- Tente fazer login
- **Copie TODAS as mensagens** que aparecerem

### 5. Verifique a aba Network (Rede):

- Na aba **Network** do F12
- Tente fazer login
- Procure por uma requisição chamada **"login"**
- Clique nela e veja:
  - **Status** (200, 401, 500, etc.)
  - **Request URL** (deve ser http://localhost:5000/api/login)
  - **Response** (resposta do servidor)
  - **Headers** (cabeçalhos)

## 🔑 Credenciais para Teste:

- **Email:** `israel@gruposaborparaense.com.br`
- **Senha:** `123456`

## ✅ O que deve aparecer no console se estiver funcionando:

```
=== INÍCIO DO LOGIN ===
Email: preenchido
Senha: preenchida
=== DETALHES DA REQUISIÇÃO ===
API_URL: http://localhost:5000
URL completa: http://localhost:5000/api/login
Teste de conexão (health): 200 {"status":"ok","message":"API funcionando"}
Fazendo requisição para: http://localhost:5000/api/login
Resposta recebida: {id: 6, nome: "Israel das chagas", ...}
Login bem-sucedido!
```

## ❌ Possíveis Problemas:

1. **Servidor não está rodando:**
   - Verifique os terminais
   - Veja se há erros nos terminais

2. **Erro de CORS:**
   - Aparece no console: "CORS policy"
   - Solução: Use http://localhost:8000 (não file://)

3. **Erro 401 (Não autorizado):**
   - Senha incorreta
   - Verifique as credenciais

4. **Erro 500 (Erro do servidor):**
   - Verifique os logs do Laravel
   - Veja o terminal do backend

5. **Erro de conexão:**
   - "Failed to fetch"
   - Servidor não está acessível
   - Verifique se o servidor está rodando

## 📝 Informe:

1. O que aparece no console quando você tenta fazer login?
2. O que aparece na aba Network?
3. Há erros nos terminais dos servidores?



