
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "conexion.php";

$sesion_iniciada = isset($_SESSION["id_usuario"]);
$usuario = null;
$tipo_usuario = null;

if ($sesion_iniciada) {
    $id_usuario = (int) $_SESSION["id_usuario"];

    $sql = "SELECT id_usuario, nombre_usuario, nombre_completo, email, tipo_usuario
            FROM usuarios
            WHERE id_usuario = ? AND estado = 1
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado && $resultado->num_rows === 1) {
            $usuario = $resultado->fetch_assoc();
            $tipo_usuario = $usuario["tipo_usuario"];
        } else {
            $_SESSION = [];
            session_destroy();
            $sesion_iniciada = false;
        }

        $stmt->close();
    } else {
        $sesion_iniciada = false;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Blog | Parche del Tattoo</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>

        /* =====================================
           INFORMACIÓN DEL USUARIO
        ===================================== */

        .usuario-sesion {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            margin-left: 15px;
        }

        .usuario-sesion i,
        .tipo-usuario {
            color: #ff6a00;
        }

        .tipo-usuario {
            font-weight: bold;
            font-size: 13px;
        }

        .btn-cerrar-sesion {
            background: #dc3545 !important;
            color: #fff !important;
            border-radius: 8px;
            padding: 8px 14px !important;
        }

        .btn-cerrar-sesion:hover {
            background: #b02a37 !important;
        }


        /* =====================================
           BOTÓN LEER MÁS
        ===================================== */

        .btn-leer-mas {
            display: inline-block;
            margin-top: 15px;
            padding: 0;
            border: none;
            background: none;
            color: #fff;
            font-size: 16px;
            font-family: inherit;
            text-decoration: underline;
            cursor: pointer;
        }

        .btn-leer-mas:hover {
            color: #ff6a00;
        }


        /* =====================================
           FONDO OSCURO DEL MODAL
        ===================================== */

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            box-sizing: border-box;

            background: rgba(0, 0, 0, 0.85);

            z-index: 99999;

            justify-content: center;
            align-items: center;

            padding: 20px;
            overflow-y: auto;
        }

        .modal-overlay.activo {
            display: flex;
        }


        /* =====================================
           TARJETA EMERGENTE
        ===================================== */

        .modal-ventana {
            position: relative;

            background: #181818;
            color: white;

            width: 850px;
            max-width: 95%;
            max-height: 85vh;

            overflow-y: auto;

            border-radius: 15px;
            padding: 40px;

            box-sizing: border-box;

            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.7);

            animation: aparecerModal 0.3s ease;
        }


        /* =====================================
           ANIMACIÓN DEL MODAL
        ===================================== */

        @keyframes aparecerModal {

            from {
                opacity: 0;
                transform: scale(0.85);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }

        }


        /* =====================================
           BOTÓN CERRAR
        ===================================== */

        .modal-cerrar {
            position: absolute;

            top: 12px;
            right: 18px;

            background: transparent;
            border: none;

            color: white;

            font-size: 35px;
            font-weight: bold;

            cursor: pointer;
        }

        .modal-cerrar:hover {
            color: #ff6a00;
        }


        /* =====================================
           TÍTULOS DEL MODAL
        ===================================== */

        .modal-ventana h2 {
            color: #ff6a00;
            font-size: 28px;
            margin-bottom: 20px;
            padding-right: 35px;
        }

        .modal-ventana h3 {
            color: #ff6a00;
            margin: 20px 0 10px;
        }


        /* =====================================
           TEXTOS DEL MODAL
        ===================================== */

        .modal-ventana p {
            color: #eee;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .modal-ventana ul {
            padding-left: 25px;
            line-height: 2;
            color: #eee;
        }

        .modal-ventana li {
            margin-bottom: 5px;
        }


        /* =====================================
           DISEÑO RESPONSIVE
        ===================================== */

        @media (max-width: 600px) {

            .modal-overlay {
                padding: 10px;
            }

            .modal-ventana {
                padding: 45px 20px 25px;
                max-width: 100%;
            }

            .modal-ventana h2 {
                font-size: 22px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================
     ENCABEZADO Y NAVEGACIÓN
===================================== -->

<header>

    <div class="logo">

        <img src="img/image-removebg-preview.png"
             alt="Logo Parche del Tattoo"
             class="logo-img">

        <h2>Parche del <span>Tattoo</span></h2>

    </div>


    <div class="buscador">

        <input type="text"
               placeholder="Buscar personas..."
               aria-label="Buscar personas">

        <i class="fa-solid fa-magnifying-glass"></i>

    </div>


    <nav>

        <a href="index.php">
            <i class="fa-solid fa-house"></i> Inicio
        </a>


        <?php if ($sesion_iniciada && $tipo_usuario === "Cliente"): ?>


            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi Perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>


        <?php elseif ($sesion_iniciada && $tipo_usuario === "Artista"): ?>


            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi Perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>


        <?php else: ?>


            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comment"></i> Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i> Blog
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="contacto.php">
                <i class="fa-solid fa-envelope"></i> Contacto
            </a>


        <?php endif; ?>

    </nav>


    <?php if ($sesion_iniciada && $usuario): ?>

        <div class="usuario-sesion">

            <i class="fa-solid fa-circle-user"></i>

            <div>

                <strong>
                    <?= htmlspecialchars(
                        $usuario["nombre_usuario"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </strong>

                <br>

                <span class="tipo-usuario">
                    <?= htmlspecialchars(
                        $tipo_usuario,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>

            </div>

        </div>

    <?php endif; ?>

</header>


<!-- =====================================
     CONTENIDO PRINCIPAL DEL BLOG
===================================== -->

<main>

    <section class="blog">


        <h1>

            <i class="fa-solid fa-blog"></i>

            Blog de la Comunidad

        </h1>


        <p>

            Noticias, tendencias, consejos y experiencias
            del mundo del tatuaje.

        </p>


        <div class="blog-grid">

            <article class="articulo">

                <div class="contenido">

                    <h2>Cómo cuidar un tatuaje recién hecho</h2>

                    <p>

                        Aprende los cuidados básicos para que tu tatuaje
                        cicatrice correctamente y conserve sus colores.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="Cómo cuidar un tatuaje recién hecho"
                            data-contenido="modal-cuidados">

                        Leer más →

                    </button>

                </div>

            </article>

            <article class="articulo">

                <div class="contenido">

                    <h2>Los estilos de tatuajes más populares</h2>

                    <p>

                        Descubre los estilos más utilizados por artistas
                        de todo el mundo.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="Los estilos de tatuajes más populares"
                            data-contenido="modal-estilos">

                        Leer más →

                    </button>

                </div>

            </article>

            <article class="articulo">

                <div class="contenido">

                    <h2>¿Cómo elegir un buen tatuador?</h2>

                    <p>

                        Consejos para escoger un profesional y evitar
                        malas experiencias.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="¿Cómo elegir un buen tatuador?"
                            data-contenido="modal-tatuador">

                        Leer más →

                    </button>

                </div>

            </article>

            <article class="articulo">

                <div class="contenido">

                    <h2>Piercings: cuidados y recomendaciones</h2>

                    <p>

                        Todo lo que debes saber antes y después
                        de realizarte un piercing.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="Piercings: cuidados y recomendaciones"
                            data-contenido="modal-piercings">

                        Leer más →

                    </button>

                </div>

            </article>

            <article class="articulo">

                <div class="contenido">

                    <h2>Tendencias de tatuajes para 2026</h2>

                    <p>

                        Conoce algunos diseños y estilos que inspiran
                        a la comunidad este año.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="Tendencias de tatuajes para 2026"
                            data-contenido="modal-tendencias">

                        Leer más →

                    </button>

                </div>

            </article>

            <article class="articulo">

                <div class="contenido">

                    <h2>Conoce la comunidad Parche del Tattoo</h2>

                    <p>

                        Una plataforma creada para artistas y amantes
                        del arte corporal.

                    </p>

                    <button type="button"
                            class="btn-leer-mas"
                            data-titulo="Conoce la comunidad Parche del Tattoo"
                            data-contenido="modal-comunidad">

                        Leer más →

                    </button>

                </div>

            </article>


        </div>

    </section>

</main>


<!-- =====================================
     CONTENIDO DE LOS SEIS MODALES
===================================== -->

<template id="modal-cuidados">

    <h3>Consejos para cuidar tu tatuaje</h3>

    <p>
        Un tatuaje recién hecho necesita cuidados especiales
        para cicatrizar correctamente y conservar sus colores.
    </p>

    <ul>
        <li>Lávate las manos antes de tocar el tatuaje.</li>

        <li>Limpia suavemente la zona con agua y jabón neutro.</li>

        <li>Aplica la crema recomendada por tu tatuador.</li>

        <li>No rasques ni retires las costras.</li>

        <li>Evita piscinas y exposición directa al sol mientras cicatriza.</li>
    </ul>

    <p>
        Recuerda seguir las recomendaciones de tu tatuador.
        Si notas señales de infección, consulta con un profesional de salud.
    </p>

</template>


<template id="modal-estilos">

    <h3>Conoce los estilos de tatuajes</h3>

    <p>
        Existen muchos estilos de tatuajes. Cada uno tiene
        características diferentes.
    </p>

    <ul>

        <li>
            <strong>Realismo:</strong>
            representa personas, animales y objetos con muchos detalles.
        </li>

        <li>
            <strong>Tradicional:</strong>
            utiliza líneas gruesas y colores intensos.
        </li>

        <li>
            <strong>Minimalista:</strong>
            diseños pequeños, sencillos y elegantes.
        </li>

        <li>
            <strong>Geométrico:</strong>
            combina figuras, líneas y patrones.
        </li>

        <li>
            <strong>Blackwork:</strong>
            utiliza principalmente tinta negra.
        </li>

        <li>
            <strong>Japonés:</strong>
            incorpora elementos tradicionales de la cultura japonesa.
        </li>

    </ul>

    <p>
        Antes de elegir tu tatuaje, busca referencias y selecciona
        un estilo que represente tu personalidad.
    </p>

</template>


<template id="modal-tatuador">

    <h3>Consejos para elegir un buen tatuador</h3>

    <p>
        Elegir un buen profesional es fundamental para obtener
        un resultado satisfactorio y seguro.
    </p>

    <ul>

        <li>Revisa sus trabajos y su portafolio.</li>

        <li>Comprueba que tenga experiencia en el estilo que buscas.</li>

        <li>Verifica que trabaje en un establecimiento higiénico.</li>

        <li>Asegúrate de que utilice agujas nuevas y material esterilizado.</li>

        <li>Pregunta por los cuidados posteriores.</li>

        <li>Consulta las opiniones de otros clientes.</li>

    </ul>

    <p>
        Un buen tatuador debe escuchar tus ideas,
        resolver tus dudas y ofrecerte confianza.
    </p>

</template>


<template id="modal-piercings">

    <h3>Cuidados básicos de un piercing</h3>

    <p>
        Los piercings necesitan cuidados para evitar complicaciones
        durante el proceso de cicatrización.
    </p>

    <ul>

        <li>Lávate las manos antes de tocar la perforación.</li>

        <li>Limpia la zona siguiendo las recomendaciones del perforador.</li>

        <li>No gires ni manipules la joya innecesariamente.</li>

        <li>Evita cambiar la joya antes de que cicatrice.</li>

        <li>Evita golpes y presión sobre la zona.</li>

    </ul>

    <p>
        Si experimentas dolor intenso, hinchazón creciente
        o secreciones anormales, consulta con un profesional de salud.
    </p>

</template>


<template id="modal-tendencias">

    <h3>Ideas para tu próximo tatuaje</h3>

    <p>
        Inspírate con diferentes diseños para encontrar
        el tatuaje que mejor represente tu personalidad.
    </p>

    <ul>

        <li>Diseños minimalistas y de líneas finas.</li>

        <li>Tatuajes inspirados en la naturaleza.</li>

        <li>Composiciones geométricas.</li>

        <li>Diseños de estilo japonés.</li>

        <li>Tatuajes personalizados con significado.</li>

    </ul>

    <p>
        Piensa en el significado, tamaño y ubicación del diseño
        antes de tomar tu decisión.
    </p>

</template>


<template id="modal-comunidad">

    <h3>¡Forma parte de nuestra comunidad!</h3>

    <p>
        Parche del Tattoo es un espacio creado para reunir
        artistas, tatuadores y amantes del arte corporal.
    </p>

    <ul>

        <li>Comparte tus diseños y creaciones.</li>

        <li>Conoce el trabajo de otros artistas.</li>

        <li>Participa en las conversaciones del foro.</li>

        <li>Descubre nuevas ideas e inspiraciones.</li>

        <li>Conecta con personas que comparten tu pasión.</li>

    </ul>

    <p>
        ¡Explora nuestra plataforma y comparte tu pasión
        por el mundo del tatuaje!
    </p>

</template>


<!-- =====================================
     VENTANA MODAL PRINCIPAL
===================================== -->

<div class="modal-overlay"
     id="modal-articulo"
     aria-hidden="true">

    <div class="modal-ventana"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modal-titulo">

        <button type="button"
                class="modal-cerrar"
                id="cerrar-modal"
                aria-label="Cerrar ventana">

            &times;

        </button>

        <h2 id="modal-titulo"></h2>

        <div id="modal-contenido"></div>

    </div>

</div>


<!-- =====================================
     PIE DE PÁGINA
===================================== -->

<footer>

    <p>
        © 2026 Parche del Tattoo | Todos los derechos reservados.
    </p>

</footer>


<!-- =====================================
     JAVASCRIPT DEL MODAL
===================================== -->

<script>

// Obtener los elementos del modal

const modal = document.getElementById("modal-articulo");

const tituloModal = document.getElementById("modal-titulo");

const contenidoModal = document.getElementById("modal-contenido");

const botonCerrar = document.getElementById("cerrar-modal");

let elementoAnterior = null;


// =====================================
// ABRIR MODAL
// =====================================

document.querySelectorAll(".btn-leer-mas").forEach(boton => {

    boton.addEventListener("click", function() {

        // Obtener título y contenido del artículo

        const titulo = this.getAttribute("data-titulo");

        const contenido = this.getAttribute("data-contenido");

        // Buscar la plantilla correspondiente

        const plantilla = document.getElementById(contenido);

        // Comprobar que exista el contenido

        if (!plantilla) {
            return;
        }

        // Guardar el botón que abrió la ventana

        elementoAnterior = this;

        // Colocar el título del artículo

        tituloModal.textContent = titulo;

        // Colocar el contenido correspondiente

        contenidoModal.replaceChildren(
            plantilla.content.cloneNode(true)
        );

        // Mostrar el modal

        modal.classList.add("activo");

        modal.setAttribute("aria-hidden", "false");

        // Bloquear desplazamiento de la página

        document.body.style.overflow = "hidden";

        // Enfocar botón de cerrar

        botonCerrar.focus();

    });

});


// =====================================
// CERRAR MODAL
// =====================================

function cerrarModal() {

    modal.classList.remove("activo");

    modal.setAttribute("aria-hidden", "true");

    // Recuperar desplazamiento de la página

    document.body.style.overflow = "";

    // Regresar el enfoque al botón anterior

    if (elementoAnterior) {

        elementoAnterior.focus();

    }

}


// =====================================
// CERRAR CON LA X
// =====================================

botonCerrar.addEventListener("click", cerrarModal);


// =====================================
// CERRAR AL PRESIONAR FUERA DE LA TARJETA
// =====================================

modal.addEventListener("click", function(evento) {

    if (evento.target === modal) {

        cerrarModal();

    }

});


// =====================================
// CERRAR CON LA TECLA ESC
// =====================================

document.addEventListener("keydown", function(evento) {

    if (
        evento.key === "Escape" &&
        modal.classList.contains("activo")
    ) {

        cerrarModal();

    }

});

</script>


</body>

</html>
