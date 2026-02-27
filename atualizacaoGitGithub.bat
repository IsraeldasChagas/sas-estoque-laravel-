@echo off
title Atualizacao Git e GitHub - SAS Estoque

echo.
echo ========================================
echo   Atualizacao Git e GitHub - SAS Estoque
echo ========================================
echo.

cd /d "%~dp0"

echo [1/5] Verificando status do repositorio...
git status
echo.

echo [2/5] Adicionando alteracoes ao Git...
git add .
if errorlevel 1 (
    echo ERRO: Falha ao adicionar arquivos.
    goto fim
)
echo   OK - Arquivos adicionados.
echo.

echo [3/5] Salvando no Git (commit)...
git diff --cached --quiet
if not errorlevel 1 (
    echo   Aviso: Nenhuma alteracao para commitar. Pulando commit.
    echo.
    goto pull
)

set msg=Atualizacao automatica
set /p msg=Digite a mensagem do commit (ou Enter para padrao): 

git commit -m "%msg%"
if errorlevel 1 (
    echo ERRO: Falha ao realizar commit.
    goto fim
)
echo   OK - Commit realizado.
echo.

:pull
echo [4/5] Sincronizando com o GitHub (pull)...
git pull origin main --rebase
if errorlevel 1 (
    echo ERRO: Falha ao sincronizar com o GitHub.
    echo Resolva os conflitos e tente novamente.
    goto fim
)
echo   OK - Sincronizado com sucesso.
echo.

echo [5/5] Enviando para o GitHub...
git push origin main
if errorlevel 1 (
    echo ERRO: Falha ao enviar para o GitHub.
    echo Verifique sua conexao e credenciais.
) else (
    echo   OK - Enviado para o GitHub com sucesso!
)
echo.

:fim
echo ========================================
echo   Processo finalizado.
echo ========================================
echo.
pause
