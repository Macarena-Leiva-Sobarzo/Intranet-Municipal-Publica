<?php
require __DIR__ . '/../aplicacion/inicio.php';
$bd = conexion();
if (existe_administrador_personal()) {
    ir('ingresar.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verificar_csrf();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
        $intento = consulta('SELECT * FROM intentos_acceso WHERE direccion=?', [$ip])->fetch();
        if ($intento && (int) $intento['intentos'] >= 8 && strtotime($intento['ultimo']) > time() - 900) {
            throw new RuntimeException('Demasiados intentos. Espera 15 minutos.');
        }
        // Cuenta cada intento antes de comprobar el PIN para impedir reintentos ilimitados.
        consulta(
            'INSERT INTO intentos_acceso(direccion,intentos,ultimo) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE intentos=IF(ultimo<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,intentos+1),ultimo=NOW()',
            [$ip],
        );
        registrar_administrador_inicial(
            requerido('nombres', 120),
            requerido('apellidos', 120),
            requerido('rut', 12),
            (string) ($_POST['clave'] ?? ''),
        );
        consulta('DELETE FROM intentos_acceso WHERE direccion=?', [$ip]);
        aviso('Administrador registrado. Ingresa con tu RUT y el PIN de Administrador.');
        ir('ingresar.php');
    } catch (Throwable $ex) {
        error_log((string) $ex);
        $error =
            $ex instanceof RuntimeException && !($ex instanceof PDOException)
                ? $ex->getMessage()
                : 'No se pudo registrar al Administrador.';
    }
}
$titulo = 'Primer Administrador';
require __DIR__ . '/../aplicacion/cabecera_publica.php';
mostrar_cabecera_publica($titulo);
?>
<main class="tarjeta registro-persona">
    <h1>Registrar al primer Administrador</h1>
    <p>Completa tus datos y confirma el PIN de Administrador que ya está configurado. Este registro se
        habilita una sola vez.</p>
    <?php if ($error): ?>
    <div class="alerta error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf() ?>
        <label>Nombres<input name="nombres" required maxlength="120" autocomplete="given-name"
                autofocus></label>
        <label>Apellidos<input name="apellidos" required maxlength="120" autocomplete="family-name"></label>
        <label>RUT<input name="rut" required maxlength="12" placeholder="12.345.678-5"
                autocomplete="username"></label>
        <label>PIN de Administrador<input type="password" name="clave" inputmode="numeric" required
                pattern="[0-9]{6}" maxlength="6" autocomplete="current-password"></label>
        <button>Registrar mis datos</button>
        <a href="ingresar.php">Volver al ingreso</a>
    </form>
</main>
<footer class="pie-publico">© <?= date('Y') ?> · Ilustre Municipalidad de Ñiquén</footer>
</body>

</html>
