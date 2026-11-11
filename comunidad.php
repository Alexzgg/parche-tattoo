
<?php
session_start();

require_once __DIR__ . '/conexion.php';

/* =========================================
   SESIÓN Y USUARIO
========================================= */

$sesion_iniciada = isset($_SESSION['id_usuario']);
$id_usuario = $sesion_iniciada ? (int) $_SESSION['id_usuario'] : 0;

$usuario = null;
$tipo_usuario = null;
$es_admin = false;

if ($sesion_iniciada) {

    $sql = "SELECT id_usuario, nombre_usuario, nombre_completo, email, tipo_usuario
            FROM usuarios
            WHERE id_usuario = ? AND estado = 1
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        die("Error al consultar el usuario: " . $conexion->error);
    }

    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $resultado_usuario = $stmt->get_result();

    if ($resultado_usuario->num_rows === 1) {

        $usuario = $resultado_usuario->fetch_assoc();
        $tipo_usuario = $usuario['tipo_usuario'];

        $es_admin = (
            $tipo_usuario === 'Administrador' ||
            $usuario['email'] === 'adminparche@example.com'
        );

    } else {

        session_unset();
        session_destroy();

        header("Location: login.php");
        exit;
    }

    $stmt->close();
}

/* =========================================
   CONSULTAR PUBLICACIONES
   Artistas, clientes y usuario logueado
========================================= */

$publicaciones = [];
$error_publicaciones = '';

$sql_publicaciones = "
    SELECT
        p.id_publicacion,
        p.id_usuario,
        p.titulo,
        p.contenido,
        p.fecha_publicacion,
        u.nombre_usuario,
        u.nombre_completo,
        u.tipo_usuario
    FROM publicaciones_foro p
    INNER JOIN usuarios u
        ON p.id_usuario = u.id_usuario
    WHERE u.estado = 1
    AND (
        u.tipo_usuario IN ('Artista', 'Cliente')
        OR p.id_usuario = ?
    )
    ORDER BY p.fecha_publicacion DESC
";

$stmt_publicaciones = $conexion->prepare($sql_publicaciones);

if ($stmt_publicaciones) {

    $stmt_publicaciones->bind_param("i", $id_usuario);
    $stmt_publicaciones->execute();

    $resultado_publicaciones = $stmt_publicaciones->get_result();

    while ($fila = $resultado_publicaciones->fetch_assoc()) {
        $publicaciones[] = $fila;
    }

    $stmt_publicaciones->close();

} else {

    $error_publicaciones = "No fue posible consultar las publicaciones: " . $conexion->error;
}

$total_publicaciones = count($publicaciones);

/* =========================================
   FUNCIONES DE PRESENTACIÓN
========================================= */

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function fecha_publicacion($fecha) {

    if (empty($fecha)) {
        return 'Fecha no disponible';
    }

    $timestamp = strtotime($fecha);

    if (!$timestamp) {
        return escapar($fecha);
    }

    return date('d/m/Y', $timestamp) . ' · ' . date('h:i A', $timestamp);
}

function inicial_usuario($nombre) {

    $nombre = trim((string) $nombre);

    if ($nombre === '') {
        return 'U';
    }

    return strtoupper(substr($nombre, 0, 1));
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Comunidad | Parche del Tattoo</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <link rel="stylesheet" href="css/style.css">

    <style>

        :root {
            --vino: #8e263b;
            --vino-claro: #bd4059;
            --fondo: #101010;
            --tarjeta: #1a1a1a;
            --texto: #f5f5f5;
            --gris: #a5a5a5;
        }

        body {
            background: var(--fondo);
            color: var(--texto);
        }

        /* MENÚ */

        .usuario-sesion {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            margin-left: 15px;
        }

        .usuario-sesion i {
            color: var(--vino-claro);
        }

        .tipo-usuario {
            color: var(--vino-claro);
            font-weight: bold;
            font-size: 13px;
        }

        .btn-cerrar-sesion {
            background: #dc3545 !important;
            color: white !important;
            border-radius: 8px;
            padding: 8px 14px !important;
        }

        .btn-cerrar-sesion:hover {
            background: #b02a37 !important;
        }

        /* PORTADA */

        .comunidad-hero {
            min-height: 420px;
            display: flex;
            align-items: center;
            background:
                linear-gradient(90deg, rgba(0,0,0,.95), rgba(0,0,0,.65)),
                url('img/comunidad-tattoo.jpg') center/cover no-repeat;
            border-bottom: 1px solid #292929;
        }

        .hero-contenido {
            max-width: 750px;
            padding: 75px 0;
        }

        .hero-etiqueta {
            display: inline-block;
            background: rgba(142,38,59,.2);
            color: #ed8b9e;
            border: 1px solid rgba(189,64,89,.5);
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .hero-contenido h1 {
            font-size: clamp(38px,6vw,65px);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }

        .hero-contenido h1 span {
            color: var(--vino-claro);
        }

        .hero-contenido p {
            color: #c4c4c4;
            font-size: 17px;
            line-height: 1.8;
            max-width: 620px;
        }

        /* BOTONES */

        .btn-vino {
            background: var(--vino);
            color: white;
            border: 1px solid var(--vino);
            padding: 12px 22px;
            border-radius: 7px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: .3s;
        }

        .btn-vino:hover {
            background: var(--vino-claro);
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-light-custom {
            color: white;
            border: 1px solid #666;
            padding: 12px 22px;
            border-radius: 7px;
            text-decoration: none;
            display: inline-block;
            transition: .3s;
        }

        .btn-outline-light-custom:hover {
            background: white;
            color: #111;
        }

        /* CONTADOR */

        .estadisticas {
            background: #171717;
            border-top: 1px solid #292929;
            border-bottom: 1px solid #292929;
            padding: 25px 0;
        }

        .estadistica-numero {
            font-size: 30px;
            font-weight: 800;
            color: #e56a83;
        }

        .estadistica-texto {
            color: var(--gris);
            font-size: 14px;
        }

        /* PUBLICACIONES */

        .seccion-publicaciones {
            padding: 65px 0;
        }

        .titulo-seccion {
            font-size: 32px;
            font-weight: 750;
            margin-bottom: 12px;
        }

        .subtitulo-seccion {
            color: var(--gris);
            line-height: 1.7;
        }

        .publicacion-card {
            background: var(--tarjeta);
            border: 1px solid #303030;
            border-radius: 14px;
            padding: 25px;
            margin-bottom: 22px;
            transition: .3s;
        }

        .publicacion-card:hover {
            border-color: var(--vino);
            transform: translateY(-3px);
        }

        .publicacion-autor {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 20px;
        }

        .avatar-usuario {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--vino), var(--vino-claro));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            color: white;
        }

        .autor-nombre {
            font-weight: 700;
            color: white;
            margin-bottom: 3px;
        }

        .autor-tipo {
            font-size: 12px;
            color: #e56a83;
        }

        .publicacion-fecha {
            color: #929292;
            font-size: 12px;
            margin-top: 3px;
        }

        .publicacion-titulo {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 12px;
            color: white;
            overflow-wrap: anywhere;
        }

        .publicacion-contenido {
            color: #c5c5c5;
            line-height: 1.8;
            white-space: pre-line;
            overflow-wrap: anywhere;
            margin-bottom: 0;
        }

        .etiqueta-publicacion {
            display: inline-block;
            background: rgba(142,38,59,.18);
            color: #ed8b9e;
            border: 1px solid rgba(189,64,89,.3);
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 11px;
            margin-bottom: 15px;
        }

        .sin-publicaciones {
            background: var(--tarjeta);
            border: 1px dashed #494949;
            border-radius: 14px;
            padding: 55px 25px;
            text-align: center;
        }

        .sin-publicaciones i {
            font-size: 45px;
            color: var(--vino-claro);
            margin-bottom: 20px;
        }

        .sin-publicaciones h3 {
            font-size: 22px;
            font-weight: 700;
        }

        .sin-publicaciones p {
            color: var(--gris);
        }

        .alerta-error {
            background: #35171d;
            color: #ffb7c4;
            border: 1px solid #743040;
            border-radius: 10px;
            padding: 18px;
        }

        /* PARTICIPACIÓN */

        .participacion {
            padding: 20px 0 65px;
        }

        .participacion-card {
            background: linear-gradient(120deg,#39101b,#171717);
            border: 1px solid #54202e;
            border-radius: 16px;
            padding: 45px;
        }

        .participacion-card p {
            color: #c5c5c5;
            line-height: 1.8;
        }

        /* FOOTER */

        .footer-comunidad {
            background: #090909;
            border-top: 1px solid #292929;
            padding: 45px 0 20px;
            color: white;
        }

        .footer-comunidad h3 {
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .footer-comunidad p,
        .footer-comunidad a {
            color: #aaa;
            line-height: 1.8;
        }

        .footer-comunidad a {
            text-decoration: none;
        }

        .footer-comunidad a:hover {
            color: var(--vino-claro);
        }

        .footer-bottom {
            border-top: 1px solid #292929;
            margin-top: 25px;
            padding-top: 20px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        @media(max-width:768px) {

            .comunidad-hero {
                min-height: 400px;
            }

            .hero-contenido {
                padding: 55px 0;
            }

            .titulo-seccion {
                font-size: 27px;
            }

            .usuario-sesion {
                margin: 15px 0;
            }

        }

        @media(max-width:576px) {

            .participacion-card {
                padding: 28px 20px;
            }

            .seccion-publicaciones {
                padding: 45px 0;
            }

            .hero-contenido p {
                font-size: 15px;
            }

            .publicacion-card {
                padding: 20px;
            }

        }

    </style>

</head>

<body>

<!-- =========================================
     MENÚ PRINCIPAL
========================================= -->

<header>

    <div class="logo">

        <img
            src="./img/image-removebg-preview.png"
            alt="Logo Parche del Tattoo"
            class="logo-img">

        <h2>
            Parche del <span>Tattoo</span>
        </h2>

    </div>

    <nav>

        <a href="index.php">
            <i class="fa-solid fa-house"></i>
            Inicio
        </a>

        <?php if ($es_admin): ?>

            <a href="admin.php" class="btn-tattoo btn-principal">
                <i class="fa-solid fa-user-shield"></i>
                Panel Administrador
            </a>

        <?php endif; ?>

        <?php if ($sesion_iniciada): ?>

            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i>
                Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i>
                Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i>
                Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i>
                Blog
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i>
                Mi Perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>

        <?php else: ?>

            <a href="login.php">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar sesión
            </a>

            <a href="registro.php">
                <i class="fa-solid fa-user-plus"></i>
                Crear cuenta
            </a>

        <?php endif; ?>

    </nav>

    <?php if ($sesion_iniciada && $usuario): ?>

        <div class="usuario-sesion">

            <i class="fa-solid fa-circle-user"></i>

            <div>

                <strong>
                    <?= escapar($usuario['nombre_usuario']) ?>
                </strong>

                <br>

                <span class="tipo-usuario">
                    <?= escapar($tipo_usuario) ?>
                </span>

            </div>

        </div>

    <?php endif; ?>

</header>

<main>

    <!-- PORTADA -->

    <section class="comunidad-hero">

        <div class="container">

            <div class="hero-contenido">

                <span class="hero-etiqueta">
                    <i class="fa-solid fa-users me-2"></i>
                    COMUNIDAD PARCHE DEL TATTOO
                </span>

                <h1>
                    Más que tatuajes,
                    <br>
                    <span>una comunidad.</span>
                </h1>

                <p>
                    Un espacio para compartir experiencias, descubrir ideas,
                    conocer otros apasionados del tatuaje y expresar tu estilo.
                    Aquí cada diseño cuenta una historia.
                </p>

                <div class="d-flex flex-wrap gap-3 mt-4">

                    <a href="#publicaciones" class="btn-vino">
                        <i class="fa-solid fa-comments me-2"></i>
                        Ver publicaciones
                    </a>

                    <?php if ($sesion_iniciada): ?>

                        <a href="foro.php" class="btn-outline-light-custom">
                            <i class="fa-solid fa-pen-to-square me-2"></i>
                            Crear publicación
                        </a>

                    <?php else: ?>

                        <a href="registro.php" class="btn-outline-light-custom">
                            <i class="fa-solid fa-user-plus me-2"></i>
                            Unirme a la comunidad
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </section>

    <!-- CONTADOR DE PUBLICACIONES -->

    <section class="estadisticas">

        <div class="container text-center">

            <div class="estadistica-numero">
                <?= number_format($total_publicaciones) ?>
            </div>

            <div class="estadistica-texto">
                Publicaciones compartidas en la comunidad
            </div>

        </div>

    </section>

    <!-- PUBLICACIONES REALES -->

    <section class="seccion-publicaciones" id="publicaciones">

        <div class="container">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

                <div>

                    <h2 class="titulo-seccion">
                        Publicaciones de la comunidad
                    </h2>

                    <p class="subtitulo-seccion mb-0">
                        Conoce lo que comparten nuestros artistas y clientes.
                    </p>

                </div>

                <?php if ($sesion_iniciada): ?>

                    <a href="foro.php" class="btn-vino">
                        <i class="fa-solid fa-plus me-2"></i>
                        Crear publicación
                    </a>

                <?php endif; ?>

            </div>

            <?php if (!empty($error_publicaciones)): ?>

                <div class="alerta-error">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <?= escapar($error_publicaciones) ?>
                </div>

            <?php elseif ($total_publicaciones > 0): ?>

                <div class="row">

                    <div class="col-lg-9 mx-auto">

                        <?php foreach ($publicaciones as $publicacion): ?>

                            <?php
                                $nombre_autor = !empty($publicacion['nombre_completo'])
                                    ? $publicacion['nombre_completo']
                                    : $publicacion['nombre_usuario'];
                            ?>

                            <article class="publicacion-card">

                                <div class="publicacion-autor">

                                    <div class="avatar-usuario">
                                        <?= escapar(inicial_usuario($nombre_autor)) ?>
                                    </div>

                                    <div>

                                        <div class="autor-nombre">
                                            <?= escapar($nombre_autor) ?>
                                        </div>

                                        <div class="autor-tipo">
                                            <i class="fa-solid fa-user-check me-1"></i>
                                            <?= escapar($publicacion['tipo_usuario']) ?>
                                        </div>

                                        <div class="publicacion-fecha">
                                            <i class="fa-regular fa-clock me-1"></i>
                                            <?= fecha_publicacion($publicacion['fecha_publicacion']) ?>
                                        </div>

                                    </div>

                                </div>

                                <span class="etiqueta-publicacion">
                                    <i class="fa-solid fa-palette me-1"></i>
                                    Comunidad Tattoo
                                </span>

                                <?php if (!empty($publicacion['titulo'])): ?>

                                    <h3 class="publicacion-titulo">
                                        <?= escapar($publicacion['titulo']) ?>
                                    </h3>

                                <?php endif; ?>

                                <p class="publicacion-contenido"><?= escapar($publicacion['contenido']) ?></p>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="sin-publicaciones">

                    <i class="fa-regular fa-comments"></i>

                    <h3>Aún no hay publicaciones</h3>

                    <p>
                        Todavía no se han compartido publicaciones en este espacio.
                        ¡Anímate a iniciar la conversación!
                    </p>

                    <?php if ($sesion_iniciada): ?>

                        <a href="foro.php" class="btn-vino mt-3">
                            <i class="fa-solid fa-plus me-2"></i>
                            Crear primera publicación
                        </a>

                    <?php else: ?>

                        <a href="registro.php" class="btn-vino mt-3">
                            Crear una cuenta
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <!-- INVITACIÓN -->

    <section class="participacion">

        <div class="container">

            <div class="participacion-card text-center">

                <h2 class="fw-bold mb-3">
                    Tu historia también hace parte del parche
                </h2>

                <p class="mb-4">
                    Comparte tus experiencias, expresa tus ideas y participa
                    en las conversaciones sobre el mundo del tatuaje.
                </p>

                <?php if ($sesion_iniciada): ?>

                    <a href="foro.php" class="btn-vino">
                        <i class="fa-solid fa-pen-to-square me-2"></i>
                        Compartir una publicación
                    </a>

                <?php else: ?>

                    <a href="registro.php" class="btn-vino">
                        <i class="fa-solid fa-user-plus me-2"></i>
                        Unirme a la comunidad
                    </a>

                    <a href="login.php" class="btn-outline-light-custom ms-2">
                        Iniciar sesión
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

<!-- FOOTER -->

<footer class="footer-comunidad">

    <div class="container">

        <div class="row g-4">

            <div class="col-md-6">

                <h3>Parche del Tattoo</h3>

                <p>
                    Plataforma web desarrollada para conectar artistas,
                    estudios de tatuajes y amantes del arte corporal.
                </p>

                <p>
                    Proyecto académico desarrollado para la
                    Institución Universitaria Pascual Bravo.
                </p>

            </div>

            <div class="col-md-3">

                <h3>Enlaces</h3>

                <p><a href="index.php">Inicio</a></p>
                <p><a href="comunidad.php">Comunidad</a></p>
                <p><a href="diseños.php">Diseños</a></p>
                <p><a href="foro.php">Foro</a></p>
                <p><a href="contacto.php">Contacto</a></p>

            </div>

            <div class="col-md-3">

                <h3>Instituciones</h3>

                <img
                    src="img/pascual.jpg"
                    alt="Pascual Bravo"
                    class="img-fluid mb-3"
                    style="max-width:120px;">

                <br>

                <img
                    src="img/felix.png"
                    alt="Félix de Bedout Moreno"
                    class="img-fluid"
                    style="max-width:120px;">

            </div>

        </div>

        <div class="footer-bottom">

            <p>
                © 2026 Parche del Tattoo |
                Todos los derechos reservados.
            </p>

            <div class="d-flex justify-content-center gap-4">

                <a href="#" aria-label="Facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>

                <a href="#" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>

                <a href="#" aria-label="TikTok">
                    <i class="fab fa-tiktok"></i>
                </a>

                <a href="#" aria-label="WhatsApp">
                    <i class="fab fa-whatsapp"></i>
                </a>

            </div>

        </div>

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
