<?php
function mostrar_directorio(array $actual, array $modulos): void
{
    if (isset($_GET['editar'])) {
        exigir('directorio');
        $id = (int) $_GET['editar'];
        $u = consulta('SELECT * FROM usuarios WHERE id=?', [$id])->fetch();
        if (!$u) {
            throw new RuntimeException('Funcionario no encontrado.');
        }
        encabezado('Editar contacto', $u['nombre']);
        echo '<section class="tarjeta editor">';
        comenzar_formulario('directorio', $id);
        campo('unidad', 'Unidad', $u, 'text', 120);
        campo('cargo', 'Cargo', $u, 'text', 120);
        campo('telefono', 'Teléfono / anexo', $u, 'tel', 40, false);
        terminar_formulario('directorio');
        echo '</section>';
        return;
    }
    encabezado('Nuestro equipo', 'Encuentra personas, unidades y contactos institucionales.');
    $unidades = consulta(
        'SELECT DISTINCT unidad FROM usuarios WHERE activo=1 AND rut IS NOT NULL ORDER BY unidad',
    )->fetchAll(PDO::FETCH_COLUMN);
    filtros('directorio', 'Buscar por nombre, unidad o cargo…', $unidades, 'unidad');
    $cond = 'activo=1 AND rut IS NOT NULL';
    $datos = [];
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    if ($q !== '') {
        $cond .= ' AND (nombre LIKE ? OR unidad LIKE ? OR cargo LIKE ?)';
        $datos = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
    }
    if (!empty($_GET['unidad'])) {
        $cond .= ' AND unidad=?';
        $datos[] = $_GET['unidad'];
    }
    [$filas, $total, $pagina, $paginas] = pagina_datos('usuarios', $cond, $datos, 'nombre, id', 50);
    if (!$filas) {
        vacio('No se encontraron contactos', 'Prueba con otro nombre, unidad o cargo.');
    }
    echo '<div class="rejilla">';
    foreach ($filas as $u): ?>
<article class="tarjeta persona">
    <div class="persona-cabecera">
        <span class="avatar grande"><?= e(mb_substr($u['nombre'], 0, 1)) ?>
        </span>
        <span class="insignia"><?= e($u['unidad']) ?>
        </span>
    </div>
    <h2><?= e($u['nombre']) ?>
    </h2>
    <p><?= e($u['cargo']) ?>
    </p>
    <div class="contacto">
        <?php if ($u['correo']): ?><a href="mailto:<?= e($u['correo']) ?>">✉ <?= e($u['correo']) ?>
        </a><?php endif; ?>
        <?php if ($u['telefono']): ?>
        <a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $u['telefono'])) ?>">☏ <?= e($u['telefono']) ?>
        </a>
        <?php else: ?>
        <small class="muted">Sin teléfono registrado</small>
        <?php endif; ?>
    </div>
    <?php if (permiso('directorio')): ?>
    <a href="index.php?modulo=directorio&editar=<?= $u['id'] ?>">Editar contacto →</a>
    <?php endif; ?>
</article>
<?php endforeach;
    echo '</div>';
    paginacion($total, $pagina, $paginas, true);
}
