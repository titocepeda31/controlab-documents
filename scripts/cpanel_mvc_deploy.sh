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
  mvc/backend/src/Core/ReportCrypto.php \
  mvc/backend/src/Core/Http.php \
  mvc/backend/src/Core/Paginator.php \
  mvc/backend/src/Models/Empresa.php \
  mvc/backend/src/Models/Informe.php \
  mvc/backend/src/Models/LaboratoristaFirma.php \
  mvc/backend/src/Models/Revision.php \
  mvc/backend/src/Models/Usuario.php \
  mvc/backend/src/Models/OrdenTrabajo.php \
  mvc/backend/src/Models/CatalogoOperativo.php \
  mvc/backend/src/Models/MapaObras.php \
  mvc/backend/src/Controllers/AuthController.php \
  mvc/backend/src/Controllers/EmpresaController.php \
  mvc/backend/src/Controllers/InformeController.php \
  mvc/backend/src/Controllers/UsuariosController.php \
  mvc/backend/src/Controllers/OrdenTrabajoController.php \
  mvc/backend/src/Controllers/CatalogoOperativoController.php \
  mvc/backend/src/Controllers/LaboratoristaFirmaController.php \
  mvc/backend/src/Controllers/RevisionController.php \
  mvc/backend/src/Controllers/CorreoController.php \
  mvc/backend/src/Middleware/Security.php \
  mvc/frontend/index.php \
  mvc/frontend/.htaccess \
  mvc/frontend/formulario.html \
  mvc/frontend/usuarios.html \
  mvc/frontend/planificador.html \
  mvc/frontend/catalogos.html \
  mvc/frontend/empresas.html \
  mvc/frontend/obras.html \
  mvc/frontend/servicios.html \
  mvc/frontend/personal.html \
  mvc/frontend/modulos.html \
  mvc/frontend/dashboard.html \
  mvc/frontend/tabler-preview.html \
  mvc/frontend/tabler-shell.js \
  mvc/frontend/pagination.js \
  mvc/frontend/assets/css/app.css \
  mvc/frontend/assets/css/shell.css \
  mvc/frontend/sw.js \
  mvc/frontend/manifest.json \
  mvc/frontend/icon.svg
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
  foreach (["informe_envios","informe_revisores","informe_revisiones","informe_revision_eventos","laboratorista_firmas","usuario_perfiles","ot_ordenes","ot_asignaciones","ot_eventos","obras","personal_terreno","ot_obra_vinculos","ot_personal_terreno","obra_ubicaciones","geo_busquedas_cache","geo_nominatim_limite","catalogo_servicios","ot_servicios"] as $table) {
    $q->execute([$table]);
    if (!(int)$q->fetchColumn()) {fwrite(STDERR,"Falta la tabla $table. Ejecutar migraciones SQL antes de desplegar.\n");exit(2);}
  }
  $admins=(int)$pdo->query("SELECT COUNT(*) FROM usuarios u JOIN usuario_perfiles p ON p.usuario_id=u.id WHERE u.activo=1 AND p.rol=\"administrador\"")->fetchColumn();
  if($admins<1){fwrite(STDERR,"No hay administrador activo en usuario_perfiles. Asignar perfil en phpMyAdmin antes de desplegar.\n");exit(4);}
  if (!extension_loaded("openssl")) {fwrite(STDERR,"OpenSSL no disponible.\n");exit(4);}
  $key=base64_decode((string)($cfg["data_encryption_key"]??""),true);
  if(!is_string($key)||strlen($key)!==32){fwrite(STDERR,"Falta data_encryption_key en configuración privada.\n");exit(4);}
  $column=$pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\"informe_datos\" AND COLUMN_NAME=\"datos\"")->fetchColumn();
  if(!in_array(strtolower((string)$column),["mediumtext","longtext"],true)){fwrite(STDERR,"informe_datos.datos requiere MEDIUMTEXT o LONGTEXT.\n");exit(4);}
  $signatureColumn=$pdo->query("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\"informe_revisiones\" AND COLUMN_NAME=\"firma_png\"")->fetchColumn();
  if(!in_array(strtolower((string)$signatureColumn),["mediumblob","longblob"],true)){fwrite(STDERR,"informe_revisiones.firma_png requiere MEDIUMBLOB o LONGBLOB.\\n");exit(4);}
  $plaintextSignatures=(int)$pdo->query("SELECT COUNT(*) FROM informe_revisiones WHERE firma_png IS NOT NULL AND LENGTH(firma_png)>0 AND LEFT(firma_png,7)!=\"enc:v1:\"")->fetchColumn();
  if($plaintextSignatures>0){fwrite(STDERR,"Hay $plaintextSignatures firmas sin cifrar. Migración pendiente.\\n");exit(4);}
  $plain=(int)$pdo->query("SELECT COUNT(*) FROM informe_datos WHERE LEFT(datos,7)!=\"enc:v1:\" AND LEFT(datos,8)!=CONCAT(CHAR(34),\"enc:v1:\")")->fetchColumn();
  if($plain>0){fwrite(STDERR,"Hay $plain informes sin cifrar. Migrar con mantenimiento y respaldo antes de desplegar.\n");exit(4);}
' "$PRIVATE/config.php" || { echo 'ERROR: comprobación de migración fallida; no se desplegó.' >&2; exit 1; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)-$$"
BACKUP="$BASE/controlab-mvc-backups/$STAMP"
mkdir -p "$BACKUP/private" "$BACKUP/public"
chmod 0700 "$BASE/controlab-mvc-backups" "$BACKUP" "$BACKUP/private" "$BACKUP/public"

# Solo archivos gestionados: no se toca config.php ni app.php.
BACKEND_FILES=(
  bootstrap.php routes/api.php
  src/Core/Database.php src/Core/Http.php src/Core/ReportCrypto.php src/Core/Paginator.php
  src/Models/Empresa.php src/Models/Informe.php src/Models/LaboratoristaFirma.php src/Models/Revision.php src/Models/Usuario.php src/Models/OrdenTrabajo.php src/Models/CatalogoOperativo.php src/Models/MapaObras.php
  src/Controllers/AuthController.php src/Controllers/EmpresaController.php src/Controllers/InformeController.php src/Controllers/UsuariosController.php src/Controllers/OrdenTrabajoController.php src/Controllers/CatalogoOperativoController.php src/Controllers/LaboratoristaFirmaController.php src/Controllers/RevisionController.php src/Controllers/CorreoController.php
  src/Middleware/Security.php
)
FRONTEND_FILES=(index.php .htaccess formulario.html usuarios.html planificador.html catalogos.html empresas.html obras.html servicios.html personal.html modulos.html dashboard.html tabler-preview.html tabler-shell.js pagination.js sw.js manifest.json icon.svg)
while IFS= read -r -d '' file; do
  FRONTEND_FILES+=("${file#"$REPO/mvc/frontend/"}")
done < <(find "$REPO/mvc/frontend/assets" -type f -name '*.css' -print0)

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
  mkdir -p "$PUBLIC/$(dirname "$rel")"
  dir="$(dirname "$rel")"
  while [[ "$dir" != "." ]]; do chmod 0755 "$PUBLIC/$dir"; dir="$(dirname "$dir")"; done
  cp -f "$REPO/mvc/frontend/$rel" "$PUBLIC/$rel"
  # umask 077 protege los respaldos y ficheros privados, pero deja 0600
  # cuando publica archivos nuevos (por ejemplo usuarios.html).
  # Los recursos en public_html deben ser legibles por el servidor web.
  chmod 0644 "$PUBLIC/$rel"
done
printf 'CONTROLAB_MVC_COMMIT=%s\nFECHA_UTC=%s\n' "$(git -C "$REPO" rev-parse --short HEAD)" "$STAMP" > "$BACKUP/deployed.txt"
echo 'OK: MVC desplegado. Configuración privada conservada.'
echo 'Verificar /demo/api/session, login y guardado en MySQL.'
