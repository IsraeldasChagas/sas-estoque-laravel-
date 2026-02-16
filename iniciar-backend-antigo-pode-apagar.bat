@echo off
echo ========================================
echo Iniciando servidor Backend Laravel
echo ========================================
echo.
echo Porta: 5000
echo URL: http://localhost:5000
echo API: http://localhost:5000/api
echo.
echo Pressione Ctrl+C para parar o servidor
echo.
cd backend
php artisan serve --host=0.0.0.0 --port=5000
pause

