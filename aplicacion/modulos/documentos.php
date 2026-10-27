<?php
function mostrar_documentos(array $actual, array $modulos): void
{
    if (isset($_GET['nuevo']) || isset($_GET['editar'])) {

        exigir('documentos');
        $id = (int) ($_GET['editar'] ?? 0);
        $d = $id ? consulta('SELECT * FROM documentos WHERE id=?', [$id])->fetch() : ['version' => '1.0'];
        if ($id && !$d) {
            throw new RuntimeException('Documento no encontrado.');
        }
        encabezado(
            $id ? 'Editar documento' : 'Subir documento',
            'Organiza el material de consulta del equipo municipal.',
        );
        echo '<section class="tarjeta editor">';
        comenzar_formulario('documento', $id, true);
        campo('titulo', 'Título', $d);
        campo('descripcion', 'Descripción', $d, 'textarea', 3000);
        echo '<div class="campos">';
        campo('categoria', 'Categoría', $d, 'text', 100);
        campo('version', 'Versión', $d, 'text', 30);
        echo '</div>';
        ?>
<label>Archivo <?= !$id ? '*' : '(opcional: reemplaza el actual)' ?>
    <input type="file" name="archivo" accept=".pdf,.png,.jpg,.jpeg,.docx,.xlsx,.csv" <?= !$id
            ? 'required'
            : '' ?>>
</label>
<p class="muted">PDF, imágenes, Word, Excel o CSV. Máximo 10 MB.
    <?= isset($d['nombre_original']) ? 'Actual: ' . e($d['nombre_original']) : '' ?>
</p>
<?php
    terminar_formulario('documentos');
    echo '</section>';
    return;

    }
    encabezado(
        'Documentos',
        'La información que necesitas, organizada y siempre a mano.',
        permiso('documentos') ? 'Subir documento' : '',
        'index.php?modulo=documentos&nuevo=1',
    );
    $categorias = consulta('SELECT DISTINCT categoria FROM documentos ORDER BY categoria')->fetchAll(
        PDO::FETCH_COLUMN,
    );
    filtros('documentos', 'Buscar por título o descripción…', $categorias);
    $cond = '1=1';
    $datos = [];
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    if ($q !== '') {
        $cond .= ' AND (titulo LIKE ? OR descripcion LIKE ?)';
        $datos = ['%' . $q . '%', '%' . $q . '%'];
    }
    if (!empty($_GET['categoria'])) {
        $cond .= ' AND categoria=?';
        $datos[] = $_GET['categoria'];
    }
    [$filas, $total, $pagina, $paginas] = pagina_datos('documentos', $cond, $datos, 'creado DESC');
    if (!$filas) {
        vacio(
            'No hay documentos para mostrar',
            'Los documentos publicados estarán disponibles para descargar aquí.',
        );
    }
    echo '<div class="rejilla">';
    foreach ($filas as $d): ?>
<article class="tarjeta documento">
    <div class="persona-cabecera">
        <span class="cuadro-icono">▱</span>
        <span class="insignia"><?= e($d['categoria']) ?>
        </span>
    </div>
    <h2><?= e($d['titulo']) ?>
    </h2>
    <p><?= e($d['descripcion']) ?>
    </p>
    <small class="muted">Versión <?= e($d['version']) ?> ·
        <?= e(strtoupper(pathinfo($d['archivo'], PATHINFO_EXTENSION))) ?> ·
        <?= number_format($d['tamano'] / 1024, 0, ',', '.') ?> KB</small>
    <div class="acciones">
        <a class="boton secundario" href="descargar.php?id=<?= $d['id'] ?>">Descargar ↓</a>
        <?php if (permiso('documentos')): ?>
        <a href="index.php?modulo=documentos&editar=<?= $d['id'] ?>">Editar</a>
        <form method="post"
            data-confirmar="¿Eliminar este documento y su archivo? Esta acción es definitiva.">
            <?= csrf() ?>
            <input type="hidden" name="accion" value="eliminar_documento">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button class="boton-texto peligro">Eliminar</button>
        </form>
        <?php endif; ?>
    </div>
</article>
<?php endforeach;
    echo '</div>';
    paginacion($total, $pagina, $paginas);
}
