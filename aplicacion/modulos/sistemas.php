<?php
function mostrar_sistemas(array $actual, array $modulos): void
{
    if (isset($_GET['nuevo']) || isset($_GET['editar'])) {
        exigir('sistemas');
        $id = (int) ($_GET['editar'] ?? 0);
        $s = $id ? consulta('SELECT * FROM sistemas_enlaces WHERE id=?', [$id])->fetch() : ['activo' => 1];
        if ($id && !$s) {
            throw new RuntimeException('Sistema no encontrado.');
        }
        encabezado(
            $id ? 'Editar acceso' : 'Nuevo acceso',
            'Conecta al equipo con sus herramientas de trabajo.',
        );
        echo '<section class="tarjeta editor">';
        comenzar_formulario('sistema', $id);
        campo('nombre', 'Nombre', $s, 'text', 140);
        campo('descripcion', 'Descripción', $s, 'textarea', 500);
        campo('url', 'Dirección web', $s, 'url', 1000);
        campo('categoria', 'Categoría', $s, 'text', 80);
        echo '<label class="check"><input type="checkbox" name="activo" ' .
            (!empty($s['activo']) ? 'checked' : '') .
            '> Acceso visible</label>';
        terminar_formulario('sistemas');
        echo '</section>';
        return;
    }
    encabezado(
        'Sistemas municipales',
        'Tus plataformas de trabajo, a un clic de distancia.',
        permiso('sistemas') ? 'Agregar sistema' : '',
        'index.php?modulo=sistemas&nuevo=1',
    );
    $categorias = consulta(
        'SELECT DISTINCT categoria FROM sistemas_enlaces WHERE activo=1 ORDER BY categoria',
    )->fetchAll(PDO::FETCH_COLUMN);
    filtros('sistemas', 'Buscar una plataforma…', $categorias);
    $cond = permiso('sistemas') ? '1=1' : 'activo=1';
    $datos = [];
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    if ($q !== '') {
        $cond .= ' AND (nombre LIKE ? OR descripcion LIKE ?)';
        $datos = ['%' . $q . '%', '%' . $q . '%'];
    }
    if (!empty($_GET['categoria'])) {
        $cond .= ' AND categoria=?';
        $datos[] = $_GET['categoria'];
    }
    [$filas, $total, $pagina, $paginas] = pagina_datos('sistemas_enlaces', $cond, $datos, 'nombre');
    if (!$filas) {
        vacio(
            'No se encontraron plataformas',
            'Prueba otra búsqueda o solicita agregar un acceso a Informática.',
        );
    }
    echo '<div class="rejilla">';
    foreach ($filas as $s): ?>
<article class="tarjeta sistema">
    <div class="persona-cabecera">
        <span class="cuadro-icono">▦</span>
        <span class="insignia"><?= e($s['categoria']) ?>
        </span>
    </div>
    <h2><?= e($s['nombre']) ?>
    </h2>
    <p><?= e($s['descripcion']) ?>
    </p>
    <?php if (!$s['activo']): ?>
    <span class="insignia">Oculto</span>
    <?php endif; ?>
    <div class="acciones">
        <a class="boton secundario" href="<?= e(
                $s['url'],
            ) ?>" target="_blank" rel="noopener noreferrer">Abrir
            plataforma ↗</a>
        <?php if (permiso('sistemas')): ?>
        <a href="index.php?modulo=sistemas&editar=<?= $s['id'] ?>">Editar</a>
        <?php endif; ?>
    </div>
</article>
<?php endforeach;
    echo '</div>';
    paginacion($total, $pagina, $paginas);
}
