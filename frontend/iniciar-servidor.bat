@echo off
echo ========================================
echo   INICIANDO SERVIDOR FRONTEND
echo ========================================
echo.
echo Iniciando servidor na porta 8001...
echo.
echo Acesse: http://localhost:8001
echo.
echo Pressione Ctrl+C para parar o servidor
echo.

cd /d "%~dp0"
php -S localhost:8001

pause


