<?php
function mostrar_noticias(array $actual, array $modulos): void
{
    if (isset($_GET['editar']) || isset($_GET['nuevo'])) {
        exigir('noticias');
        $id = (int) ($_GET['editar'] ?? 0);
        $n = $id ? consulta('SELECT * FROM noticias WHERE id=?', [$id])->fetch() : [];
        if ($id && !$n) {
            throw new RuntimeException('Noticia no encontrada.');
        }
        encabezado(
            $id ? 'Editar noticia' : 'Nueva noticia',
            'Comparte información relevante con el equipo municipal.',
        );
        echo '<section class="tarjeta editor">';
        comenzar_formulario('noticia', $id);
        campo('titulo', 'Título', $n);
        campo('contenido', 'Contenido', $n, 'textarea', 20000);
        echo '<div class="campos">';
        campo('categoria', 'Categoría', $n, 'text', 80);
        selector('estado', 'Estado', opciones(['Borrador', 'Publicada', 'Archivada']), $n);
        echo '</div><label class="check"><input type="checkbox" name="destacada" ' .
            (!empty($n['destacada']) ? 'checked' : '') .
            '> Destacar en el inicio</label>';
        terminar_formulario('noticias');
        echo '</section>';
        return;
    }
    if (isset($_GET['ver'])) {

        $n = consulta(
            'SELECT n.*,u.nombre autor,u.rut autor_rut FROM noticias n JOIN usuarios u ON u.id=n.autor_id WHERE n.id=?',
            [(int) $_GET['ver']],
        )->fetch();
        if (!$n || ($n['estado'] !== 'Publicada' && !permiso('noticias'))) {
            throw new RuntimeException('Noticia no disponible.');
        }
        encabezado($n['titulo'], $n['categoria'] . ' · ' . fecha($n['publicada'] ?: $n['creada']));
        ?>
<article class="tarjeta lectura">
    <p class="contenido-texto"><?= e($n['contenido']) ?>
    </p>
    <p class="muted">Publicado por <?= e($n['autor']) ?> · RUT <?= e(
     formatear_rut($n['autor_rut'] ?? 'No registrado en el historial anterior'),
 ) ?> · <?= e($n['estado']) ?>
    </p>
    <a href="index.php?modulo=noticias">← Volver a noticias</a>
</article>
<?php return;
    }
    encabezado(
        'Noticias internas',
        'Mantente al día con la información de nuestro municipio.',
        permiso('noticias') ? 'Nueva noticia' : '',
        'index.php?modulo=noticias&nuevo=1',
    );
    $cond = permiso('noticias') ? '1=1' : 'estado="Publicada"';
    $datos = [];
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    if ($q !== '') {
        $cond .= ' AND (titulo LIKE ? OR contenido LIKE ?)';
        $datos = ['%' . $q . '%', '%' . $q . '%'];
    }
    if (!empty($_GET['estado']) && permiso('noticias')) {
        $cond .= ' AND estado=?';
        $datos[] = $_GET['estado'];
    }
    filtros(
        'noticias',
        'Buscar noticias…',
        permiso('noticias') ? ['Borrador', 'Publicada', 'Archivada'] : [],
        'estado',
    );
    [$filas, $total, $pagina, $paginas] = pagina_datos(
        'noticias',
        $cond,
        $datos,
        'destacada DESC,creada DESC',
    );
    if (!$filas) {
        vacio(
            'No hay noticias para mostrar',
            'Cuando se publiquen contenidos, podrás consultarlos en este espacio.',
        );
    }
    echo '<div class="rejilla">';
    foreach ($filas as $n): ?>
<article class="tarjeta noticia">
    <div class="noticia-visual">
        <span>▤</span>
        <small><?= e($n['categoria']) ?>
        </small>
    </div>
    <div class="noticia-cuerpo">
        <span class="insignia"><?= e($n['estado']) ?> <?= $n['destacada'] ? '· Destacada' : '' ?>
        </span>
        <h2>
            <a href="index.php?modulo=noticias&ver=<?= $n['id'] ?>"><?= e($n['titulo']) ?>
            </a>
        </h2>
        <p><?= e(mb_strimwidth($n['contenido'], 0, 180, '…')) ?>
        </p>
        <small class="muted"><?= fecha($n['publicada'] ?: $n['creada']) ?>
        </small>
        <div class="acciones">
            <a href="index.php?modulo=noticias&ver=<?= $n['id'] ?>">Leer más →</a>
            <?php if (permiso('noticias')): ?>
            <a href="index.php?modulo=noticias&editar=<?= $n['id'] ?>">Editar</a>
            <?php if ($n['estado'] !== 'Archivada'): ?>
            <form method="post"
                data-confirmar="¿Archivar esta noticia? Dejará de ser visible para los funcionarios.">
                <?= csrf() ?>
                <input type="hidden" name="accion" value="archivar_noticia">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button class="boton-texto">Archivar</button>
            </form>
            <?php endif;endif; ?>
        </div>
    </div>
</article>
<?php endforeach;
    echo '</div>';
    paginacion($total, $pagina, $paginas);
}
