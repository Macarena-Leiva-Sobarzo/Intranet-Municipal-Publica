<?php
declare(strict_types=1);
function procesar_accion(): void
{
    global $bd;
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    switch ($accion) {
        case 'salir':
            try {
                auditar('Cierre de sesión');
            } catch (Throwable $ex) {
                error_log((string) $ex);
            }
            $_SESSION = [];
            $cookie = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'],
            ]);
            session_destroy();
            ir('ingresar.php');
        case 'noticia':
            exigir('noticias');
            $titulo = requerido('titulo');
            $contenido = requerido('contenido', 20000);
            $categoria = requerido('categoria', 80);
            $estado = opcion('estado', ['Borrador', 'Publicada', 'Archivada']);
            $destacada = isset($_POST['destacada']) ? 1 : 0;
            if ($id) {
                consulta(
                    'UPDATE noticias SET titulo=?,contenido=?,categoria=?,estado=?,destacada=?,publicada=IF(?="Publicada",COALESCE(publicada,NOW()),publicada) WHERE id=?',
                    [$titulo, $contenido, $categoria, $estado, $destacada, $estado, $id],
                );
            } else {
                consulta(
                    'INSERT INTO noticias(titulo,contenido,categoria,estado,destacada,autor_id,publicada) VALUES(?,?,?,?,?,?,IF(?="Publicada",NOW(),NULL))',
                    [
                        $titulo,
                        $contenido,
                        $categoria,
                        $estado,
                        $destacada,
                        $_SESSION['usuario']['id'],
                        $estado,
                    ],
                );
            }
            auditar('Guardó noticia: ' . $titulo);
            aviso('Noticia guardada correctamente.');
            ir('index.php?modulo=noticias');
        case 'archivar_noticia':
            exigir('noticias');
            consulta('UPDATE noticias SET estado="Archivada",destacada=0 WHERE id=?', [$id]);
            auditar('Archivó noticia #' . $id);
            aviso('Noticia archivada.');
            ir('index.php?modulo=noticias');
        case 'importar_personas':
            exigir('administracion');
            require_once __DIR__ . '/importar_personas.php';
            $_SESSION['resultado_importacion'] = importar_archivo_personas();
            aviso('Importación procesada. Revisa el resumen de personas registradas y RUT omitidos.');
            ir('index.php?modulo=administracion');
        case 'usuario':
            exigir('administracion');
            $nombres = validar_nombre(requerido('nombres', 120), 'nombres');
            $apellidos = validar_nombre(requerido('apellidos', 120), 'apellidos');
            $rut = normalizar_rut(requerido('rut', 12));
            $nombre = $nombres . ' ' . $apellidos;
            $correo = trim((string) ($_POST['correo'] ?? ''));
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Correo no válido.');
            }
            $unidad = trim((string) ($_POST['unidad'] ?? '')) ?: 'Sin unidad asignada';
            $cargo = trim((string) ($_POST['cargo'] ?? '')) ?: 'Sin cargo asignado';
            $telefono = trim((string) ($_POST['telefono'] ?? ''));
            if (
                mb_strlen($unidad) > 120 ||
                mb_strlen($cargo) > 120 ||
                mb_strlen($correo) > 180 ||
                ($telefono !== '' && !preg_match('/^[+0-9 ()-]{3,40}$/', $telefono))
            ) {
                throw new RuntimeException('Revisa los datos de contacto.');
            }
            $rol = (int) ($_POST['rol_id'] ?? 0);
            if (!consulta('SELECT id FROM roles WHERE id=?', [$rol])->fetch()) {
                throw new RuntimeException('Perfil inválido.');
            }
            $activo = isset($_POST['activo']) ? 1 : 0;
            $bd->beginTransaction();
            consulta('SELECT id FROM roles WHERE nombre="Administrador" FOR UPDATE');
            if ($id) {
                $anterior = consulta('SELECT * FROM usuarios WHERE id=? FOR UPDATE', [$id])->fetch();
                if (!$anterior) {
                    throw new RuntimeException('Persona no encontrada.');
                }
                if ($anterior['rut'] !== null && $anterior['rut'] !== $rut) {
                    throw new RuntimeException('El RUT de una persona ya registrada no se puede reemplazar.');
                }
                if (
                    $id === (int) $_SESSION['usuario']['id'] &&
                    (!$activo || $rol !== (int) $_SESSION['usuario']['rol_id'])
                ) {
                    throw new RuntimeException('No puedes desactivar tu cuenta ni cambiar tu propio rol.');
                }
                $admin = (int) consulta('SELECT id FROM roles WHERE nombre="Administrador"')->fetchColumn();
                if (
                    (int) $anterior['rol_id'] === $admin &&
                    (!$activo || $rol !== $admin) &&
                    (int) consulta(
                        'SELECT COUNT(*) FROM usuarios WHERE rol_id=? AND activo=1 AND rut IS NOT NULL',
                        [$admin],
                    )->fetchColumn() <= 1
                ) {
                    throw new RuntimeException('Debe quedar al menos un Administrador activo.');
                }
                consulta(
                    'UPDATE usuarios SET nombre=?,nombres=?,apellidos=?,rut=?,correo=?,unidad=?,cargo=?,telefono=?,rol_id=?,activo=? WHERE id=?',
                    [
                        $nombre,
                        $nombres,
                        $apellidos,
                        $rut,
                        $correo ?: null,
                        $unidad,
                        $cargo,
                        $telefono,
                        $rol,
                        $activo,
                        $id,
                    ],
                );
            } else {
                if (consulta('SELECT id FROM usuarios WHERE rut=?', [$rut])->fetch()) {
                    $bd->rollBack();
                    aviso('El RUT ya existe. Se omitió el registro sin modificar a la persona.');
                    ir('index.php?modulo=administracion');
                }
                consulta(
                    'INSERT INTO usuarios(nombre,nombres,apellidos,rut,correo,unidad,cargo,telefono,rol_id,activo) VALUES(?,?,?,?,?,?,?,?,?,?)',
                    [
                        $nombre,
                        $nombres,
                        $apellidos,
                        $rut,
                        $correo ?: null,
                        $unidad,
                        $cargo,
                        $telefono,
                        $rol,
                        $activo,
                    ],
                );
            }
            auditar('Guardó persona: ' . $nombre . ' · RUT ' . formatear_rut($rut));
            $bd->commit();
            aviso('Persona guardada correctamente.');
            ir('index.php?modulo=administracion');
        case 'directorio':
            exigir('directorio');
            $telefono = trim((string) ($_POST['telefono'] ?? ''));
            if (
                mb_strlen($telefono) > 40 ||
                ($telefono !== '' && !preg_match('/^[+0-9 ()-]{3,40}$/', $telefono))
            ) {
                throw new RuntimeException('Teléfono inválido.');
            }
            consulta('UPDATE usuarios SET unidad=?,cargo=?,telefono=? WHERE id=?', [
                requerido('unidad', 120),
                requerido('cargo', 120),
                $telefono,
                $id,
            ]);
            auditar('Actualizó directorio #' . $id);
            aviso('Directorio actualizado.');
            ir('index.php?modulo=directorio');
        case 'documento':
            exigir('documentos');
            $titulo = requerido('titulo');
            $descripcion = requerido('descripcion', 3000);
            $categoria = requerido('categoria', 100);
            $version = requerido('version', 30);
            $anterior = $id ? consulta('SELECT * FROM documentos WHERE id=?', [$id])->fetch() : null;
            if ($id && !$anterior) {
                throw new RuntimeException('Documento no encontrado.');
            }
            $nuevo =
                !$id || (isset($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE);
            $archivo = $nuevo
                ? guardar_archivo()
                : [
                    $anterior['archivo'],
                    $anterior['nombre_original'],
                    $anterior['mime'],
                    $anterior['tamano'],
                ];
            try {
                $bd->beginTransaction();
                if ($id) {
                    consulta(
                        'UPDATE documentos SET titulo=?,descripcion=?,categoria=?,version=?,archivo=?,nombre_original=?,mime=?,tamano=? WHERE id=?',
                        [$titulo, $descripcion, $categoria, $version, ...$archivo, $id],
                    );
                } else {
                    consulta(
                        'INSERT INTO documentos(titulo,descripcion,categoria,version,archivo,nombre_original,mime,tamano,autor_id) VALUES(?,?,?,?,?,?,?,?,?)',
                        [
                            $titulo,
                            $descripcion,
                            $categoria,
                            $version,
                            ...$archivo,
                            $_SESSION['usuario']['id'],
                        ],
                    );
                }
                auditar('Guardó documento: ' . $titulo . ' · versión ' . $version);
                $bd->commit();
            } catch (Throwable $ex) {
                if ($bd->inTransaction()) {
                    $bd->rollBack();
                }
                if ($nuevo) {
                    unlink(__DIR__ . '/../almacenamiento/' . $archivo[0]);
                }
                throw $ex;
            }
            if ($anterior && $nuevo) {
                @unlink(__DIR__ . '/../almacenamiento/' . $anterior['archivo']);
            }
            aviso('Documento guardado.');
            ir('index.php?modulo=documentos');
        case 'eliminar_documento':
            exigir('documentos');
            $d = consulta('SELECT * FROM documentos WHERE id=?', [$id])->fetch();
            if (!$d) {
                throw new RuntimeException('Documento no encontrado.');
            }
            $bd->beginTransaction();
            consulta('DELETE FROM documentos WHERE id=?', [$id]);
            auditar('Eliminó documento: ' . $d['titulo']);
            $bd->commit();
            @unlink(__DIR__ . '/../almacenamiento/' . $d['archivo']);
            aviso('Documento eliminado.');
            ir('index.php?modulo=documentos');
        case 'sistema':
            exigir('sistemas');
            $datos = [
                requerido('nombre', 140),
                requerido('descripcion', 500),
                url_valida(requerido('url', 1000)),
                requerido('categoria', 80),
                isset($_POST['activo']) ? 1 : 0,
            ];
            if ($id) {
                consulta(
                    'UPDATE sistemas_enlaces SET nombre=?,descripcion=?,url=?,categoria=?,activo=? WHERE id=?',
                    [...$datos, $id],
                );
            } else {
                consulta(
                    'INSERT INTO sistemas_enlaces(nombre,descripcion,url,categoria,activo) VALUES(?,?,?,?,?)',
                    $datos,
                );
            }
            auditar('Guardó sistema: ' . $datos[0]);
            aviso('Acceso guardado.');
            ir('index.php?modulo=sistemas');
        case 'ticket':
            $asunto = requerido('asunto');
            $descripcion = requerido('descripcion', 10000);
            $categoria = opcion('categoria', CATEGORIAS);
            $prioridad = opcion('prioridad', PRIORIDADES);
            $bd->beginTransaction();
            consulta(
                'INSERT INTO tickets(solicitante_id,unidad,asunto,categoria,prioridad,descripcion,solicitante_nombre,solicitante_rut) VALUES(?,?,?,?,?,?,?,?)',
                [
                    $_SESSION['usuario']['id'],
                    $_SESSION['usuario']['unidad'],
                    $asunto,
                    $categoria,
                    $prioridad,
                    $descripcion,
                    $_SESSION['usuario']['nombre'],
                    $_SESSION['usuario']['rut'],
                ],
            );
            $id = (int) $bd->lastInsertId();
            consulta(
                'INSERT INTO ticket_historial(ticket_id,usuario_id,detalle,autor_nombre,autor_rut) VALUES(?,?,?,?,?)',
                [
                    $id,
                    $_SESSION['usuario']['id'],
                    'Solicitud creada · Estado: Nuevo · Prioridad: ' . $prioridad,
                    $_SESSION['usuario']['nombre'],
                    $_SESSION['usuario']['rut'],
                ],
            );
            auditar('Creó ticket ' . folio($id));
            $bd->commit();
            aviso('Solicitud registrada: ' . folio($id));
            ir('index.php?modulo=soporte&ver=' . $id);
        case 'comentario':
            $t = ticket_visible($id);
            if ($t['estado'] === 'Cerrado') {
                throw new RuntimeException('La solicitud está cerrada.');
            }
            $comentario = requerido('comentario', 5000);
            $bd->beginTransaction();
            consulta(
                'INSERT INTO ticket_historial(ticket_id,usuario_id,detalle,autor_nombre,autor_rut) VALUES(?,?,?,?,?)',
                [
                    $id,
                    $_SESSION['usuario']['id'],
                    'Comentario: ' . $comentario,
                    $_SESSION['usuario']['nombre'],
                    $_SESSION['usuario']['rut'],
                ],
            );
            consulta('UPDATE tickets SET actualizado=NOW() WHERE id=?', [$id]);
            auditar('Comentó ticket ' . folio($id));
            $bd->commit();
            aviso('Comentario agregado.');
            ir('index.php?modulo=soporte&ver=' . $id);
        case 'gestionar_ticket':
            exigir('soporte');
            $bd->beginTransaction();
            consulta('SELECT id FROM tickets WHERE id=? FOR UPDATE', [$id]);
            $t = ticket_visible($id);
            $estado = opcion('estado', ESTADOS);
            $prioridad = opcion('prioridad', PRIORIDADES);
            $responsable = (int) ($_POST['responsable_id'] ?? 0);
            $solucion = trim((string) ($_POST['solucion'] ?? ''));
            if (mb_strlen($solucion) > 5000) {
                throw new RuntimeException('La solución supera los 5000 caracteres.');
            }
            $transiciones = [
                'Nuevo' => ['Nuevo', 'Asignado'],
                'Asignado' => ['Asignado', 'En proceso', 'Nuevo'],
                'En proceso' => ['En proceso', 'Esperando usuario', 'Resuelto'],
                'Esperando usuario' => ['Esperando usuario', 'En proceso', 'Resuelto'],
                'Resuelto' => ['Resuelto', 'Cerrado', 'En proceso'],
                'Cerrado' => ['Cerrado', 'En proceso'],
            ];
            if (!in_array($estado, $transiciones[$t['estado']], true)) {
                throw new RuntimeException('Transición no permitida. Sigue el flujo de estados indicado.');
            }
            $r = $responsable
                ? consulta(
                    'SELECT u.id,u.nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1 AND u.rut IS NOT NULL AND (r.nombre="Administrador" OR EXISTS(SELECT 1 FROM roles_permisos p WHERE p.rol_id=r.id AND p.modulo="soporte"))',
                    [$responsable],
                )->fetch()
                : null;
            if ($responsable && !$r) {
                throw new RuntimeException('Selecciona un responsable autorizado.');
            }
            if ($estado !== 'Nuevo' && !$responsable) {
                throw new RuntimeException('Asigna un responsable antes de avanzar.');
            }
            if (in_array($estado, ['Resuelto', 'Cerrado'], true) && $solucion === '') {
                throw new RuntimeException('Registra la solución antes de resolver o cerrar.');
            }
            consulta(
                'UPDATE tickets SET estado=?,prioridad=?,responsable_id=?,solucion=?,actualizado=NOW(),resuelto=IF(? IN ("Resuelto","Cerrado"),COALESCE(resuelto,NOW()),NULL) WHERE id=?',
                [$estado, $prioridad, $responsable ?: null, $solucion, $estado, $id],
            );
            consulta(
                'INSERT INTO ticket_historial(ticket_id,usuario_id,detalle,autor_nombre,autor_rut) VALUES(?,?,?,?,?)',
                [
                    $id,
                    $_SESSION['usuario']['id'],
                    'Gestión: ' .
                    $t['estado'] .
                    ' → ' .
                    $estado .
                    ' · Prioridad: ' .
                    $t['prioridad'] .
                    ' → ' .
                    $prioridad .
                    ' · Responsable: ' .
                    ($t['responsable'] ?: 'Sin asignar') .
                    ' → ' .
                    ($r['nombre'] ?? 'Sin asignar') .
                    ($solucion !== '' ? ' · Solución: ' . $solucion : ''),
                    $_SESSION['usuario']['nombre'],
                    $_SESSION['usuario']['rut'],
                ],
            );
            auditar('Gestionó ticket ' . folio($id));
            $bd->commit();
            aviso('Solicitud actualizada.');
            ir('index.php?modulo=soporte&ver=' . $id);
        case 'perfil':
            exigir('administracion');
            $rol = consulta('SELECT * FROM roles WHERE id=?', [$id])->fetch();
            if (!$rol) {
                throw new RuntimeException('Perfil no encontrado.');
            }
            $clave = (string) ($_POST['clave'] ?? '');
            $modulos = ['noticias', 'documentos', 'directorio', 'sistemas', 'soporte'];
            $permisos = $_POST['permisos'] ?? [];
            if (!is_array($permisos) || array_diff($permisos, $modulos)) {
                throw new RuntimeException('Permisos inválidos.');
            }
            $bd->beginTransaction();
            $roles = consulta('SELECT * FROM roles ORDER BY id FOR UPDATE')->fetchAll();
            if ($clave !== '') {
                if (!pin_valido($clave)) {
                    throw new RuntimeException('El PIN debe contener exactamente 6 dígitos.');
                }
                foreach ($roles as $otro) {
                    if ((int) $otro['id'] !== $id && password_verify($clave, $otro['clave'])) {
                        throw new RuntimeException('El PIN debe ser diferente al de los otros perfiles.');
                    }
                }
                consulta('UPDATE roles SET clave=?,version=version+1 WHERE id=?', [
                    password_hash($clave, PASSWORD_DEFAULT),
                    $id,
                ]);
            }
            if ($rol['nombre'] !== 'Administrador') {
                consulta('DELETE FROM roles_permisos WHERE rol_id=?', [$id]);
                foreach (array_unique($permisos) as $p) {
                    consulta('INSERT INTO roles_permisos VALUES(?,?)', [$id, $p]);
                }
            }
            auditar('Actualizó permisos o PIN del perfil ' . $rol['nombre']);
            $bd->commit();
            aviso('Perfil actualizado. Al cambiar el PIN se cerrarán las sesiones de ese perfil.');
            ir('index.php?modulo=administracion');
        case 'parametros':
            exigir('administracion');
            $valores = [
                'institucion' => requerido('institucion', 120),
                'mensaje' => requerido('mensaje', 500),
                'soporte' => requerido('soporte', 500),
            ];
            $bd->beginTransaction();
            foreach ($valores as $k => $v) {
                consulta('UPDATE parametros SET valor=? WHERE nombre=?', [$v, $k]);
            }
            auditar('Actualizó parámetros institucionales');
            $bd->commit();
            aviso('Configuración guardada.');
            ir('index.php?modulo=administracion');
        default:
            throw new RuntimeException('Acción no válida.');
    }
}
