#!/usr/bin/env bash
# Sincroniza allowlist do OpenClaw (channels.telegram.allowFrom).
# Uso: sync-allowlist.sh adicionar|remover|sincronizar [TELEGRAM_USER_ID]
set -euo pipefail

ACTION="${1:-}"
TG_ID="${2:-}"
OPENCLAW_JSON="${OPENCLAW_CONFIG:-$HOME/.openclaw/openclaw.json}"
LOCK_FILE="/tmp/ayla-allowlist-sync.lock"
BACKUP_DIR="${AYLA_ALLOWLIST_BACKUP_DIR:-/opt/ayla-telegram-bridge/backups}"

log() { echo "[$(date -Iseconds)] $*"; }
die() { log "ERRO: $*"; exit 1; }

[[ -f "$OPENCLAW_JSON" ]] || die "Config OpenClaw não encontrada: $OPENCLAW_JSON"

acquire_lock() {
  exec 9>"$LOCK_FILE"
  flock -n 9 || die "Outra sincronização em andamento."
}

validate_id() {
  [[ "$TG_ID" =~ ^[0-9]{3,32}$ ]] || die "Telegram ID inválido."
}

backup_config() {
  mkdir -p "$BACKUP_DIR"
  cp -a "$OPENCLAW_JSON" "$BACKUP_DIR/openclaw.json.$(date +%Y%m%d%H%M%S).bak"
}

restore_latest_backup() {
  local latest
  latest=$(ls -1t "$BACKUP_DIR"/openclaw.json.*.bak 2>/dev/null | head -1 || true)
  [[ -n "$latest" ]] || die "Sem backup para restaurar."
  cp -a "$latest" "$OPENCLAW_JSON"
  log "Backup restaurado: $latest"
}

read_allowlist() {
  python3 - "$OPENCLAW_JSON" <<'PY'
import json, sys
path = sys.argv[1]
with open(path, encoding='utf-8') as f:
    data = json.load(f)
allow = data.get('channels', {}).get('telegram', {}).get('allowFrom', [])
if not isinstance(allow, list):
    allow = []
print('\n'.join(str(x) for x in allow if str(x).strip()))
PY
}

write_allowlist() {
  local tmp ids_file
  tmp=$(mktemp)
  ids_file=$(mktemp)
  read_allowlist > "$ids_file"
  case "$ACTION" in
    adicionar)
      validate_id
      grep -qxF "$TG_ID" "$ids_file" || echo "$TG_ID" >> "$ids_file"
      ;;
    remover)
      validate_id
      grep -vxF "$TG_ID" "$ids_file" > "${ids_file}.new" || true
      mv "${ids_file}.new" "$ids_file"
      ;;
    sincronizar)
      : # mantém lista atual
      ;;
    *)
      die "Ação inválida: $ACTION"
      ;;
  esac

  # Nunca substituir por lista vazia acidentalmente em remover isolado
  if [[ "$ACTION" == "remover" ]] && [[ ! -s "$ids_file" ]]; then
    log "Lista ficaria vazia após remoção — abortando por segurança."
    exit 1
  fi

  python3 - "$OPENCLAW_JSON" "$ids_file" > "$tmp" <<'PY'
import json, sys
path, ids_path = sys.argv[1], sys.argv[2]
with open(path, encoding='utf-8') as f:
    data = json.load(f)
with open(ids_path, encoding='utf-8') as f:
    ids = [line.strip() for line in f if line.strip()]
data.setdefault('channels', {}).setdefault('telegram', {})['allowFrom'] = ids
data['channels']['telegram']['dmPolicy'] = 'allowlist'
with open(path, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)
    f.write('\n')
PY

  rm -f "$ids_file"
}

validate_openclaw() {
  if command -v openclaw >/dev/null 2>&1; then
    openclaw config validate || return 1
  fi
  return 0
}

restart_gateway() {
  if systemctl --user is-active openclaw-gateway >/dev/null 2>&1; then
    systemctl --user restart openclaw-gateway
  else
    log "openclaw-gateway não ativo via systemd — reinicie manualmente se necessário."
  fi
}

main() {
  [[ -n "$ACTION" ]] || die "Informe adicionar|remover|sincronizar"
  acquire_lock
  backup_config
  if ! write_allowlist; then
    restore_latest_backup
    die "Falha ao atualizar allowlist."
  fi
  if ! validate_openclaw; then
    restore_latest_backup
    die "openclaw config validate falhou — backup restaurado."
  fi
  restart_gateway || {
    restore_latest_backup
    die "Falha ao reiniciar gateway — backup restaurado."
  }
  log "Allowlist atualizada ($ACTION) com sucesso."
}

main "$@"
