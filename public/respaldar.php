<?php
require __DIR__ . '/../aplicacion/inicio.php';
try {
    $bd = conexion();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        mostrar_aviso_pagina(
            'Para descargar un respaldo debes ingresar como Administrador y solicitarlo desde Administración.',
            405,
        );
    }
    verificar_csrf();
    if (!isset($_SESSION['usuario'])) {
        mostrar_aviso_pagina('Debes iniciar sesión para solicitar un respaldo.');
    }
    $u = consulta(
        'SELECT u.*,r.nombre rol,r.version FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1',
        [$_SESSION['usuario']['id']],
    )->fetch();
    if (
        !$u ||
        empty($u['rut']) ||
        $u['rol'] !== 'Administrador' ||
        (int) $u['version'] !== (int) $_SESSION['usuario']['version'] ||
        (int) $u['rol_id'] !== (int) $_SESSION['usuario']['rol_id'] ||
        ($_SESSION['actividad'] ?? 0) < time() - 3600
    ) {
        mostrar_aviso_pagina('Tu sesión finalizó o no tienes permiso para descargar respaldos.', 403);
    }
    $_SESSION['actividad'] = time();
    auditar('Generó respaldo SQL');
    $tablas = [
        'roles',
        'roles_permisos',
        'usuarios',
        'noticias',
        'documentos',
        'sistemas_enlaces',
        'tickets',
        'ticket_historial',
        'auditoria',
        'parametros',
        'intentos_acceso',
    ];
    $bd->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $bd->beginTransaction();
    $salida =
        '-- Respaldo intranet municipal · ' . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tablas as $tabla) {
        $estructura = consulta('SHOW CREATE TABLE `' . $tabla . '`')->fetch(PDO::FETCH_NUM);
        $salida .= 'DROP TABLE IF EXISTS `' . $tabla . "`;\n" . $estructura[1] . ";\n";
        foreach (consulta('SELECT * FROM `' . $tabla . '`')->fetchAll() as $fila) {
            $valores = array_map(
                fn($v) => $v === null ? 'NULL' : $bd->quote((string) $v),
                array_values($fila),
            );
            $salida .= 'INSERT INTO `' . $tabla . '` VALUES(' . implode(',', $valores) . ");\n";
        }
    }
    $salida .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $bd->commit();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="respaldo_intranet_' . date('Ymd_His') . '.sql"');
    echo $salida;
} catch (Throwable $ex) {
    if (isset($bd) && $bd->inTransaction()) {
        $bd->rollBack();
    }
    error_log((string) $ex);
    mostrar_aviso_pagina('No se pudo generar el respaldo. Presiona Aceptar para volver a ingresar.', 500);
}
