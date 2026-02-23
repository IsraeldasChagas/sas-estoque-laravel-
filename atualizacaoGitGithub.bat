@echo off
chcp 65001 >nul
title Atualização Git e GitHub - SAS Estoque

echo.
echo ========================================
echo   Atualização Git e GitHub - SAS Estoque
echo ========================================
echo.

cd /d "%~dp0"

echo [1/4] Verificando status do repositório...
git status
echo.

echo [2/4] Adicionando alterações ao Git...
git add .
if %errorlevel% neq 0 (
    echo.
    echo ERRO: Falha ao adicionar arquivos.
    goto fim
)
echo   OK - Arquivos adicionados.
echo.

echo [3/4] Salvando no Git (commit)...
set /p msg="Digite a mensagem do commit (ou Enter para usar padrão): "
if "%msg%"=="" set msg=Atualização automática
git commit -m "%msg%"
if %errorlevel% neq 0 (
    echo.
    echo Aviso: Nenhuma alteração para commit ou commit falhou.
) else (
    echo   OK - Commit realizado.
)
echo.

echo [4/4] Enviando para o GitHub...
git push origin main
if %errorlevel% neq 0 (
    echo.
    echo ERRO: Falha ao enviar para o GitHub.
    echo Verifique sua conexão e credenciais.
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
