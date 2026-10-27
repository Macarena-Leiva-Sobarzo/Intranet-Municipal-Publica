<?php
require __DIR__ . '/../aplicacion/inicio.php';
try {
    $bd = conexion();
    if (!(int) consulta('SELECT COUNT(*) FROM roles')->fetchColumn()) {
        mostrar_aviso_pagina(
            'No se pudo cargar la configuración. Revisa la conexión e importa la base de datos del proyecto.',
            503,
        );
    }
} catch (Throwable $ex) {
    mostrar_aviso_pagina(
        'No se pudo cargar la configuración. Revisa la conexión e importa la base de datos del proyecto.',
        503,
    );
}
if (!isset($_SESSION['usuario'])) {
    ir('ingresar.php');
}
$actual = consulta(
    'SELECT u.*,r.nombre rol,r.version FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1',
    [$_SESSION['usuario']['id']],
)->fetch();
if (
    !$actual ||
    empty($actual['rut']) ||
    (int) $actual['version'] !== (int) $_SESSION['usuario']['version'] ||
    (int) $actual['rol_id'] !== (int) $_SESSION['usuario']['rol_id'] ||
    ($_SESSION['actividad'] ?? 0) < time() - 3600
) {
    $_SESSION = [];
    mostrar_aviso_pagina('Tu sesión finalizó. Presiona Aceptar para ingresar nuevamente.');
}
$_SESSION['usuario'] = $actual;
$_SESSION['actividad'] = time();
$_SESSION['permisos'] = consulta('SELECT modulo FROM roles_permisos WHERE rol_id=?', [
    $actual['rol_id'],
])->fetchAll(PDO::FETCH_COLUMN);
$modulos = [
    'inicio' => 'Inicio',
    'noticias' => 'Noticias internas',
    'directorio' => 'Directorio',
    'documentos' => 'Documentos',
    'sistemas' => 'Sistemas municipales',
    'soporte' => 'Mesa de ayuda',
    'administracion' => 'Administración',
    'ayuda' => 'Ayuda',
];
$modulo = (string) ($_GET['modulo'] ?? 'inicio');
if (!isset($modulos[$modulo])) {
    mostrar_aviso_pagina('La página solicitada no existe o ya no está disponible.', 404);
}
if ($modulo === 'administracion' && !permiso('administracion')) {
    http_response_code(403);
    aviso('Tu perfil no tiene acceso a Administración.', 'error');
    $modulo = 'inicio';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../aplicacion/acciones.php';
    try {
        procesar_accion();
    } catch (Throwable $ex) {
        if ($bd->inTransaction()) {
            $bd->rollBack();
        }
        error_log((string) $ex);
        $mensaje =
            $ex instanceof PDOException
                ? ((string) $ex->getCode() === '23000'
                    ? 'El RUT o el correo ya están registrados, o el registro está relacionado con otros datos.'
                    : 'No se pudo guardar. Inténtalo nuevamente.')
                : ($ex instanceof RuntimeException
                    ? $ex->getMessage()
                    : 'No se pudo completar la operación.');
        aviso($mensaje, 'error');
        $_SESSION['formulario'] = array_diff_key($_POST, array_flip(['clave', 'csrf']));
        $ruta = 'index.php?modulo=' . urlencode($modulo);
        foreach (['editar', 'ver', 'nuevo'] as $k) {
            if (isset($_GET[$k])) {
                $ruta .= '&' . $k . '=' . (int) $_GET[$k];
            }
        }
        ir($ruta);
    }
}
$titulo = $modulos[$modulo];
require __DIR__ . '/../aplicacion/formularios.php';
require __DIR__ . '/../aplicacion/plantilla.php';
mostrar_plantilla($titulo, $modulo, $modulos, $actual);
