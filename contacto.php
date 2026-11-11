<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
    exit('Error de conexión con la base de datos.');
}

$conexion->set_charset('utf8mb4');

function h($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/* =========================================
   VERIFICAR SESIÓN Y USUARIO
========================================= */

$sesion_iniciada = isset($_SESSION['id_usuario']);
$usuario = null;
$tipo_usuario = null;

if ($sesion_iniciada) {
    $id_usuario = (int) $_SESSION['id_usuario'];

    $sql = "SELECT id_usuario, nombre_usuario, nombre_completo, email,
                   tipo_usuario, estado
            FROM usuarios
            WHERE id_usuario = ? AND estado = 1
            LIMIT 1";

    $stmt = $conexion->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('i', $id_usuario);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $usuario = $resultado->fetch_assoc();

        $stmt->close();
    }

    if ($usuario) {
        $tipo_usuario = $usuario['tipo_usuario'];
    } else {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }

        session_destroy();
        $sesion_iniciada = false;
        $usuario = null;
    }
}

/* =========================================
   TOKEN DE SEGURIDAD
========================================= */

if (empty($_SESSION['token_contacto'])) {
    $_SESSION['token_contacto'] = bin2hex(random_bytes(32));
}

$tokenContacto = $_SESSION['token_contacto'];

/* =========================================
   VARIABLES DEL FORMULARIO
========================================= */

$nombre = $usuario['nombre_completo'] ?? '';
$correo = $usuario['email'] ?? '';
$asunto = '';
$mensaje = '';

$alerta = '';
$tipoAlerta = '';

/* =========================================
   PROCESAR FORMULARIO
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje = trim($_POST['mensaje'] ?? '');

    try {

        if (
            !isset($_POST['token']) ||
            !hash_equals($tokenContacto, $_POST['token'])
        ) {
            throw new Exception('La solicitud no es válida. Recarga la página.');
        }

        if ($nombre === '' || $correo === '' || $asunto === '' || $mensaje === '') {
            throw new Exception('Todos los campos son obligatorios.');
        }

        if (mb_strlen($nombre) > 150) {
            throw new Exception('El nombre no puede superar los 150 caracteres.');
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Ingresa un correo electrónico válido.');
        }

        if (mb_strlen($correo) > 150) {
            throw new Exception('El correo electrónico es demasiado largo.');
        }

        if (mb_strlen($asunto) > 150) {
            throw new Exception('El asunto no puede superar los 150 caracteres.');
        }

        if (mb_strlen($mensaje) > 10000) {
            throw new Exception('El mensaje supera el límite permitido.');
        }

        /*
         * Se guarda en la tabla contactos.
         * fecha_contacto y atendido tienen sus valores
         * predeterminados definidos en la base de datos.
         */

        $sql = "INSERT INTO contactos
                (nombre, email, asunto, mensaje)
                VALUES (?, ?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            throw new Exception('No fue posible preparar el registro del mensaje.');
        }

        $stmt->bind_param(
            'ssss',
            $nombre,
            $correo,
            $asunto,
            $mensaje
        );

        if (!$stmt->execute()) {
            throw new Exception('No fue posible guardar el mensaje.');
        }

        $stmt->close();

        $alerta = '¡Mensaje enviado correctamente! Gracias por comunicarte con Parche del Tattoo.';
        $tipoAlerta = 'success';

        $nombre = $usuario['nombre_completo'] ?? '';
        $correo = $usuario['email'] ?? '';
        $asunto = '';
        $mensaje = '';

        $_SESSION['token_contacto'] = bin2hex(random_bytes(32));
        $tokenContacto = $_SESSION['token_contacto'];

    } catch (Throwable $e) {

        $alerta = $e->getMessage();
        $tipoAlerta = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Contacto | Parche del Tattoo</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
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
            font-size: 12px;
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

        /* CONTACTO */

        .contacto {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 35px;
            max-width: 1150px;
            margin: 55px auto;
            padding: 30px;
            align-items: center;
        }

        .contacto-info,
        .formulario {
            min-width: 0;
        }

        .contacto-info h1 {
            font-size: 36px;
            margin-bottom: 18px;
        }

        .contacto-info > p {
            line-height: 1.8;
            margin-bottom: 28px;
        }

        .dato {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }

        .dato i {
            color: #ff6a00;
            font-size: 21px;
            width: 25px;
        }

        .redes-contacto {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .redes-contacto a {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #252525;
            color: #fff;
            border-radius: 50%;
            transition: .2s;
        }

        .redes-contacto a:hover {
            background: #ff6a00;
            transform: translateY(-3px);
        }

        .formulario {
            background: #191919;
            border: 1px solid #383838;
            border-radius: 12px;
            padding: 28px;
        }

        .formulario h2 {
            margin: 0 0 8px;
            color: #ff6a00;
            font-size: 22px;
        }

        .formulario > p {
            color: #aaa;
            font-size: 13px;
            margin-bottom: 22px;
        }

        .formulario form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .formulario input,
        .formulario textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 13px 14px;
            border: 1px solid #444;
            border-radius: 7px;
            background: #101010;
            color: #fff;
            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .formulario input:focus,
        .formulario textarea:focus {
            border-color: #ff6a00;
        }

        .formulario input::placeholder,
        .formulario textarea::placeholder {
            color: #888;
        }

        .formulario textarea {
            min-height: 145px;
            resize: vertical;
        }

        .formulario button {
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #ff6a00;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: .2s;
        }

        .formulario button:hover {
            background: #d95700;
        }

        .formulario button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .respuesta-contacto {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 13px;
            line-height: 1.6;
        }

        .respuesta-contacto.success {
            background: #163d27;
            border: 1px solid #267a48;
            color: #a9efbd;
        }

        .respuesta-contacto.error {
            background: #4a1d1d;
            border: 1px solid #a33;
            color: #ffb2b2;
        }

        .campo-ayuda {
            font-size: 11px;
            color: #999;
            margin-top: -9px;
        }

        .mapa {
            max-width: 1150px;
            margin: 0 auto 45px;
            padding: 0 30px;
        }

        .mapa iframe {
            width: 100%;
            height: 330px;
            border: 0;
            border-radius: 12px;
        }

        @media (max-width: 850px) {
            .contacto {
                grid-template-columns: 1fr;
                margin: 25px auto;
                padding: 20px;
            }

            .contacto-info h1 {
                font-size: 29px;
            }
        }

        @media (max-width: 550px) {
            .formulario {
                padding: 19px;
            }

            .mapa {
                padding: 0 15px;
            }

            .mapa iframe {
                height: 250px;
            }

            .usuario-sesion {
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>

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

        <!-- Inicio siempre está visible -->
        <a href="index.php">
            <i class="fa-solid fa-house"></i> Inicio
        </a>

        <?php if ($sesion_iniciada): ?>

            <!-- Menú completo para cualquier usuario autenticado -->
            <a href="admin.php">
                <i class="fa-solid fa-gauge-high"></i> Administración
            </a>

            <a href="comunidad.php">
                <i class="fa-solid fa-users"></i> Comunidad
            </a>

            <a href="diseños.php">
                <i class="fa-solid fa-palette"></i> Diseños
            </a>

            <a href="foro.php">
                <i class="fa-solid fa-comments"></i> Foro
            </a>

            <a href="blog.php">
                <i class="fa-solid fa-blog"></i> Blog
            </a>

            <a href="piercings.php">
                <i class="fa-solid fa-gem"></i> Piercings
            </a>

            <a href="perfil.php">
                <i class="fa-solid fa-user"></i> Mi perfil
            </a>

            <a href="logout.php" class="btn-cerrar-sesion">
                <i class="fa-solid fa-right-from-bracket"></i>
                Cerrar sesión
            </a>

        <?php else: ?>

            <!-- Menú para visitantes sin sesión iniciada -->
            <a href="login.php">
                <i class="fa-solid fa-right-to-bracket"></i>
                Iniciar sesión
            </a>

        <?php endif; ?>

    </nav>

    <?php if ($sesion_iniciada && $usuario): ?>

        <div class="usuario-sesion">
            <i class="fa-solid fa-circle-user"></i>

            <div>
                <strong><?= h($usuario['nombre_usuario']) ?></strong>
                <br>
                <span class="tipo-usuario"><?= h($tipo_usuario) ?></span>
            </div>
        </div>

    <?php endif; ?>

</header>

<main>

    <section class="contacto">

        <div class="contacto-info">

            <h1>Contáctanos</h1>

            <p>
                ¿Tienes dudas, sugerencias o deseas promocionar tu
                estudio de tatuajes? Escríbenos y nos pondremos
                en contacto contigo.
            </p>

            <div class="dato">
                <i class="fa-solid fa-location-dot"></i>
                <span>Medellín - Colombia</span>
            </div>

            <div class="dato">
                <i class="fa-solid fa-phone"></i>
                <span>+57 300 123 4567</span>
            </div>

            <div class="dato">
                <i class="fa-solid fa-envelope"></i>
                <span>contacto@parchedeltattoo.com</span>
            </div>

            <div class="redes-contacto">

                <a href="#" aria-label="Facebook">
                    <i class="fab fa-facebook"></i>
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

        <div class="formulario">

            <h2>Envíanos un mensaje</h2>

            <p>
                Completa el formulario y tu mensaje quedará registrado
                en nuestra plataforma.
            </p>

            <?php if ($alerta !== ''): ?>

                <div class="respuesta-contacto <?= h($tipoAlerta) ?>"
                     role="status"
                     aria-live="polite">

                    <i class="fa-solid <?= $tipoAlerta === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>

                    <?= h($alerta) ?>

                </div>

            <?php endif; ?>

            <form method="POST" action="contacto.php">

                <input type="hidden"
                       name="token"
                       value="<?= h($tokenContacto) ?>">

                <input type="text"
                       id="nombre"
                       name="nombre"
                       placeholder="Nombre completo"
                       maxlength="150"
                       value="<?= h($nombre) ?>"
                       required>

                <input type="email"
                       id="correo"
                       name="correo"
                       placeholder="Correo electrónico"
                       maxlength="150"
                       value="<?= h($correo) ?>"
                       required>

                <input type="text"
                       id="asunto"
                       name="asunto"
                       placeholder="Asunto"
                       maxlength="150"
                       value="<?= h($asunto) ?>"
                       required>

                <textarea id="mensaje"
                          name="mensaje"
                          maxlength="10000"
                          placeholder="Escribe tu mensaje..."
                          required><?= h($mensaje) ?></textarea>

                <button type="submit">
                    <i class="fa-solid fa-paper-plane"></i>
                    Enviar mensaje
                </button>

            </form>

        </div>

    </section>

    <section class="mapa">

        <iframe
            title="Mapa de Medellín, Colombia"
            src="https://www.google.com/maps?q=Medell%C3%ADn%2CColombia&output=embed"
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>

    </section>

</main>

<footer>
    <p>© 2026 Parche del Tattoo | Todos los derechos reservados.</p>
</footer>

</body>
</html>
