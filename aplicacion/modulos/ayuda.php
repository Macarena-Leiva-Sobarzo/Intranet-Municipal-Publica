<?php
function mostrar_ayuda(array $actual, array $modulos): void
{
    encabezado('Ayuda de la intranet', 'Una guía para trabajar en nuestro espacio municipal.'); ?>
<section class="tarjeta lectura">
    <h2>Ingreso y perfiles</h2>
    <p>Ingresa con tu RUT y el PIN de seis dígitos del rol que te asignó el Administrador. El sistema
        comprueba el PIN únicamente contra ese rol. El
        administrador mantiene el directorio y configura los PIN y permisos. Los PIN son distintos entre
        perfiles
        y pueden comenzar con cero. Si cambias de rol, utiliza su PIN al volver a ingresar. Las sesiones
        vencen después
        de 60 minutos de inactividad.</p>
    <h2>Información y documentos</h2>
    <p>En Inicio encontrarás novedades y accesos. Noticias muestra las publicaciones internas. Directorio
        permite buscar
        por nombre, unidad y cargo. En Documentos puedes filtrar por categoría y descargar archivos. Sistemas
        reúne las
        plataformas municipales.</p>
    <h2>Publicación de contenidos</h2>
    <p>Quienes tengan permisos de edición pueden crear noticias como borrador, publicarlas o archivarlas. Solo
        las
        publicadas son visibles para todos. Marca una noticia como destacada para priorizarla en Inicio. Los
        documentos
        admiten categoría, descripción y versión; al reemplazar un archivo, actualiza su versión. Los formatos
        permitidos son PDF, JPG, PNG, DOCX, XLSX y CSV, hasta 10 MB.</p>
    <h2>Mesa de ayuda</h2>
    <p>Crea una solicitud indicando asunto, categoría, prioridad y descripción. La unidad y el solicitante se
        completan
        con tu cuenta. Conserva el folio SOP para consultar su evolución. Puedes agregar comentarios mientras
        el ticket
        esté abierto. Cada persona consulta sus propias solicitudes; quienes administran soporte pueden verlas
        todas.
    </p>
    <p>El equipo de soporte asigna un responsable y sigue el flujo: Nuevo → Asignado → En proceso → Esperando
        usuario →
        Resuelto → Cerrado. Puede resolver directamente desde En proceso, volver de Esperando usuario a En
        proceso, o
        reabrir un caso resuelto o cerrado. Para resolver o cerrar debe registrar una solución. El historial
        conserva
        comentarios, responsables y cambios de estado. Las acciones conservan el nombre y RUT de la persona
        que las realizó.</p>
    <h2>Administración</h2>
    <p>Registra a cada funcionario con su correo, unidad, cargo y perfil. Un funcionario inactivo no aparece
        en el directorio. Solo las personas activas con RUT registrado pueden ingresar. El Administrador puede
        importar un Excel XLSX/XLS o PDF con texto seleccionable, con encabezados Nombres, Apellidos, RUT y
        Rol. Cada carga recibe un archivo; se omiten los RUT existentes sin cambiar su rol. El resumen
        distingue personas registradas, omitidas e inválidas. Los permisos autorizan gestionar módulos; las
        consultas generales siguen disponibles.
        Al cambiar el PIN de un perfil se invalidan sus sesiones activas. La bitácora registra las acciones
        relevantes.
    </p>
    <?php if (permiso('administracion')): ?>
    <h2>Instalación y mantenimiento</h2>
    <ol>
        <li>Requiere PHP 8.2 o posterior, Apache y MySQL/MariaDB. Habilita pdo_mysql, mbstring, fileinfo y
            zip.</li>
        <li>Ubica el proyecto en el servidor y configura la conexión en <code>config/base_datos.php</code> o
            mediante
            sus variables de entorno.</li>
        <li>Crea la base de datos y su usuario en el hosting. Importa <code>BD - Intranet
                Municipal.sql</code>, que contiene las tablas, los perfiles y los PIN configurados. Luego
            registra al primer Administrador con sus datos y el PIN de Administrador existente; luego ingresa
            con RUT y PIN.</li>
        <li>Configura HTTPS para producción, un usuario exclusivo de base de datos y permisos de escritura
            solamente en
            almacenamiento. Mantén activas las reglas .htaccess y evita exponer las carpetas config,
            aplicacion y
            almacenamiento.</li>
        <li>Respalda desde Administración periódicamente y antes de actualizar. El SQL contiene estructura y
            datos;
            restaura en una base vacía desde phpMyAdmin y comprueba acceso, documentos y tickets.</li>
        <li>Respalda también la carpeta almacenamiento: los archivos adjuntos no están dentro del SQL.
            Conserva código,
            configuración y archivos junto al respaldo correspondiente.</li>
    </ol>
    <h2>Modelo de datos</h2>
    <p>Usuarios pertenece a Roles; Roles_permisos define la administración de módulos. Noticias y Documentos
        registran
        su autor. Sistemas_enlaces contiene los accesos. Tickets relaciona solicitante y responsable con
        Usuarios;
        Ticket_historial conserva los eventos de cada solicitud. Auditoria registra acciones, Parametros
        almacena
        configuración e Intentos_acceso limita los intentos fallidos.</p>
    <p>El proyecto instala datos institucionales mínimos, sin personas ni tickets ficticios. La tabla usuarios
        alimenta
        también el directorio para evitar duplicar contactos.</p>
    <?php endif; ?>
    <button type="button" class="secundario" data-imprimir>Imprimir guía / guardar como PDF</button>
</section>
<?php
}
