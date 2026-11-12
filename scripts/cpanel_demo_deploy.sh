#!/usr/bin/env bash
# Despliegue aislado de DEMO estática; NO instala Composer, API ni MySQL.
set -Eeuo pipefail
umask 077
MODE="${1:---check}"
if [[ "$MODE" != "--check" && "$MODE" != "--deploy" ]]; then
  echo 'Uso: cpanel_demo_deploy.sh --check | --deploy' >&2; exit 2
fi
REPO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
[[ "$(basename "$REPO")" == 'controlab-documents' ]] || { echo 'BLOQUEADO: repositorio inesperado' >&2; exit 1; }
[[ -n "${HOME:-}" && -d "$HOME" ]] || { echo 'BLOQUEADO: HOME inválido' >&2; exit 1; }
ROOT="$HOME/public_html"
DEST="$ROOT/controlab-demo"
MARK="$DEST/.controlab-documents-managed"
SOURCE="$REPO/public/index.html"
[[ -d "$ROOT" && ! -L "$ROOT" ]] || { echo 'BLOQUEADO: public_html inválido' >&2; exit 1; }
[[ -f "$SOURCE" && ! -L "$SOURCE" ]] || { echo 'BLOQUEADO: falta HTML de demostración' >&2; exit 1; }
[[ ! -L "$DEST" && ! -L "$MARK" && ! -L "$DEST/index.html" ]] || { echo 'BLOQUEADO: enlace simbólico no permitido' >&2; exit 1; }
if [[ -d "$DEST" && ! -f "$MARK" && -n "$(find "$DEST" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
  echo 'BLOQUEADO: directorio existente no administrado' >&2; exit 1
fi
if [[ -f "$MARK" && "$(cat "$MARK")" != 'Controlab Documents - static demo managed by cpanel_demo_deploy.sh' ]]; then
  echo 'BLOQUEADO: marcador de otro despliegue' >&2; exit 1
fi
[[ ! -e "$DEST/index.html" || -f "$DEST/index.html" ]] || { echo 'BLOQUEADO: index.html inválido' >&2; exit 1; }
if ! grep -q 'OT-DEMO-001' "$SOURCE" || ! grep -q 'Constructora Ejemplo SpA' "$SOURCE"; then
  echo 'BLOQUEADO: deben existir solamente datos ficticios del ejemplo' >&2; exit 1
fi
if [[ "$MODE" == '--check' ]]; then
  echo "OK: comprobación únicamente; destino previsto: $DEST"
  exit 0
fi
mkdir -p -- "$DEST"
chmod 0755 "$DEST"
if [[ -f "$DEST/index.html" ]]; then
  BACKUP="$HOME/controlab-documents-demo-backups"
  [[ ! -L "$BACKUP" ]] || { echo 'BLOQUEADO: respaldo con enlace simbólico' >&2; exit 1; }
  mkdir -p -- "$BACKUP"; chmod 0700 "$BACKUP"
  cp -p -- "$DEST/index.html" "$BACKUP/index-$(date -u +%Y%m%dT%H%M%SZ)-$$.html"
fi
TMP="$(mktemp "$DEST/.index-tmp.XXXXXXXX")"
trap 'rm -f -- "$TMP"' EXIT
cp -- "$SOURCE" "$TMP"; chmod 0644 "$TMP"
/bin/mv -f -- "$TMP" "$DEST/index.html"
printf '%s' 'Controlab Documents - static demo managed by cpanel_demo_deploy.sh' > "$MARK"
chmod 0600 "$MARK"
/bin/cmp -s "$SOURCE" "$DEST/index.html" || { echo 'ERROR: copia no coincide' >&2; exit 1; }
echo "OK: demo publicada en $DEST"
echo 'Sin API/MySQL: usar solo datos ficticios, con autorización del titular del hosting.'
