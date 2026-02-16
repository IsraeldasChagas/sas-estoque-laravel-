# Como Testar o Frontend

## Opção 1: Usar o Script Automático (Recomendado)

### No Windows:
1. Abra o PowerShell ou CMD
2. Navegue até a pasta `frontend`:
   ```powershell
   cd C:\gruposaborparaense\sas-estoque-laravel\frontend
   ```
3. Execute um dos scripts:
   - **PowerShell**: `.\test-server.ps1`
   - **CMD**: `test-server.bat`

4. Abra o navegador e acesse: **http://localhost:8000**

---

## Opção 2: Comando Manual

### No PowerShell:
```powershell
cd C:\gruposaborparaense\sas-estoque-laravel\frontend
php -S localhost:8000
```

### No CMD:
```cmd
cd C:\gruposaborparaense\sas-estoque-laravel\frontend
php -S localhost:8000
```

Depois acesse: **http://localhost:8000**

---

## Opção 3: Usar Python (se tiver instalado)

```powershell
cd C:\gruposaborparaense\sas-estoque-laravel\frontend
python -m http.server 8000
```

---

## ⚠️ Importante

1. **Backend precisa estar rodando**: O frontend se conecta ao backend em `http://186.209.113.112:5000`
   - Se o backend estiver em outro endereço, edite o arquivo `config.js`

2. **Teste a navegação**: 
   - Faça login
   - Clique nos menus laterais
   - Cada tela deve ser carregada do seu arquivo separado

3. **Verifique o console do navegador** (F12):
   - Se houver erros de carregamento de páginas, verifique se os arquivos estão nos caminhos corretos

---

## Estrutura de Arquivos

As telas estão em:
- `src/pages/dashboard/index.html`
- `src/pages/produtos/index.html`
- `src/pages/compras/index.html`
- etc.

Cada tela é carregada dinamicamente pelo router quando você clica nos menus.



