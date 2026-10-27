<?php
require __DIR__ . '/../aplicacion/inicio.php';
try {
    $bd = conexion();
    if (!isset($_SESSION['usuario'])) {
        mostrar_aviso_pagina('Debes iniciar sesión nuevamente para descargar documentos.');
    }
    $u = consulta(
        'SELECT u.activo,u.rol_id,r.version FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=?',
        [$_SESSION['usuario']['id']],
    )->fetch();
    if (
        !$u ||
        empty($u['rut']) ||
        !$u['activo'] ||
        (int) $u['version'] !== (int) $_SESSION['usuario']['version'] ||
        (int) $u['rol_id'] !== (int) $_SESSION['usuario']['rol_id'] ||
        ($_SESSION['actividad'] ?? 0) < time() - 3600
    ) {
        mostrar_aviso_pagina('Debes iniciar sesión nuevamente para descargar documentos.');
    }
    $_SESSION['actividad'] = time();
    $d = consulta('SELECT * FROM documentos WHERE id=?', [(int) ($_GET['id'] ?? 0)])->fetch();
    if (!$d || !preg_match('/^[a-f0-9]{48}\.[a-z0-9]+$/', $d['archivo'])) {
        throw new RuntimeException('Documento no disponible.');
    }
    $ruta = __DIR__ . '/../almacenamiento/' . $d['archivo'];
    if (!is_file($ruta)) {
        throw new RuntimeException('Documento no disponible.');
    }
    auditar('Descargó documento #' . $d['id']);
    header('Content-Type: application/octet-stream');
    header(
        'Content-Disposition: attachment; filename="documento.' .
            pathinfo($d['archivo'], PATHINFO_EXTENSION) .
            '"; filename*=UTF-8\'\'' .
            rawurlencode($d['nombre_original']),
    );
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
} catch (Throwable $ex) {
    error_log((string) $ex);
    mostrar_aviso_pagina(
        'El documento solicitado no está disponible. Presiona Aceptar para volver a ingresar.',
        404,
    );
}
