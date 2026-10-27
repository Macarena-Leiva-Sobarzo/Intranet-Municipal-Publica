<?php
require __DIR__ . '/../aplicacion/inicio.php';
$configuracion = require __DIR__ . '/../config/base_datos.php';
$es_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$error = '';
$completa = false;
try {
    $bd = conexion();
    $completa =
        (int) $bd->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 0;
} catch (Throwable $ex) {
    /* La base puede no existir todavía. */
}
if ($completa) {
    ir('index.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificar_csrf();
        if (!$es_local) {
            $autorizacion = (string) $configuracion['clave_instalacion'];
            if (strlen($autorizacion) < 20) {
                throw new RuntimeException('Configura una clave_instalacion de al menos 20 caracteres en config/base_datos.php para autorizar la instalación en el hosting.');
            }
            if (!hash_equals($autorizacion, (string) ($_POST['clave_instalacion'] ?? ''))) {
                throw new RuntimeException('La clave de instalación no es correcta.');
            }
        }
        $nombre = requerido('nombre', 120);
        $correo = requerido('correo', 180);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Ingresa un correo válido.');
        }
        $claves = [];
        foreach (
            ['administrador', 'informatica', 'editor', 'funcionario']
            as $rol
        ) {
            $clave = (string) ($_POST['clave_' . $rol] ?? '');
            if (!pin_valido($clave)) {
                throw new RuntimeException(
                    'Cada PIN debe contener exactamente 6 dígitos.',
                );
            }
            $claves[] = $clave;
        }
        if (count(array_unique($claves)) !== 4) {
            throw new RuntimeException('Los cuatro PIN deben ser diferentes.');
        }
        $c = require __DIR__ . '/../config/base_datos.php';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $c['nombre'])) {
            throw new RuntimeException(
                'El nombre de la base de datos no es válido.',
            );
        }
        try {
            // El hosting normalmente entrega una base creada desde su panel.
            $bd = conexion();
        } catch (PDOException $ex) {
            if (!$es_local || (int) ($ex->errorInfo[1] ?? 0) !== 1049) {
                throw new RuntimeException('No se pudo conectar a la base configurada. Crea la base y su usuario en el panel del hosting, asígnale permisos y revisa config/base_datos.php.');
            }
            $bd = conexion(true);
            $bd->exec(
                'CREATE DATABASE IF NOT EXISTS `' . $c['nombre'] .
                '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            );
            $bd = conexion();
        }
        $bd->exec(file_get_contents(__DIR__ . '/../BD - Intranet Municipal.sql'));
        $bd->beginTransaction();
        if ((int) $bd->query('SELECT COUNT(*) FROM roles')->fetchColumn() > 0) {
            throw new RuntimeException('La intranet ya está instalada.');
        }
        foreach (
            ['Administrador', 'Informática', 'Editor', 'Funcionario']
            as $i => $rol
        ) {
            consulta('INSERT INTO roles(nombre,clave) VALUES(?,?)', [
                $rol,
                password_hash($claves[$i], PASSWORD_DEFAULT),
            ]);
            $id = (int) $bd->lastInsertId();
            $permisos = match ($rol) {
                'Administrador' => [
                    'noticias',
                    'documentos',
                    'directorio',
                    'sistemas',
                    'soporte',
                ],
                'Informática' => ['sistemas', 'soporte'],
                'Editor' => ['noticias', 'documentos'],
                default => [],
            };
            foreach ($permisos as $p) {
                consulta('INSERT INTO roles_permisos VALUES(?,?)', [$id, $p]);
            }
        }
        consulta(
            'INSERT INTO usuarios(nombre,correo,unidad,cargo,rol_id) VALUES(?,?,?,?,1)',
            [$nombre, $correo, 'Administración', 'Administrador de intranet'],
        );
        consulta('INSERT INTO parametros VALUES(?,?),(?,?),(?,?)', [
            'institucion',
            'Municipalidad de Ñiquén',
            'mensaje',
            'Un espacio para informarnos, colaborar y hacer una mejor gestión municipal.',
            'soporte',
            'Contacta al equipo de Informática mediante la mesa de ayuda.',
        ]);
        consulta(
            'INSERT INTO sistemas_enlaces(nombre,descripcion,url,categoria) VALUES(?,?,?,?)',
            [
                'Portal municipal',
                'Sitio institucional de la Municipalidad de Ñiquén.',
                'https://www.muniniquen.cl/',
                'Institucional',
            ],
        );
        auditar('Instalación inicial completada');
        $bd->commit();
        aviso(
            'Instalación completada. Ingresa con el PIN de Administrador.',
        );
        ir('ingresar.php');
    } catch (Throwable $ex) {
        if (isset($bd) && $bd->inTransaction()) {
            $bd->rollBack();
        }
        error_log((string) $ex);
        $error =
            $ex instanceof RuntimeException && !($ex instanceof PDOException)
                ? $ex->getMessage()
                : 'No se pudo completar la instalación. Verifica la conexión, los permisos de la base y la configuración en config/base_datos.php.';
    }
}
$titulo = 'Instalación inicial';
require __DIR__ . '/../aplicacion/cabecera_publica.php';
mostrar_cabecera_publica($titulo);
?>
<main class="instalacion tarjeta">
    <span class="etiqueta">CONFIGURACIÓN INICIAL</span>
    <h1>Tu intranet comienza aquí.</h1>
    <p>La instalación creará las tablas y los cuatro perfiles de acceso en la base configurada. En hosting, crea primero la base y su usuario desde el panel del proveedor.</p>
    <?php if ($error): ?>
    <div class="alerta error" role="alert"><?= e($error) ?>
    </div>
    <?php endif; ?>
    <form method="post"><?= csrf() ?>
        <?php if (!$es_local): ?>
        <label>Clave de instalación
            <input type="password" name="clave_instalacion" required minlength="20" autocomplete="off">
        </label>
        <p class="muted">Utiliza la clave configurada en config/base_datos.php. Esta autorización es distinta a los PIN de los perfiles.</p>
        <?php endif; ?>
        <h2>Administrador inicial</h2>
        <div class="campos">
            <label>Nombre completo
                <input name="nombre" required maxlength="120" autocomplete="name">
            </label>
            <label>Correo institucional
                <input type="email" name="correo" required maxlength="180" autocomplete="email">
            </label>
        </div>
        <h2>Un PIN diferente por perfil</h2>
        <p class="muted">Exactamente 6 dígitos. Cada persona ingresará únicamente con el PIN compartido de su perfil.
            El administrador podrá registrar a los demás funcionarios.</p>
        <div class="campos">
            <?php foreach (
                [
                    'administrador' => 'Administrador',
                    'informatica' => 'Informática',
                    'editor' => 'Editor',
                    'funcionario' => 'Funcionario',
                ]
                as $k => $r
            ): ?>
            <label>PIN de <?= e($r) ?>
                <input type="password" name="clave_<?= e($k) ?>" required inputmode="numeric" pattern="[0-9]{6}"
                    minlength="6" maxlength="6" title="Ingresa exactamente 6 dígitos" autocomplete="new-password">
            </label>
            <?php endforeach; ?>
        </div>
        <button>Crear intranet municipal →</button>
    </form>
    <details>
        <summary>Configuración del servidor</summary>
        <p>Edita <code>config/base_datos.php</code> o configura las variables <code>INTRANET_BD_SERVIDOR</code>, <code>
                INTRANET_BD_PUERTO</code>, <code>INTRANET_BD_NOMBRE</code>, <code>INTRANET_BD_USUARIO</code> y <code>
                INTRANET_BD_CLAVE</code>. En XAMPP local se utiliza root sin clave de forma predeterminada. En
            producción utiliza un usuario exclusivo y HTTPS. Para instalar desde hosting, configura también <code>clave_instalacion</code> o la variable <code>INTRANET_CLAVE_INSTALACION</code> con un secreto de al menos 20 caracteres. Después de completar la instalación, elimina ese secreto o retira instalar.php.</p>
    </details>
</main>
</body>

</html>
