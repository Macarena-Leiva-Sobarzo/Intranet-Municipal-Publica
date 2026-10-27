<?php
function mostrar_administracion(array $actual, array $modulos): void
{
    exigir('administracion');
    if (isset($_GET['nuevo']) || isset($_GET['editar'])) {
        $id = (int) ($_GET['editar'] ?? 0);
        $u = $id ? consulta('SELECT * FROM usuarios WHERE id=?', [$id])->fetch() : ['activo' => 1];
        if ($id && !$u) {
            throw new RuntimeException('Funcionario no encontrado.');
        }
        encabezado(
            $id ? 'Editar funcionario' : 'Registrar funcionario',
            'Registra la identidad y el rol de la persona. Para ingresar utilizará su RUT y el PIN de ese rol.',
        );
        echo '<section class="tarjeta editor">';
        comenzar_formulario('usuario', $id);
        echo '<div class="campos">';
        campo('nombres', 'Nombres', $u, 'text', 120);
        campo('apellidos', 'Apellidos', $u, 'text', 120);
        campo('rut', 'RUT', array_replace($u, ['rut' => formatear_rut($u['rut'] ?? '')]), 'text', 12);
        campo('correo', 'Correo institucional', $u, 'email', 180, false);
        campo('unidad', 'Unidad', $u, 'text', 120, false);
        campo('cargo', 'Cargo', $u, 'text', 120, false);
        campo('telefono', 'Teléfono / anexo', $u, 'tel', 40, false);
        selector(
            'rol_id',
            'Perfil',
            array_column(consulta('SELECT id,nombre FROM roles')->fetchAll(), 'nombre', 'id'),
            $u,
        );
        echo '</div><label class="check"><input type="checkbox" name="activo" ' .
            (!empty($u['activo']) ? 'checked' : '') .
            '> Persona activa con acceso y visible en directorio</label>';
        terminar_formulario('administracion');
        echo '</section>';
        return;
    }
    encabezado(
        'Administración',
        'Personas, perfiles y configuración de la intranet.',
        'Registrar funcionario',
        'index.php?modulo=administracion&nuevo=1',
    );
    ?>
<section class="tarjeta importacion-personas">
    <h2>Importar personas desde Excel o PDF</h2>
    <p><a class="boton secundario" href="plantilla_personas.php">Descargar plantilla Excel ↓</a></p>
    <p>Sube un archivo por carga. Puedes repetir cargas tantas veces como necesites. Los RUT ya registrados se
        omiten, sin cambiar su nombre ni su rol.</p>
    <p>Encabezados obligatorios: <strong>Nombres | Apellidos | RUT | Rol</strong>. Roles: Administrador,
        Informática, Editor o Funcionario. Se admiten XLSX, XLS y PDF con texto seleccionable, hasta 5 MB y
        5000 filas.</p>
    <p>En PDF utiliza una tabla con columnas separadas o líneas con punto y coma. Los PDF escaneados o
        protegidos deben convertirse previamente a un listado con texto.</p>
    <form method="post" enctype="multipart/form-data"><?= csrf() ?>
        <input type="hidden" name="accion" value="importar_personas">
        <label>Archivo de personas<input type="file" name="archivo_personas" accept=".xlsx,.xls,.pdf"
                required></label>
        <button>Importar personas</button>
    </form>
    <?php if (isset($_SESSION['resultado_importacion'])):

            $resumen = $_SESSION['resultado_importacion'];
            unset($_SESSION['resultado_importacion']);
            ?>
    <div class="alerta" role="status"><?= (int) $resumen[
                'registrados'
            ] ?> registradas · <?= (int) $resumen['omitidos'] ?> RUT repetidos omitidos ·
        <?= (int) $resumen[
     'invalidos'
 ] ?> filas inválidas.</div>
    <?php if (
                $resumen['errores']
            ): ?>
    <details>
        <summary>Ver filas inválidas (hasta 50 detalles)</summary>
        <ul><?php foreach (
    $resumen['errores']
    as $detalle
): ?>
            <li><?= e($detalle) ?></li><?php endforeach; ?>
        </ul>
    </details><?php endif; ?>
    <?php
        endif; ?>
</section>
<?php
    filtros('administracion', 'Buscar por nombre, RUT, correo, unidad o cargo…');
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    $cond =
        $q !== '' ? '(nombre LIKE ? OR rut LIKE ? OR correo LIKE ? OR unidad LIKE ? OR cargo LIKE ?)' : '1=1';
    $datos = $q !== '' ? array_fill(0, 5, '%' . $q . '%') : [];
    $cond = '(' . $cond . ') AND rut IS NOT NULL';
    [$filas, $total, $pagina, $paginas] = pagina_datos('usuarios', $cond, $datos, 'nombre, id', 50);
    $roles = consulta('SELECT * FROM roles ORDER BY id')->fetchAll();
    $nombres = array_column($roles, 'nombre', 'id');
    ?>
<section class="tarjeta tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Funcionario</th>
                <th>Unidad / cargo</th>
                <th>Perfil</th>
                <th>Estado</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $u): ?>
            <tr>
                <td>
                    <strong><?= e($u['nombre']) ?>
                    </strong>
                    <small>RUT <?= e(formatear_rut($u['rut'])) ?> · <?= e($u['correo'] ?? '') ?>
                    </small>
                </td>
                <td><?= e($u['unidad']) ?>
                    <small><?= e($u['cargo']) ?>
                    </small>
                </td>
                <td><?= e($nombres[$u['rol_id']] ?? '') ?>
                </td>
                <td>
                    <span class="insignia"><?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td>
                    <a href="index.php?modulo=administracion&editar=<?= $u['id'] ?>">Editar →</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php paginacion($total, $pagina, $paginas, true); ?>
<div class="titulo-seccion">
    <div>
        <h2>Perfiles y permisos</h2>
        <p>Las casillas autorizan la administración de cada módulo. Todos los perfiles consultan información y
            crean sus
            propios tickets.</p>
    </div>
</div>
<div class="rejilla perfiles">
    <?php foreach ($roles as $r):
            $permisos = consulta('SELECT modulo FROM roles_permisos WHERE rol_id=?', [$r['id']])->fetchAll(
                PDO::FETCH_COLUMN,
            ); ?>
    <section class="tarjeta">
        <h3><?= e($r['nombre']) ?>
        </h3>
        <form method="post"
            data-confirmar="¿Guardar los permisos y el PIN de este perfil? Cambiar el PIN cerrará sus sesiones activas.">
            <?= csrf() ?>
            <input type="hidden" name="accion" value="perfil">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <?php if ($r['nombre'] === 'Administrador'): ?>
            <p class="muted">Acceso completo a todos los módulos y a la configuración.</p>
            <?php else:foreach (
                        [
                            'noticias' => 'Gestionar noticias',
                            'documentos' => 'Gestionar documentos',
                            'directorio' => 'Editar directorio',
                            'sistemas' => 'Gestionar sistemas',
                            'soporte' => 'Gestionar todos los tickets',
                        ]
                        as $k => $etiqueta
                    ): ?>
            <label class="check">
                <input type="checkbox" name="permisos[]" value="<?= e($k) ?>"
                    <?= in_array(
    $k,
    $permisos,
    true,
)
    ? 'checked'
    : '' ?>><?= e($etiqueta) ?>
            </label>
            <?php endforeach;endif; ?>
            <label>Nuevo PIN compartido
                <input type="password" name="clave" inputmode="numeric" pattern="[0-9]{6}" minlength="6"
                    maxlength="6" title="Ingresa exactamente 6 dígitos" autocomplete="new-password"
                    placeholder="Dejar vacío para conservar">
            </label>
            <p class="muted">Exactamente 6 dígitos y distinto al de los otros perfiles.</p>
            <button>Guardar perfil</button>
        </form>
    </section>
    <?php
        endforeach; ?>
</div>
<div class="dos-columnas">
    <section class="tarjeta">
        <h2>Configuración institucional</h2>
        <?php
            comenzar_formulario('parametros');
            campo(
                'institucion',
                'Nombre de institución',
                ['institucion' => parametro('institucion')],
                'text',
                120,
            );
            campo('mensaje', 'Mensaje de bienvenida', ['mensaje' => parametro('mensaje')], 'textarea', 500);
            campo('soporte', 'Información de soporte', ['soporte' => parametro('soporte')], 'textarea', 500);
            ?>
        <button>Guardar configuración</button>
        </form>
    </section>
    <section class="tarjeta">
        <h2>Respaldo y continuidad</h2>
        <p>Descarga una copia SQL de la base de datos. Incluye información interna y hashes de los PIN:
            guárdala en un
            lugar protegido.</p>
        <form method="post" action="respaldar.php"><?= csrf() ?>
            <button>Descargar respaldo SQL ↓</button>
        </form>
        <p>Para restaurar: crea una base vacía, importa el respaldo desde phpMyAdmin y ajusta la conexión en
            <code>
                config/base_datos.php</code>.</p>
        <a href="index.php?modulo=ayuda">Consultar ayuda del sistema →</a>
    </section>
</div>
<div class="titulo-seccion" id="bitacora">
    <h2>Bitácora de actividad</h2>
</div>
<section class="tarjeta tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Persona</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php
                $totalAuditoria = (int) consulta('SELECT COUNT(*) FROM auditoria')->fetchColumn();
                $paginasAuditoria = max(1, (int) ceil($totalAuditoria / 50));
                $paginaAuditoria = min(
                    max(1, (int) ($_GET['pagina_bitacora'] ?? 1)),
                    $paginasAuditoria,
                );
                foreach (
                    consulta(
                        'SELECT a.*,COALESCE(a.autor_nombre,u.nombre) nombre,COALESCE(a.autor_rut,u.rut) rut FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id ORDER BY a.id DESC LIMIT 50 OFFSET ' .
                            ($paginaAuditoria - 1) * 50,
                    )->fetchAll()
                    as $a
                ): ?>
            <tr>
                <td><?= fecha($a['creado']) ?>
                </td>
                <td><?= e($a['nombre'] ?? 'Sistema') ?><small><?= e(
    formatear_rut($a['rut'] ?? 'Sin identidad individual histórica'),
) ?></small>
                </td>
                <td><?= e(formatear_ruts_en_texto($a['accion'])) ?>
                </td>
            </tr>
            <?php endforeach;
                ?>
        </tbody>
    </table>
    <?php paginacion($totalAuditoria, $paginaAuditoria, $paginasAuditoria, true, 'pagina_bitacora', 'bitacora'); ?>
</section>
<?php
}
