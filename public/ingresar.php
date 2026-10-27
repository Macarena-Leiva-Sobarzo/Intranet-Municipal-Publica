<?php
require __DIR__ . '/../aplicacion/inicio.php';
$error = '';
$acceso_disponible = false;
try {
    $bd = conexion();
    $acceso_disponible = (int) consulta('SELECT COUNT(*) FROM roles')->fetchColumn() > 0;
    if (!$acceso_disponible) {
        $error =
            'La base de datos no contiene los perfiles. Importa BD - Intranet Municipal.sql antes de ingresar.';
    }
} catch (Throwable $ex) {
    error_log((string) $ex);
    $error =
        'No se pudo acceder a la base de datos. Revisa la conexión y la importación del SQL en el hosting.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reiniciar_sesion') {
    verificar_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    ir('ingresar.php');
}
if (isset($_SESSION['usuario'])) {
    ir();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificar_csrf();
        if (!$acceso_disponible) {
            throw new RuntimeException($error);
        }
        $clave = (string) ($_POST['clave'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
        $intento = consulta('SELECT * FROM intentos_acceso WHERE direccion=?', [$ip])->fetch();
        if ($intento && (int) $intento['intentos'] >= 8 && strtotime($intento['ultimo']) > time() - 900) {
            throw new RuntimeException('Demasiados intentos. Espera 15 minutos antes de volver a ingresar.');
        }
        $u = autenticar_persona((string) ($_POST['rut'] ?? ''), $clave);
        if (!$u) {
            consulta(
                'INSERT INTO intentos_acceso(direccion,intentos,ultimo) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE intentos=IF(ultimo<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,intentos+1),ultimo=NOW()',
                [$ip],
            );
            throw new RuntimeException('RUT o PIN incorrectos. Utiliza el PIN del rol asignado a tu RUT.');
        }
        consulta('DELETE FROM intentos_acceso WHERE direccion=?', [$ip]);
        session_regenerate_id(true);
        $_SESSION['usuario'] = $u;
        $_SESSION['actividad'] = time();
        $_SESSION['permisos'] = consulta('SELECT modulo FROM roles_permisos WHERE rol_id=?', [
            $u['rol_id'],
        ])->fetchAll(PDO::FETCH_COLUMN);
        auditar('Inicio de sesión: ' . $u['rol']);
        ir(destino_perfil($u['rol']));
    } catch (Throwable $ex) {
        error_log((string) $ex);
        $error =
            $ex instanceof RuntimeException && !($ex instanceof PDOException)
                ? $ex->getMessage()
                : 'No se pudo iniciar sesión. Inténtalo nuevamente.';
    }
}
$requiere_administrador = $acceso_disponible && !existe_administrador_personal();
$titulo = 'Bienvenido';
$clase_publica = 'pagina-acceso';
require __DIR__ . '/../aplicacion/cabecera_publica.php';
mostrar_cabecera_publica($titulo, $clase_publica);
?>
<main class="acceso">
    <section class="presentacion">
        <span class="etiqueta">CONECTADOS CON NUESTRA GESTIÓN</span>
        <h1>El progreso lo
            <br>construimos
            <br>
            <em>juntos.</em>
        </h1>
        <p>Información, herramientas y apoyo para el trabajo de cada día.</p>
        <div class="linea-colores">
            <i></i>
            <i></i>
            <i></i>
            <i></i>
        </div>
        <small>ILUSTRE MUNICIPALIDAD DE ÑIQUÉN</small>
    </section>
    <section class="tarjeta formulario-acceso">
        <span class="icono-grande">↗</span>
        <h2>Bienvenido a tu intranet</h2>
        <p class="muted">Ingresa tu RUT y el PIN de tu rol. El acceso corresponde al perfil asignado a tu
            persona.</p>
        <?php if (isset($_SESSION['aviso'])): ?>
        <div class="alerta"><?= e($_SESSION['aviso'][0]) ?>
        </div>
        <?php unset($_SESSION['aviso']);endif; ?>
        <?php if ($error): ?>
        <div class="alerta error" role="alert"><?= e($error) ?>
        </div>
        <?php endif; ?>
        <?php if (
            $requiere_administrador
        ): ?>
        <p><a href="registro_inicial.php">Registrar al primer Administrador</a></p><?php endif; ?>
        <form method="post"><?= csrf() ?>
            <label>RUT
                <input name="rut" required maxlength="12" autocomplete="username" placeholder="12.345.678-5"
                    value="" autofocus data-enfocar-rut>
            </label>
            <label>PIN de tu rol
                <input type="password" name="clave" required inputmode="numeric" pattern="[0-9]{6}"
                    minlength="6" maxlength="6" title="Ingresa exactamente 6 dígitos"
                    autocomplete="current-password">
            </label>
            <button class="ancho">Ingresar a la intranet →</button>
        </form>
        <p class="nota">¿Necesitas acceso? El Administrador debe registrar tu RUT y entregarte el PIN de tu
            rol.</p>
    </section>
</main>
<footer class="pie-publico">© <?= date('Y') ?> · Ilustre Municipalidad de Ñiquén</footer>
</body>

</html>
