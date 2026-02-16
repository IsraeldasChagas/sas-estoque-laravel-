# Como debugar o problema de login

## Passos para identificar o problema:

1. **Abra o Console do Navegador:**
   - Pressione F12
   - Vá na aba "Console"

2. **Acesse o sistema:**
   - Abra: http://localhost:8000
   - Digite email e senha
   - Clique em "Entrar no painel"

3. **Observe o Console:**
   - Você verá mensagens começando com "=== INÍCIO DO LOGIN ==="
   - Veja se aparece algum erro em vermelho
   - Copie TODAS as mensagens do console

4. **Verifique a aba Network (Rede):**
   - Na aba "Network" do F12
   - Tente fazer login novamente
   - Procure por uma requisição para "/api/login"
   - Clique nela e veja:
     - Status (deve ser 200 ou 401)
     - Response (resposta do servidor)
     - Headers (cabeçalhos)

## Credenciais para teste:

- **Email:** israel@gruposaborparaense.com.br
- **Senha:** 123456

## O que verificar:

1. Se o servidor Laravel está rodando (porta 5000)
2. Se o servidor Frontend está rodando (porta 8000)
3. Se há erros de CORS no console
4. Se a requisição está sendo feita corretamente

## Comandos para iniciar os servidores:

```powershell
# Terminal 1 - Backend Laravel
cd C:\gruposaborparaense\sas-estoque-laravel\backend
php artisan serve --host=0.0.0.0 --port=5000

# Terminal 2 - Frontend
cd C:\gruposaborparaense\sas-estoque-laravel\frontend
php -S localhost:8000
```



