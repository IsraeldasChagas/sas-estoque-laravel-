Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Iniciando servidor Backend Laravel" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Porta: 5000" -ForegroundColor Green
Write-Host "URL: http://localhost:5000" -ForegroundColor Green
Write-Host "API: http://localhost:5000/api" -ForegroundColor Green
Write-Host ""
Write-Host "Pressione Ctrl+C para parar o servidor" -ForegroundColor Yellow
Write-Host ""

Set-Location backend
php artisan serve --host=0.0.0.0 --port=5000

