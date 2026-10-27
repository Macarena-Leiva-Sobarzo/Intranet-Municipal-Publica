<?php
function mostrar_inicio(array $actual, array $modulos): void
{
    $totalNoticias = (int) consulta('SELECT COUNT(*) FROM noticias WHERE estado="Publicada"')->fetchColumn();
    $totalDocumentos = (int) consulta('SELECT COUNT(*) FROM documentos')->fetchColumn();
    $totalPersonas = (int) consulta(
        'SELECT COUNT(*) FROM usuarios WHERE activo=1 AND rut IS NOT NULL',
    )->fetchColumn();
    $where = permiso('soporte') ? '1=1' : 'solicitante_id=' . (int) $actual['id'];
    $totalTickets = (int) consulta(
        'SELECT COUNT(*) FROM tickets WHERE ' . $where . ' AND estado NOT IN ("Resuelto","Cerrado")',
    )->fetchColumn();
    $noticias = consulta(
        'SELECT * FROM noticias WHERE estado="Publicada" ORDER BY destacada DESC,publicada DESC LIMIT 3',
    )->fetchAll();
    ?>
<div class="saludo">
    <div>
        <span class="etiqueta">TU ESPACIO MUNICIPAL</span>
        <h1>Hola, <?= e($actual['nombre']) ?> <span class="saludo-punto">.</span>
        </h1>
        <p>Todo lo que necesitas para tu jornada, en un solo lugar.</p>
    </div>
    <span class="fecha-hoy">
        <?= e(
                ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][(int) date('w')],
            ) ?>,
        <?= date('d/m/Y') ?>
    </span>
</div>
<section class="bienvenida">
    <div>
        <span class="etiqueta clara">CONECTADOS PARA SERVIR MEJOR</span>
        <h2>El progreso lo
            <br>construimos juntos.
        </h2>
        <p><?= e(parametro('mensaje')) ?>
        </p>
        <a class="boton naranja" href="index.php?modulo=sistemas">Explorar sistemas municipales ↗</a>
    </div>
    <div class="identidad-municipal">
        <img src="imagenes/logo_municipalidad.png" width="560" height="217"
            alt="Ilustre Municipalidad de Ñiquén. ¡El progreso lo construimos juntos!">
        <span>AL SERVICIO DE NUESTRA COMUNA</span>
    </div>
    <span class="hero-pie">ILUSTRE MUNICIPALIDAD DE ÑIQUÉN · REGIÓN DE ÑUBLE</span>
</section>
<div class="estadisticas">
    <?php foreach (
            [
                ['noticias', '▤', $totalNoticias, 'Noticias publicadas'],
                ['documentos', '▱', $totalDocumentos, 'Documentos disponibles'],
                ['directorio', '♙', $totalPersonas, 'Personas en el directorio'],
                [
                    'soporte',
                    '◉',
                    $totalTickets,
                    permiso('soporte') ? 'Solicitudes pendientes' : 'Mis solicitudes pendientes',
                ],
            ]
            as [$enlace, $icono, $numero, $texto]
        ): ?>
    <a class="estadistica tarjeta" href="index.php?modulo=<?= $enlace ?>">
        <span class="cuadro-icono"><?= $icono ?>
        </span>
        <div>
            <strong><?= $numero ?>
            </strong>
            <small><?= e($texto) ?>
            </small>
        </div>
        <span class="flecha">↗</span>
    </a>
    <?php endforeach; ?>
</div>
<div class="titulo-seccion">
    <div>
        <h2>Accesos de cada día</h2>
        <p>Herramientas que te acompañan en tu trabajo.</p>
    </div>
</div>
<div class="accesos-rapidos">
    <?php foreach (
            [
                ['directorio', '♙', 'Encuentra a tu equipo', 'Contactos y unidades municipales'],
                ['documentos', '▱', 'Documentos a mano', 'Formularios y material de consulta'],
                ['soporte', '◉', 'Estamos para ayudarte', 'Crea y sigue una solicitud de soporte'],
            ]
            as [$enlace, $icono, $texto, $descripcion]
        ): ?>
    <a class="tarjeta acceso-rapido" href="index.php?modulo=<?= $enlace ?>">
        <span class="cuadro-icono"><?= $icono ?>
        </span>
        <h3><?= e($texto) ?>
        </h3>
        <p><?= e($descripcion) ?>
        </p>
        <span class="enlace">Ir a <?= e($modulos[$enlace]) ?> →</span>
    </a>
    <?php endforeach; ?>
</div>
<div class="dos-columnas">
    <section>
        <div class="titulo-seccion">
            <h2>Lo que está pasando</h2>
            <a href="index.php?modulo=noticias">Ver noticias →</a>
        </div>
        <?php
            if (!$noticias) {
                vacio('Aún no hay noticias', 'Las publicaciones internas aparecerán aquí.');
            }
            foreach ($noticias as $n): ?>
        <a class="tarjeta noticia-resumen" href="index.php?modulo=noticias&ver=<?= $n['id'] ?>">
            <span class="etiqueta"><?= e($n['categoria']) ?> <?= $n['destacada'] ? '· DESTACADA' : '' ?>
            </span>
            <h3><?= e($n['titulo']) ?>
            </h3>
            <p><?= e(mb_strimwidth($n['contenido'], 0, 145, '…')) ?>
            </p>
            <small><?= fecha($n['publicada']) ?> <span>Leer noticia →</span>
            </small>
        </a>
        <?php endforeach;
            ?>
    </section>
    <section>
        <div class="titulo-seccion">
            <h2>Tu mesa de ayuda</h2>
        </div>
        <div class="tarjeta tarjeta-soporte">
            <span class="icono-grande">◉</span>
            <h3>Un equipo para apoyarte.</h3>
            <p><?= e(parametro('soporte')) ?>
            </p>
            <a class="boton" href="index.php?modulo=soporte&nuevo=1">＋ Nueva solicitud</a>
            <a class="enlace" href="index.php?modulo=soporte">Consultar solicitudes →</a>
        </div>
    </section>
</div>
<?php
}
