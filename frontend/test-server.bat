@echo off
echo ========================================
echo   Servidor de Teste - SAS Estoque
echo ========================================
echo.
echo Iniciando servidor na porta 8000...
echo.
echo Acesse: http://localhost:8000
echo.
echo Pressione Ctrl+C para parar o servidor
echo.
cd /d "%~dp0"
php -S localhost:8000



