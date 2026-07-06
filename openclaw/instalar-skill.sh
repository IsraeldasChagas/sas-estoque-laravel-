#!/bin/bash
# Instala a skill SAS-Estoque no OpenClaw (rodar na VPS após openclaw onboard).
set -euo pipefail

SKILL_SRC="$(cd "$(dirname "$0")/skill-sas-estoque" && pwd)"
SKILL_DEST="${OPENCLAW_WORKSPACE:-$HOME/.openclaw/workspace}/skills/sas-estoque"

echo "Origem:  $SKILL_SRC"
echo "Destino: $SKILL_DEST"

mkdir -p "$(dirname "$SKILL_DEST")"
rm -rf "$SKILL_DEST"
cp -r "$SKILL_SRC" "$SKILL_DEST"

echo ""
echo "Skill instalada em: $SKILL_DEST"
echo ""
echo "Configure as variáveis no OpenClaw:"
echo "  SAS_ESTOQUE_API_URL=https://api.gruposaborparaense.com.br/api/ia"
echo "  SAS_ESTOQUE_TOKEN=<token gerado no painel SAS-Estoque>"
echo ""
echo "Exemplo openclaw.json (skills.entries):"
cat <<'EOF'
{
  "skills": {
    "entries": {
      "sas-estoque": {
        "enabled": true,
        "apiKey": { "source": "env", "id": "SAS_ESTOQUE_TOKEN" }
      }
    }
  }
}
EOF
echo ""
echo "Reinicie o gateway: openclaw gateway restart"
