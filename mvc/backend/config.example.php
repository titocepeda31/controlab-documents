<?php
// ARCHIVO DE EJEMPLO: NO USAR PARA PRODUCCION.
// El config real vive exclusivamente en /home/maderas7/demo-informes/config.php
return [
    'db_host' => 'localhost',
    'db_name' => 'CAMBIAR_BASE_DE_DATOS',
    'db_user' => 'CAMBIAR_USUARIO',
    'db_pass' => 'CAMBIAR_CONTRASENA',
    'base_path' => '/demo',
    'session_name' => 'DEMO_SUELO_MVC_2026',
    // Generar y guardar SOLO en config.php privado: php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
    'data_encryption_key' => 'REEMPLAZAR_POR_CLAVE_BASE64_DE_32_BYTES',
];
