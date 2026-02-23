@echo off
title Atualizacao Git e GitHub - SAS Estoque

echo.
echo ========================================
echo   Atualizacao Git e GitHub - SAS Estoque
echo ========================================
echo.

cd /d "%~dp0"

echo [1/4] Verificando status do repositorio...
git status
echo.

echo [2/4] Adicionando alteracoes ao Git...
git add .
if errorlevel 1 (
    echo.
    echo ERRO: Falha ao adicionar arquivos.
    goto fim
)
echo   OK - Arquivos adicionados.
echo.

echo [3/4] Salvando no Git (commit)...
set "msg="
set /p "msg=Digite a mensagem do commit (ou Enter para padrao): "
if not defined msg set "msg=Atualizacao automatica"
git commit -m "%msg%"
if errorlevel 1 (
    echo.
    echo Aviso: Nenhuma alteracao para commit ou commit falhou.
) else (
    echo   OK - Commit realizado.
)
echo.

echo [4/4] Enviando para o GitHub...
git push origin main
if errorlevel 1 (
    echo.
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
