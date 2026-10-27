<?php
declare(strict_types=1);
require_once __DIR__ . '/rutas.php';
require_once __DIR__ . '/personas.php';
date_default_timezone_set('America/Santiago');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.gc_maxlifetime', '3600');
session_name('intranet_municipal');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header(
    "Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'",
);
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
ob_start('presentar_rutas_limpias');

// Conserva enlaces antiguos y muestra su dirección pública equivalente.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_SERVER['REDIRECT_STATUS'])) {
    $solicitada = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $base = base_publica();
    $archivos = [
        'index.php',
        'ingresar.php',
        'registro_inicial.php',
        'descargar.php',
        'respaldar.php',
        'aviso.php',
    ];
    foreach ($archivos as $archivo) {
        if ($solicitada === $base . '/public/' . $archivo || $solicitada === $base . '/' . $archivo) {
            $consulta = (string) ($_SERVER['QUERY_STRING'] ?? '');
            $destino = ruta_publica($archivo . ($consulta !== '' ? '?' . $consulta : ''));
            if ($destino !== $archivo) {
                header('Location: ' . $destino, true, 302);
                exit();
            }
        }
    }
}

function mostrar_aviso_pagina(string $mensaje, int $codigo = 401): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    header_remove('Content-Disposition');
    header_remove('Content-Length');
    $directorio = rtrim(
        str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/Intranet_municipal/public/index.php')),
        '/',
    );
    $estilos = ruta_publica('css/estilos.css?v=' . filemtime(__DIR__ . '/../public/css/estilos.css'));
    $ingreso = ruta_publica('ingresar.php');
    echo '<!doctype html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Información · Intranet Ñiquén</title>
        <link rel="stylesheet" href="' .
        e($estilos) .
        '">
    </head>
    <body class="pagina-aviso">
        <main class="tarjeta aviso-pagina" aria-labelledby="titulo-aviso">
            <span class="icono-grande" aria-hidden="true">i</span>
            <h1 id="titulo-aviso">Necesitas volver al inicio de sesión</h1>
            <p>' .
        e($mensaje) .
        '</p>
            <form method="post" action="' .
        e($ingreso) .
        '">
                ' .
        csrf() .
        '
                <input type="hidden" name="accion" value="reiniciar_sesion">
                <button type="submit" autofocus>Aceptar</button>
            </form>
        </main>
        <footer class="pie-aviso">© ' .
        date('Y') .
        ' · Ilustre Municipalidad de Ñiquén</footer>
    </body>
</html>';
    exit();
}
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pin_valido(string $pin): bool
{
    return preg_match('/\A[0-9]{6}\z/', $pin) === 1;
}
function destino_perfil(string $rol): string
{
    return match ($rol) {
        'Administrador' => 'index.php?modulo=administracion',
        'Informática' => 'index.php?modulo=soporte',
        'Editor' => 'index.php?modulo=noticias',
        default => 'index.php',
    };
}
function ir(string $ruta = 'index.php'): never
{
    header('Location: ' . ruta_publica($ruta));
    exit();
}
function conexion(bool $sin_base = false): PDO
{
    $c = require __DIR__ . '/../config/base_datos.php';
    return new PDO(
        'mysql:host=' .
            $c['servidor'] .
            ';port=' .
            $c['puerto'] .
            ($sin_base ? '' : ';dbname=' . $c['nombre']) .
            ';charset=utf8mb4',
        $c['usuario'],
        $c['clave'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
}
function consulta(string $sql, array $datos = []): PDOStatement
{
    global $bd;
    $q = $bd->prepare($sql);
    $q->execute($datos);
    return $q;
}
function token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token()) . '">';
}
function verificar_csrf(): void
{
    if (!hash_equals(token(), (string) ($_POST['csrf'] ?? ''))) {
        throw new RuntimeException(
            'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.',
        );
    }
}
function aviso(string $mensaje, string $tipo = 'exito'): void
{
    $_SESSION['aviso'] = [$mensaje, $tipo];
}
function requerido(string $campo, int $max = 180): string
{
    $v = trim((string) ($_POST[$campo] ?? ''));
    if ($v === '' || mb_strlen($v) > $max) {
        throw new RuntimeException(
            'Completa correctamente el campo ' .
                str_replace('_', ' ', $campo) .
                ' (máximo ' .
                $max .
                ' caracteres).',
        );
    }
    return $v;
}
function opcion(string $campo, array $opciones): string
{
    $v = requerido($campo);
    if (!in_array($v, $opciones, true)) {
        throw new RuntimeException('La opción seleccionada no es válida.');
    }
    return $v;
}
function permiso(string $modulo): bool
{
    return isset($_SESSION['usuario']) &&
        ($_SESSION['usuario']['rol'] === 'Administrador' ||
            in_array($modulo, $_SESSION['permisos'] ?? [], true));
}
function exigir(string $modulo): void
{
    if (!permiso($modulo)) {
        http_response_code(403);
        throw new RuntimeException('Tu perfil no tiene permiso para realizar esta acción.');
    }
}
function auditar(string $accion): void
{
    $persona = $_SESSION['usuario'] ?? [];
    consulta('INSERT INTO auditoria(usuario_id,accion,autor_nombre,autor_rut) VALUES(?,?,?,?)', [
        $persona['id'] ?? null,
        $accion,
        $persona['nombre'] ?? null,
        $persona['rut'] ?? null,
    ]);
}
function parametro(string $nombre, string $defecto = ''): string
{
    return (string) (consulta('SELECT valor FROM parametros WHERE nombre=?', [$nombre])->fetchColumn() ?:
    $defecto);
}
function folio(int $id): string
{
    return 'SOP-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}
function fecha(?string $fecha): string
{
    return $fecha ? date('d/m/Y H:i', strtotime($fecha)) : '—';
}
const ESTADOS = ['Nuevo', 'Asignado', 'En proceso', 'Esperando usuario', 'Resuelto', 'Cerrado'];
const CATEGORIAS = [
    'Computador o notebook',
    'Impresora',
    'Internet / red',
    'Correo institucional',
    'Acceso a sistemas',
    'Creación o modificación de usuario',
    'Software',
    'Telefonía',
    'Otro',
];
const PRIORIDADES = ['Baja', 'Media', 'Alta', 'Urgente'];
function url_valida(string $url): string
{
    if (
        !filter_var($url, FILTER_VALIDATE_URL) ||
        !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
    ) {
        throw new RuntimeException('Ingresa una dirección web válida que comience con https:// o http://.');
    }
    return $url;
}
function ticket_visible(int $id): array
{
    $t = consulta(
        'SELECT t.*,COALESCE(t.solicitante_nombre,u.nombre) solicitante,COALESCE(t.solicitante_rut,u.rut) solicitante_rut,r.nombre responsable,r.rut responsable_rut FROM tickets t JOIN usuarios u ON u.id=t.solicitante_id LEFT JOIN usuarios r ON r.id=t.responsable_id WHERE t.id=?',
        [$id],
    )->fetch();
    if (!$t || (!permiso('soporte') && (int) $t['solicitante_id'] !== (int) $_SESSION['usuario']['id'])) {
        http_response_code(404);
        throw new RuntimeException('No se encontró la solicitud.');
    }
    return $t;
}
function guardar_archivo(): array
{
    $f = $_FILES['archivo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Selecciona un archivo válido de hasta 10 MB.');
    }
    if ($f['size'] > 10 * 1024 * 1024 || $f['size'] === 0) {
        throw new RuntimeException('El archivo debe pesar entre 1 byte y 10 MB.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $tipos = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($tipos[$ext]) || !in_array($mime, $tipos[$ext], true)) {
        throw new RuntimeException('Formato no permitido. Usa PDF, PNG, JPG, DOCX, XLSX o CSV.');
    }
    if (in_array($ext, ['docx', 'xlsx'], true)) {
        $z = new ZipArchive();
        if ($z->open($f['tmp_name']) !== true) {
            throw new RuntimeException('Documento de Office inválido.');
        }
        $valido =
            $z->locateName('[Content_Types].xml') !== false &&
            $z->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') !== false;
        $z->close();
        if (!$valido) {
            throw new RuntimeException('Documento de Office inválido.');
        }
    }
    $nombre = bin2hex(random_bytes(24)) . '.' . $ext;
    $destino = __DIR__ . '/../almacenamiento/' . $nombre;
    if (!move_uploaded_file($f['tmp_name'], $destino)) {
        throw new RuntimeException('No se pudo almacenar el archivo.');
    }
    return [$nombre, mb_substr(basename($f['name']), 0, 200), $mime, (int) $f['size']];
}

set_exception_handler(function (Throwable $error): void {
    error_log((string) $error);
    mostrar_aviso_pagina('No se pudo mostrar esta página. Presiona Aceptar para volver a ingresar.', 500);
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if (
        $error !== null &&
        in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)
    ) {
        error_log($error['message']);
        mostrar_aviso_pagina(
            'Se produjo un problema al cargar la página. Presiona Aceptar para volver a ingresar.',
            500,
        );
    }
});
