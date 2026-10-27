<?php
function mostrar_soporte(array $actual, array $modulos): void
{
    if (isset($_GET['nuevo'])) {

        encabezado(
            'Nueva solicitud',
            'Cuéntanos qué necesitas. El equipo de Informática dará seguimiento a tu caso.',
        );
        echo '<section class="tarjeta editor">';
        comenzar_formulario('ticket');
        campo('asunto', 'Asunto');
        echo '<div class="campos">';
        selector('categoria', 'Categoría', opciones(CATEGORIAS));
        selector('prioridad', 'Prioridad', opciones(PRIORIDADES), [
            'prioridad' => 'Media',
        ]);
        echo '</div>';
        campo('descripcion', 'Descripción del problema', [], 'textarea', 10000);
        ?>
<p class="muted">Solicitante: <?= e($actual['nombre']) ?> · RUT <?= e(
     formatear_rut($actual['rut']),
 ) ?> · Unidad: <?= e($actual['unidad']) ?>. Evita incluir
    contraseñas en tu solicitud.</p>
<div class="acciones">
    <button>Enviar solicitud →</button>
    <a href="index.php?modulo=soporte">Cancelar</a>
</div>
</form>
</section>
<?php return;
    }
    if (isset($_GET['ver'])) {

        $t = ticket_visible((int) $_GET['ver']);
        encabezado(folio((int) $t['id']), $t['asunto']);
        ?>
<div class="dos-columnas detalle-ticket">
    <section>
        <article class="tarjeta">
            <div class="acciones">
                <span class="insignia"><?= e($t['estado']) ?>
                </span>
                <span class="insignia prioridad"><?= e($t['prioridad']) ?>
                </span>
            </div>
            <h2><?= e($t['asunto']) ?>
            </h2>
            <p class="contenido-texto"><?= e($t['descripcion']) ?>
            </p>
            <dl>
                <dt>Solicitante</dt>
                <dd><?= e($t['solicitante']) ?><small>RUT <?= e(
    formatear_rut($t['solicitante_rut'] ?? 'No registrado en el historial anterior'),
) ?></small>
                </dd>
                <dt>Unidad</dt>
                <dd><?= e($t['unidad']) ?>
                </dd>
                <dt>Categoría</dt>
                <dd><?= e($t['categoria']) ?>
                </dd>
                <dt>Responsable</dt>
                <dd><?= e($t['responsable'] ?: 'Sin asignar') ?>
                </dd>
                <dt>Creación</dt>
                <dd><?= fecha($t['creado']) ?>
                </dd>
                <dt>Última actualización</dt>
                <dd><?= fecha($t['actualizado']) ?>
                </dd>
                <dt>Resolución</dt>
                <dd><?= fecha($t['resuelto']) ?>
                </dd>
            </dl>
            <?php if ($t['solucion']): ?>
            <div class="solucion">
                <strong>Solución registrada</strong>
                <p class="contenido-texto"><?= e($t['solucion']) ?>
                </p>
            </div>
            <?php endif; ?>
        </article>
        <div class="titulo-seccion">
            <h2>Actividad e historial</h2>
        </div>
        <section class="tarjeta historial">
            <?php foreach (
                    consulta(
                        'SELECT h.*,COALESCE(h.autor_nombre,u.nombre) nombre,COALESCE(h.autor_rut,u.rut) rut FROM ticket_historial h JOIN usuarios u ON u.id=h.usuario_id WHERE h.ticket_id=? ORDER BY h.id DESC',
                        [$t['id']],
                    )->fetchAll()
                    as $h
                ): ?>
            <article>
                <span class="avatar"><?= e(mb_substr($h['nombre'], 0, 1)) ?>
                </span>
                <div>
                    <strong><?= e($h['nombre']) ?><small>RUT <?= e(
    formatear_rut($h['rut'] ?? 'No registrado en el historial anterior'),
) ?></small>
                    </strong>
                    <small class="muted"><?= fecha($h['creado']) ?>
                    </small>
                    <p class="contenido-texto"><?= e($h['detalle']) ?>
                    </p>
                </div>
            </article>
            <?php endforeach; ?>
        </section>
    </section>
    <aside>
        <?php if (permiso('soporte')): ?>
        <section class="tarjeta">
            <h2>Gestionar solicitud</h2>
            <p class="muted">Nuevo → Asignado → En proceso → Esperando usuario → Resuelto → Cerrado. Se
                permite reabrir
                a En proceso.</p>
            <?php
                comenzar_formulario('gestionar_ticket', (int) $t['id']);
                selector('estado', 'Estado', opciones(ESTADOS), $t);
                selector('prioridad', 'Prioridad', opciones(PRIORIDADES), $t);
                $responsables = consulta(
                    'SELECT u.id,u.nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.activo=1 AND u.rut IS NOT NULL AND (r.nombre="Administrador" OR EXISTS(SELECT 1 FROM roles_permisos p WHERE p.rol_id=r.id AND p.modulo="soporte")) ORDER BY u.nombre',
                )->fetchAll();
                selector(
                    'responsable_id',
                    'Responsable',
                    [0 => 'Sin asignar'] + array_column($responsables, 'nombre', 'id'),
                    $t,
                );
                campo('solucion', 'Solución', $t, 'textarea', 5000, false);
                ?>
            <button>Actualizar solicitud</button>
            </form>
        </section>
        <?php endif; ?>
        <?php if ($t['estado'] !== 'Cerrado'): ?>
        <section class="tarjeta">
            <h2>Agregar comentario</h2>
            <?php
                comenzar_formulario('comentario', (int) $t['id']);
                campo('comentario', 'Mensaje', [], 'textarea', 5000);
                ?>
            <button>Enviar comentario</button>
            </form>
        </section>
        <?php endif; ?>
        <a class="boton secundario" href="index.php?modulo=soporte">← Volver a solicitudes</a>
    </aside>
</div>
<?php return;
    }
    encabezado(
        'Mesa de ayuda',
        permiso('soporte')
            ? 'Gestiona las solicitudes y acompaña al equipo municipal.'
            : 'Registra tus solicitudes personales y consulta su avance.',
        'Nueva solicitud',
        'index.php?modulo=soporte&nuevo=1',
    );
    $cond = permiso('soporte') ? '1=1' : 't.solicitante_id=' . (int) $actual['id'];
    $datos = [];
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 180);
    if ($q !== '') {
        $cond .= ' AND (t.asunto LIKE ? OR u.nombre LIKE ? OR t.unidad LIKE ? OR t.id=?)';
        $datos = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', (int) preg_replace('/^SOP-0*/i', '', $q)];
    }
    foreach (['estado', 'prioridad'] as $f) {
        if (!empty($_GET[$f])) {
            $cond .= ' AND t.' . $f . '=?';
            $datos[] = $_GET[$f];
        }
    }
    ?>
<form method="get" class="filtros">
    <input type="hidden" name="modulo" value="soporte">
    <label class="busqueda">
        <span class="sr">Buscar solicitudes</span>
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="180"
            placeholder="Buscar por folio, asunto, solicitante o unidad…">
    </label>
    <?php
        selector('estado', 'Estado', ['' => 'Todos'] + opciones(ESTADOS), $_GET);
        selector('prioridad', 'Prioridad', ['' => 'Todas'] + opciones(PRIORIDADES), $_GET);
        ?>
    <button>Filtrar</button>
    <a href="index.php?modulo=soporte">Limpiar</a>
</form>
<?php
    $tabla =
        'tickets t JOIN usuarios u ON u.id=t.solicitante_id LEFT JOIN usuarios r ON r.id=t.responsable_id';
    $total = (int) consulta('SELECT COUNT(*) FROM ' . $tabla . ' WHERE ' . $cond, $datos)->fetchColumn();
    $paginas = max(1, (int) ceil($total / 50));
    $pagina = min($paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
    $filas = consulta(
        'SELECT t.*,COALESCE(t.solicitante_nombre,u.nombre) solicitante,COALESCE(t.solicitante_rut,u.rut) solicitante_rut,r.nombre responsable,r.rut responsable_rut FROM ' .
            $tabla .
            ' WHERE ' .
            $cond .
            ' ORDER BY t.creado DESC,t.id DESC LIMIT 50 OFFSET ' .
            ($pagina - 1) * 50,
        $datos,
    )->fetchAll();
    ?>
<div class="tarjeta tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Solicitud</th>
                <th>Solicitante / unidad</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Responsable</th>
                <th>Actualización</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filas as $t): ?>
            <tr>
                <td>
                    <a href="index.php?modulo=soporte&ver=<?= $t['id'] ?>">
                        <strong><?= folio((int) $t['id']) ?>
                        </strong>
                        <span><?= e($t['asunto']) ?>
                        </span>
                    </a>
                </td>
                <td><?= e($t['solicitante']) ?><small>RUT <?= e(
    formatear_rut($t['solicitante_rut'] ?? 'No registrado en el historial anterior'),
) ?></small>
                    <small><?= e($t['unidad']) ?>
                    </small>
                </td>
                <td>
                    <span class="insignia prioridad"><?= e($t['prioridad']) ?>
                    </span>
                </td>
                <td>
                    <span class="insignia"><?= e($t['estado']) ?>
                    </span>
                </td>
                <td><?= e($t['responsable'] ?: 'Sin asignar') ?>
                </td>
                <td><?= fecha($t['actualizado']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (!$filas) {
            vacio(
                'No hay solicitudes para mostrar',
                'Crea una solicitud o modifica los filtros de búsqueda.',
            );
        } ?>
</div>
<?php paginacion($total, $pagina, $paginas, true); ?>
<?php
}
