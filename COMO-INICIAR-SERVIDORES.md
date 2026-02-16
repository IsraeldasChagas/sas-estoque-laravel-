# 🚀 COMO INICIAR OS SERVIDORES

## ⚠️ PROBLEMA IDENTIFICADO

O erro `ERR_CONNECTION_REFUSED` significa que o **servidor backend não está rodando**.

## ✅ SOLUÇÃO: Iniciar os Servidores

### Opção 1: Script Automático (Recomendado)

**Windows (CMD):**
```bash
iniciar-servidores.bat
```

**Windows (PowerShell):**
```powershell
.\iniciar-servidores.ps1
```

### Opção 2: Manual (2 Terminais)

**Terminal 1 - Backend:**
```bash
cd backend
php artisan serve --host=0.0.0.0 --port=5000
```

**Terminal 2 - Frontend:**
```bash
cd frontend
php -S localhost:8000
```

## 📋 CONFIGURAÇÃO DAS PORTAS

- **Backend Laravel:** `http://localhost:5000`
- **API:** `http://localhost:5000/api`
- **Frontend:** `http://localhost:8000`

## 🌐 ACESSAR O SISTEMA

**IMPORTANTE:** Acesse via servidor HTTP:
```
http://localhost:8000
```

**❌ NÃO FAÇA ISSO:** Abrir o arquivo `index.html` diretamente (file://)

## 🔍 VERIFICAR SE ESTÁ FUNCIONANDO

1. Abra o navegador em `http://localhost:5000/api/ping`
   - Deve retornar: `{"status":"ok","message":"API Laravel funcionando",...}`

2. Abra o navegador em `http://localhost:8000`
   - Deve mostrar a tela de login

## 🐛 SE AINDA NÃO FUNCIONAR

1. Verifique se as portas 5000 e 8000 estão livres
2. Verifique se o PHP está instalado e no PATH
3. Verifique se o Laravel está instalado (`cd backend && php artisan --version`)
4. Veja os logs do servidor nos terminais

