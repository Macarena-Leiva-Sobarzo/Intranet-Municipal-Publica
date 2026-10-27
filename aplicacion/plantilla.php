<?php
function mostrar_plantilla(string $titulo, string $modulo, array $modulos, array $actual): void
{
    ?>
<!doctype html>
<html lang="es">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= e($titulo) ?> · Intranet Ñiquén</title>
        <link rel="stylesheet" href="css/estilos.css">
        <script src="interacciones.js?v=<?= filemtime(
                __DIR__ . '/../public/interacciones.js',
            ) ?>" defer></script>
    </head>

    <body data-sesion-protegida>
        <aside class="lateral" id="navegacion">
            <a class="marca" href="index.php">
                <img src="imagenes/logo_municipalidad.png" width="560" height="217"
                    alt="Ilustre Municipalidad de Ñiquén. ¡El progreso lo construimos juntos!">
            </a>
            <div class="separador"></div>
            <span class="rotulo">ESPACIO DE TRABAJO</span>
            <nav aria-label="Navegación principal">
                <?php
                    $iconos = [
                        'inicio' => '⌂',
                        'noticias' => '▤',
                        'directorio' => '♙',
                        'documentos' => '▱',
                        'sistemas' => '▦',
                        'soporte' => '◉',
                        'administracion' => '⚙',
                        'ayuda' => '?',
                    ];
                    foreach ($modulos as $k => $v):
                        if ($k === 'administracion' && !permiso('administracion')) {
                            continue;
                        } ?>
                <a class="<?= $modulo === $k ? 'activo' : '' ?>" href="index.php?modulo=<?= e(
    $k,
) ?>" <?= $modulo === $k ? 'aria-current="page"' : '' ?>>
                    <span class="icono" aria-hidden="true"><?= e($iconos[$k]) ?>
                    </span><?= e($v) ?>
                </a>
                <?php
                    endforeach;
                    ?>
            </nav>
            <div class="lateral-pie">
                <span class="estado-punto"></span> Intranet municipal<small>Información que nos
                    conecta.</small>
                <a href="https://www.muniniquen.cl/" target="_blank" rel="noopener noreferrer">Sitio municipal
                    ↗</a>
            </div>
        </aside>
        <div class="contenido">
            <header class="barra">
                <div class="ruta">
                    <button class="menu" type="button" data-menu aria-controls="navegacion"
                        aria-expanded="false" aria-label="Mostrar navegación">☰</button>
                    <span>Intranet municipal</span>
                    <span class="muted">/</span>
                    <strong><?= e($titulo) ?>
                    </strong>
                </div>
                <div class="sesion">
                    <span class="avatar"><?= e(mb_substr($actual['nombre'], 0, 1)) ?>
                    </span>
                    <div>
                        <strong><?= e($actual['nombre']) ?>
                        </strong>
                        <small><?= e($actual['rol']) ?> · <?= e(formatear_rut($actual['rut'])) ?>
                        </small>
                    </div>
                    <form method="post"><?= csrf() ?>
                        <input type="hidden" name="accion" value="salir">
                        <button class="boton-texto" aria-label="Cerrar sesión">Salir ↗</button>
                    </form>
                </div>
            </header>
            <main id="principal">
                <?php if (isset($_SESSION['aviso'])):

                        [$mensaje, $tipo] = $_SESSION['aviso'];
                        unset($_SESSION['aviso']);
                        ?>
                <div class="alerta <?= e($tipo) ?>" role="status"><?= e($mensaje) ?>
                </div>
                <?php
                    endif; ?>
                <?php
                    try {
                        require_once __DIR__ . '/modulos/' . $modulo . '.php';
                        $mostrar_modulo = 'mostrar_' . $modulo;
                        $mostrar_modulo($actual, $modulos);
                    } catch (Throwable $ex) {
                        error_log((string) $ex); ?>
                <div class="alerta error" role="alert">
                    <?= e(
                            $ex instanceof RuntimeException && !($ex instanceof PDOException)
                                ? $ex->getMessage()
                                : 'No se pudo cargar la información. Inténtalo nuevamente.',
                        ) ?>
                </div>
                <?php
                    }
                    unset($_SESSION['formulario']);
                    ?>
            </main>
            <footer class="pie">
                <span>© <?= date('Y') ?> <?= e(parametro('institucion', 'Municipalidad de Ñiquén')) ?>
                </span>
                <span>Uso interno · Región de Ñuble</span>
            </footer>
        </div>
    </body>

</html>
<?php
}
