#!/bin/bash
# Script: sas_fix_api_auto.sh
# Objetivo: corrigir URL da API no frontend e recarregar cache do Laravel

set -e  # se der erro em algum comando, para o script

PROJ_DIR="$HOME/public_html/sas-estoque"
BACKEND="$PROJ_DIR/backend"
FRONTEND="$PROJ_DIR/frontend"
API_URL="https://api.gruposaborparaense.com.br"
API_URL_FULL="$API_URL/api"

echo "==============================================="
echo "  AJUSTE DE API / ROTAS - SAS ESTOQUE"
echo "  Projeto: $PROJ_DIR"
echo "==============================================="
echo

##############################
# 1) Ajustar FRONTEND
##############################
echo "➡ Ajustando URLs no FRONTEND: $FRONTEND"
cd "$FRONTEND"

# config.js (caso exista)
if [ -f config.js ]; then
  echo "  - Atualizando config.js ..."
  sed -i "s#http://localhost:5000/api#$API_URL_FULL#g" config.js || true
  sed -i "s#http://localhost:5000#$API_URL#g" config.js || true
fi

# app.js (fallback BASE_URL / mensagens de erro)
if [ -f app.js ]; then
  echo "  - Atualizando app.js ..."
  sed -i "s#http://localhost:5000#$API_URL#g" app.js || true
fi

echo
echo "✅ URLs do frontend ajustadas para: $API_URL_FULL"
echo

##############################
# 2) Ajustar BACKEND (Laravel)
##############################
echo "➡ Limpando e recriando cache do Laravel: $BACKEND"
cd "$BACKEND"

php artisan config:clear
php artisan cache:clear
php artisan route:clear

php artisan config:cache
php artisan route:cache

echo
echo "✅ Backend atualizado e cache recriado."
echo "==============================================="
echo "  FIM DO SCRIPT sas_fix_api_auto.sh"
echo "  Agora teste o login no navegador."
echo "==============================================="
