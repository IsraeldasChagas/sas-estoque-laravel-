Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Servidor de Teste - SAS Estoque" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Iniciando servidor na porta 8000..." -ForegroundColor Yellow
Write-Host ""
Write-Host "Acesse: http://localhost:8000" -ForegroundColor Green
Write-Host ""
Write-Host "Pressione Ctrl+C para parar o servidor" -ForegroundColor Yellow
Write-Host ""

$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

php -S localhost:8000



