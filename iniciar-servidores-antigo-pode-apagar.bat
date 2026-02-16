@echo off
echo ========================================
echo   INICIANDO SERVIDORES SAS-ESTOQUE
echo ========================================
echo.

echo [1/2] Iniciando Backend Laravel (porta 5000)...
start cmd /k "cd /d %~dp0backend && php artisan serve --host=0.0.0.0 --port=5000"

timeout /t 3 >nul

echo [2/2] Iniciando Frontend (porta 8000)...
start cmd /k "cd /d %~dp0frontend && php -S localhost:8000"

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
echo NUNCA abra o arquivo index.html diretamente!
echo.
echo Pressione qualquer tecla para fechar esta janela...
pause >nul
