@echo off
title SAS Estoque - Backend e Frontend
color 0A

echo ========================================
echo   INICIANDO BACKEND E FRONTEND
echo ========================================
echo.

REM Limpar processos PHP anteriores (opcional)
taskkill /F /IM php.exe >nul 2>&1

echo [1/2] Iniciando Backend Laravel (porta 5000)...
cd /d "%~dp0backend"
start /b php artisan serve --host=0.0.0.0 --port=5000

timeout /t 3 >nul

echo [2/2] Iniciando Frontend (porta 8000)...
cd /d "%~dp0frontend"
start /b php -S localhost:8000

timeout /t 2 >nul

echo.
echo ========================================
echo   SERVIDORES INICIADOS!
echo ========================================
echo.
echo Backend Laravel: http://localhost:5000
echo API:            http://localhost:5000/api
echo Frontend:       http://localhost:8000
echo.
echo IMPORTANTE: Acesse o sistema em http://localhost:8000
echo.
echo ========================================
echo Pressione qualquer tecla para PARAR os servidores...
echo ========================================
pause >nul

echo.
echo Parando servidores...
taskkill /F /IM php.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo Servidores parados com sucesso!
) else (
    echo Nenhum servidor em execucao.
)
timeout /t 2 >nul

