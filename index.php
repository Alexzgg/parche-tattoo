
<?php

session_start();

include("conexion.php");

/*
====================================================
VERIFICAR SI HAY UN USUARIO INICIADO
====================================================
*/

$sesion_iniciada = isset($_SESSION["id_usuario"]);

$usuario = null;
$tipo_usuario = null;

if ($sesion_iniciada) {

    $id_usuario = $_SESSION["id_usuario"];

    $sql = "SELECT
                id_usuario,
                nombre_usuario,
                nombre_completo,
                email,
                tipo_usuario
            FROM usuarios
            WHERE id_usuario = ?
            AND estado = 1
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param("i", $id_usuario);

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        $tipo_usuario = $usuario["tipo_usuario"];

    } else {

        session_unset();
        session_destroy();

        $sesion_iniciada = false;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Parche del Tattoo</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <!-- CSS -->
    <link
        rel="stylesheet"
        href="css/style.css">

    <style>

        /* =====================================================
           USUARIO LOGUEADO
        ===================================================== */

        .usuario-sesion {

            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            margin-left: 15px;

        }

        .usuario-sesion i {
            color: #ff6a00;
        }

        .tipo-usuario {

            color: #ff6a00;
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

        /* =====================================================
           OPCIONES PARA ARTISTA
        ===================================================== */

        .artista-panel {

            background: #171717;
            border: 1px solid #333;
            border-radius: 15px;
            padding: 25px;
            margin: 30px auto;
            max-width: 1100px;
            color: white;

        }

        .artista-panel h2 {

            color: #ff6a00;
            margin-bottom: 10px;

        }

        .artista-panel p {
            color: #ccc;
        }

        .botones-artista {

            display: flex;
            gap: 15px;
            flex-wrap: wrap;

        }

        .botones-artista a {
            text-decoration: none;
        }

        /* =====================================================
           MENSAJE CLIENTE
        ===================================================== */

        .cliente-panel {

            background: #171717;
            border: 1px solid #333;
            border-radius: 15px;
            padding: 25px;
            margin: 30px auto;
            max-width: 1100px;
            color: white;

        }

        .cliente-panel h2 {
            color: #ff6a00;
        }

    </style>

</head>

<body>

<!-- =====================================================
     MENU
===================================================== -->

<header>

    <!-- LOGO -->

    <div class="logo">

        <img
            src="./img/image-removebg-preview.png"
            alt="Logo Parche del Tattoo"
            class="logo-img">

        <h2>
            Parche del <span>Tattoo</span>
        </h2>

    </div>

    <!-- BUSCADOR -->

    <div class="buscador">

        <input
            type="text"
            placeholder="Buscar personas...">

        <i class="fa-solid fa-magnifying-glass"></i>

    </div>

    <!-- =================================================
         NAVEGACIÓN
    ================================================= -->

    <nav>

        <!-- INICIO -->

        <a href="index.php">

            <i class="fa-solid fa-house"></i>

            Inicio

        </a>

        <!-- =========================================
             OPCIONES PARA ADMINISTRADOR
        ========================================== -->

        <?php if ($sesion_iniciada && (
            $tipo_usuario === "Administrador" ||
            (isset($usuario['email']) && $usuario['email'] === 'adminparche@example.com')
        )): ?>

            <a href="admin.php" class="btn-tattoo btn-principal">

                <i class="fa-solid fa-user-shield"></i>

                Panel Administrador

            </a>

            <a href="comunidad.php">

                <i class="fa-solid fa-users"></i>

                Comunidad

            </a>

            <a href="foro.php">

                <i class="fa-solid fa-comment"></i>

                Foro

            </a>

            <a href="perfil.php">

                <i class="fa-solid fa-user"></i>

                Mi Perfil

            </a>

            <a href="logout.php" class="btn-cerrar-sesion">

                <i class="fa-solid fa-right-from-bracket"></i>

                Cerrar sesión

            </a>

        <?php endif; ?>


        <!-- =========================================
             OPCIONES PARA CLIENTE Y ARTISTA
        ========================================== -->

        <?php if ($sesion_iniciada && $tipo_usuario !== "Administrador"): ?>


            <!-- OPCIONES PARA CLIENTE -->

            <?php if ($tipo_usuario === "Cliente"): ?>

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

            <?php endif; ?>


            <!-- OPCIONES PARA ARTISTA -->

            <?php if ($tipo_usuario === "Artista"): ?>

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

            <?php endif; ?>

        <?php endif; ?>


        <!-- =========================================
             USUARIO NO LOGUEADO
        ========================================== -->

        <?php if (!$sesion_iniciada): ?>

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


    <!-- =================================================
         INFORMACIÓN DEL USUARIO
    ================================================= -->

    <?php if ($sesion_iniciada && $usuario): ?>

        <div class="usuario-sesion">

            <i class="fa-solid fa-circle-user"></i>

            <div>

                <strong>

                    <?= htmlspecialchars($usuario["nombre_usuario"]) ?>

                </strong>

                <br>

                <span class="tipo-usuario">

                    <?= htmlspecialchars($tipo_usuario) ?>

                </span>

            </div>

        </div>

    <?php endif; ?>

</header>


<!-- =====================================================
     HERO
===================================================== -->

<section class="hero">

    <div class="container">

        <div class="row align-items-center">

            <!-- TEXTO -->

            <div class="col-lg-6">

                <?php if ($sesion_iniciada && $usuario): ?>

                    <span class="etiqueta">

                        Bienvenido,
                        <?= htmlspecialchars($usuario["nombre_usuario"]) ?>

                    </span>

                <?php else: ?>

                    <span class="etiqueta">

                        La red social del Tattoo

                    </span>

                <?php endif; ?>


                <h1>

                    Comparte tu arte,

                    <span>

                        inspira al mundo.

                    </span>

                </h1>


                <p>

                    Descubre artistas, comparte tus diseños,
                    encuentra estudios certificados y conecta
                    con la comunidad de tatuadores más grande
                    de Colombia.

                </p>


                <!-- BOTONES SEGÚN TIPO DE USUARIO -->

                <?php if (!$sesion_iniciada): ?>

                    <a
                        href="diseños.php"
                        class="btn-tattoo btn-principal">

                        Explorar Diseños

                    </a>

                    <a
                        href="registro.php"
                        class="btn-tattoo btn-secundario">

                        Crear Cuenta

                    </a>


                <?php elseif ($tipo_usuario === "Cliente"): ?>

                    <a
                        href="diseños.php"
                        class="btn-tattoo btn-principal">

                        <i class="fa-solid fa-palette"></i>

                        Explorar Diseños

                    </a>

                    <a
                        href="comunidad.php"
                        class="btn-tattoo btn-secundario">

                        <i class="fa-solid fa-users"></i>

                        Ver Comunidad

                    </a>


                <?php elseif ($tipo_usuario === "Artista"): ?>

                    <!-- SE CONSERVA MIS DISEÑOS, SE ELIMINA PUBLICAR DISEÑO -->

                    <a
                        href="diseños.php"
                        class="btn-tattoo btn-secundario">

                        <i class="fa-solid fa-palette"></i>

                        Mis Diseños

                    </a>

                <?php endif; ?>

            </div>


            <!-- CARRUSEL -->

            <div class="col-lg-6">

                <div
                    id="carouselTattoo"
                    class="carousel slide shadow-lg rounded overflow-hidden"
                    data-bs-ride="carousel">

                    <div class="carousel-inner">

                        <div class="carousel-item active">

                            <img
                                src="img/espinadorsal.jpg"
                                class="d-block w-100"
                                alt="Tatuaje espalda">

                        </div>

                        <div class="carousel-item">

                            <img
                                src="img/cuervo.jpg"
                                class="d-block w-100"
                                alt="Tatuaje cuervo">

                        </div>

                        <div class="carousel-item">

                            <img
                                src="img/samurai.jpg"
                                class="d-block w-100"
                                alt="Tatuaje Samurai">

                        </div>

                        <div class="carousel-item">

                            <img
                                src="img/calavera.jpg"
                                class="d-block w-100"
                                alt="Tatuaje calavera">

                        </div>

                        <div class="carousel-item">

                            <img
                                src="img/pircilavios.png"
                                class="d-block w-100"
                                alt="Piercing">

                        </div>

                    </div>


                    <button
                        class="carousel-control-prev"
                        type="button"
                        data-bs-target="#carouselTattoo"
                        data-bs-slide="prev">

                        <span class="carousel-control-prev-icon"></span>

                    </button>


                    <button
                        class="carousel-control-next"
                        type="button"
                        data-bs-target="#carouselTattoo"
                        data-bs-slide="next">

                        <span class="carousel-control-next-icon"></span>

                    </button>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =====================================================
     PANEL CLIENTE
===================================================== -->

<?php if ($sesion_iniciada && $tipo_usuario === "Cliente"): ?>

    <section class="cliente-panel">

        <h2>

            <i class="fa-solid fa-user"></i>

            Espacio para clientes

        </h2>

        <p>

            Desde aquí puedes explorar diseños, conocer artistas,
            participar en la comunidad y contactar artistas.

        </p>

    </section>

<?php endif; ?>


<!-- =====================================================
     PANEL ARTISTA
===================================================== -->

<?php if ($sesion_iniciada && $tipo_usuario === "Artista"): ?>

    <section class="artista-panel">

        <h2>

            <i class="fa-solid fa-palette"></i>

            Panel del artista

        </h2>

        <p>

            Desde aquí puedes mostrar tu trabajo a la comunidad
            y contactar con posibles clientes.

        </p>

        <div class="botones-artista">

            <a
                href="perfil.php"
                class="btn-tattoo btn-secundario">

                <i class="fa-solid fa-user"></i>

                Mi perfil

            </a>

            <a
                href="mensajes.php"
                class="btn-tattoo btn-secundario">

                <i class="fa-solid fa-envelope"></i>

                Ver mensajes

            </a>

        </div>

    </section>

<?php endif; ?>


<!-- =====================================================
     CARACTERÍSTICAS
===================================================== -->

<section class="caracteristicas">

    <div class="card">

        <i class="fa-solid fa-users"></i>

        <h3>Comunidad</h3>

        <p>
            Conecta con artistas y aficionados.
        </p>

    </div>


    <div class="card">

        <i class="fa-solid fa-image"></i>

        <h3>Comparte</h3>

        <p>
            Publica tus mejores diseños.
        </p>

    </div>


    <div class="card">

        <i class="fa-solid fa-comments"></i>

        <h3>Interactúa</h3>

        <p>
            Comenta y crea nuevos parches.
        </p>

    </div>


    <div class="card">

        <i class="fa-solid fa-shield-halved"></i>

        <h3>Seguro</h3>

        <p>
            Un espacio respetuoso para todos.
        </p>

    </div>

</section>


<!-- =====================================================
     MODAL PERFIL
===================================================== -->

<div
    class="modal fade"
    id="perfilModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content perfil-modal">

            <button
                type="button"
                class="btn-close btn-close-white cerrar-modal"
                data-bs-dismiss="modal"
                aria-label="Cerrar">
            </button>


            <div class="perfil-header">

                <img
                    id="modalImagen"
                    src=""
                    alt="Foto de perfil"
                    class="perfil-imagen">

            </div>


            <div class="perfil-info">

                <h2 id="modalNombre">
                    Nombre Usuario
                </h2>

                <p
                    class="perfil-usuario"
                    id="modalUsuario">
                    @usuario
                </p>

                <p id="modalDescripcion">
                    Descripción del usuario.
                </p>


                <div class="perfil-estadisticas">

                    <div>

                        <strong id="modalTatuajes">0</strong>

                        <span>Tatuajes</span>

                    </div>

                    <div>

                        <strong id="modalSeguidores">0</strong>

                        <span>Seguidores</span>

                    </div>

                    <div>

                        <strong id="modalSiguiendo">0</strong>

                        <span>Siguiendo</span>

                    </div>

                </div>


                <div class="perfil-botones">

                    <button
                        type="button"
                        class="btn-tattoo btn-principal">

                        <i class="fa-solid fa-user-plus"></i>

                        Seguir

                    </button>

                    <button
                        type="button"
                        class="btn-tattoo btn-secundario">

                        <i class="fa-solid fa-message"></i>

                        Mensaje

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <div class="footer-contenedor">

        <div class="footer-info">

            <h2>Parche del Tattoo</h2>

            <p>

                Plataforma web desarrollada para conectar artistas,
                estudios de tatuajes y amantes del arte corporal.

            </p>

            <p>

                Proyecto académico desarrollado para la
                Institución Universitaria Pascual Bravo.

            </p>

        </div>


        <div class="footer-links">

            <h3>Enlaces</h3>

            <ul>

                <li>

                    <a href="index.php">
                        Inicio
                    </a>

                </li>


                <?php if ($sesion_iniciada): ?>

                    <li>

                        <a href="comunidad.php">
                            Comunidad
                        </a>

                    </li>

                    <li>

                        <a href="diseños.php">
                            Diseños
                        </a>

                    </li>

                    <li>

                        <a href="blog.php">
                            Blog
                        </a>

                    </li>

                <?php endif; ?>


                <li>

                    <a href="contacto.php">
                        Contacto
                    </a>

                </li>

            </ul>

        </div>


        <div class="footer-logos">

            <h3>Instituciones</h3>

            <img
                src="img/pascual.jpg"
                alt="Pascual Bravo">

            <img
                src="img/felix.png"
                alt="Félix de Bedout Moreno">

        </div>

    </div>


    <hr>


    <div class="footer-bottom">

        <p>

            © 2026 Parche del Tattoo |
            Todos los derechos reservados.

        </p>


        <div class="redes">

            <a href="#">
                <i class="fab fa-facebook-f"></i>
            </a>

            <a href="#">
                <i class="fab fa-instagram"></i>
            </a>

            <a href="#">
                <i class="fab fa-tiktok"></i>
            </a>

            <a href="#">
                <i class="fab fa-whatsapp"></i>
            </a>

        </div>

    </div>

</footer>


<!-- =====================================================
     JAVASCRIPT DEL MODAL
===================================================== -->

<script>

const tarjetasUsuarios =
    document.querySelectorAll(".usuario-card");


tarjetasUsuarios.forEach(tarjeta => {

    tarjeta.addEventListener("click", function () {

        const nombre =
            this.dataset.nombre;

        const usuario =
            this.dataset.usuario;

        const imagen =
            this.dataset.imagen;

        const descripcion =
            this.dataset.descripcion;

        const tatuajes =
            this.dataset.tatuajes;

        const seguidores =
            this.dataset.seguidores;

        const siguiendo =
            this.dataset.siguiendo;


        document.getElementById(
            "modalNombre"
        ).textContent = nombre;


        document.getElementById(
            "modalUsuario"
        ).textContent = usuario;


        document.getElementById(
            "modalImagen"
        ).src = imagen;


        document.getElementById(
            "modalDescripcion"
        ).textContent = descripcion;


        document.getElementById(
            "modalTatuajes"
        ).textContent = tatuajes;


        document.getElementById(
            "modalSeguidores"
        ).textContent = seguidores;


        document.getElementById(
            "modalSiguiendo"
        ).textContent = siguiendo;

    });

});

</script>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>
