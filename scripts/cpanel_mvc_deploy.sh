#!/usr/bin/env bash
# Despliegue de Controlab MVC desde la copia Git de cPanel.
# No tocar contraseñas/archivos de otros proyectos.
set -Eeuo pipefail
umask 077

if [[ "${1:-}" != "--deploy" ]]; then
  echo "Uso: cpanel_mvc_deploy.sh --deploy" >&2
  exit 2
fi

BASE="${HOME:-}"
[[ -n "$BASE" && -d "$BASE" ]] || { echo 'ERROR: HOME no definido'; exit 1; }
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
[[ "$REPO" == "$BASE/repositories/controlab-documents" ]] || {
  echo 'ERROR: clon en ruta inesperada. No se desplegó.' >&2; exit 1;
}
PRIVATE="$BASE/demo-informes"
PUBLIC="$BASE/public_html/demo"
[[ -f "$PRIVATE/config.php" && -f "$PRIVATE/bootstrap.php" && -d "$PRIVATE/src" ]] || {
  echo 'ERROR: falta instalación MVC anterior/config.php privado' >&2; exit 1;
}
[[ -f "$BASE/tito-pos-privado/api/vendor/autoload.php" ]] || {
  echo 'ERROR: falta autoload de Slim en ruta esperada' >&2; exit 1;
}
[[ -d "$PUBLIC" && -f "$PUBLIC/index.php" ]] || {
  echo 'ERROR: la carpeta pública MVC no existe; no se creó automáticamente' >&2; exit 1;
}
for path in \
  mvc/backend/bootstrap.php \
  mvc/backend/routes/api.php \
  mvc/backend/src/Core/Database.php \
  mvc/backend/src/Core/Http.php \
  mvc/backend/src/Models/Empresa.php \
  mvc/backend/src/Models/Informe.php \
  mvc/backend/src/Models/Revision.php \
  mvc/backend/src/Models/Usuario.php \
  mvc/backend/src/Controllers/AuthController.php \
  mvc/backend/src/Controllers/EmpresaController.php \
  mvc/backend/src/Controllers/InformeController.php \
  mvc/backend/src/Controllers/RevisionController.php \
  mvc/backend/src/Middleware/Security.php \
  mvc/frontend/index.php \
  mvc/frontend/.htaccess \
  mvc/frontend/formulario.html \
  mvc/frontend/sw.js
  do
    [[ -f "$REPO/$path" && ! -L "$REPO/$path" ]] || {
      echo "ERROR: falta o es enlace: $path" >&2; exit 1;
    }
  done

# Si no existe la tabla de envíos, bloquear el despliegue antes de tocar archivos.
PHP_BIN="$(command -v php || true)"
[[ -n "$PHP_BIN" ]] || { echo 'ERROR: PHP CLI no disponible. Despliegue detenido.' >&2; exit 1; }
while IFS= read -r -d '' file; do
  "$PHP_BIN" -l "$file" >/dev/null || { echo "ERROR PHP syntax: $file" >&2; exit 1; }
done < <(find "$REPO/mvc/backend" "$REPO/mvc/frontend" -type f -name '*.php' -print0)
"$PHP_BIN" -r '
  $cfg = require $argv[1];
  $pdo = new PDO("mysql:host=".$cfg["db_host"].";dbname=".$cfg["db_name"].";charset=utf8mb4", $cfg["db_user"], $cfg["db_pass"], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
  foreach (["informe_envios","informe_revisores","informe_revisiones","informe_revision_eventos"] as $table) {
    $q->execute([$table]);
    if (!(int)$q->fetchColumn()) {fwrite(STDERR,"Falta la tabla $table. Ejecutar migraciones SQL antes de desplegar.\n");exit(2);}
  }
' "$PRIVATE/config.php" || { echo 'ERROR: comprobación de migración fallida; no se desplegó.' >&2; exit 1; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"
BACKUP="$BASE/controlab-mvc-backups/$STAMP"
mkdir -p "$BACKUP/private" "$BACKUP/public"
chmod 0700 "$BASE/controlab-mvc-backups" "$BACKUP" "$BACKUP/private" "$BACKUP/public"

# Solo archivos gestionados: no se toca config.php ni app.php.
BACKEND_FILES=(
  bootstrap.php routes/api.php
  src/Core/Database.php src/Core/Http.php
  src/Models/Empresa.php src/Models/Informe.php src/Models/Revision.php src/Models/Usuario.php
  src/Controllers/AuthController.php src/Controllers/EmpresaController.php src/Controllers/InformeController.php src/Controllers/RevisionController.php
  src/Middleware/Security.php
)
FRONTEND_FILES=(index.php .htaccess formulario.html sw.js)

for rel in "${BACKEND_FILES[@]}"; do
  [[ -f "$PRIVATE/$rel" ]] && { mkdir -p "$BACKUP/private/$(dirname "$rel")"; cp -p "$PRIVATE/$rel" "$BACKUP/private/$rel"; }
done
for rel in "${FRONTEND_FILES[@]}"; do
  [[ -f "$PUBLIC/$rel" ]] && { mkdir -p "$BACKUP/public/$(dirname "$rel")"; cp -p "$PUBLIC/$rel" "$BACKUP/public/$rel"; }
done

echo "Respaldo previo: $BACKUP"
for rel in "${BACKEND_FILES[@]}"; do
  mkdir -p "$PRIVATE/$(dirname "$rel")"
  cp -f "$REPO/mvc/backend/$rel" "$PRIVATE/$rel"
done
for rel in "${FRONTEND_FILES[@]}"; do
  cp -f "$REPO/mvc/frontend/$rel" "$PUBLIC/$rel"
done
printf 'CONTROLAB_MVC_COMMIT=%s\nFECHA_UTC=%s\n' "$(git -C "$REPO" rev-parse --short HEAD)" "$STAMP" > "$BACKUP/deployed.txt"
echo 'OK: MVC desplegado. Configuración privada conservada.'
echo 'Verificar /demo/api/session, login y guardado en MySQL.'
