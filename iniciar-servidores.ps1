Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  INICIANDO SERVIDORES SAS-ESTOQUE" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "[1/2] Iniciando Backend Laravel (porta 5000)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit -Command `"cd '$scriptPath\backend'; php artisan serve --host=0.0.0.0 --port=5000`""

Start-Sleep -Seconds 3

Write-Host "[2/2] Iniciando Frontend (porta 8000)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit -Command `"cd '$scriptPath\frontend'; php -S localhost:8000`""

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  SERVIDORES INICIADOS!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Backend Laravel: http://localhost:5000" -ForegroundColor Cyan
Write-Host "API:            http://localhost:5000/api" -ForegroundColor Cyan
Write-Host "Frontend:       http://localhost:8000" -ForegroundColor Cyan
Write-Host ""
Write-Host "IMPORTANTE: Acesse o sistema em http://localhost:8000" -ForegroundColor Yellow
Write-Host "NUNCA abra o arquivo index.html diretamente!" -ForegroundColor Yellow
Write-Host ""
Write-Host "Pressione Enter para fechar esta janela..." -ForegroundColor Gray
Read-Host
