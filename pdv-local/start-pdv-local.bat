@echo off
cd /d "%~dp0"
if not exist node_modules (
  echo Instalando dependencias...
  call npm install
)
echo.
echo Iniciando PDV Local...
call npm start
pause
