
<?php
session_start();

require_once __DIR__ . "/conexion.php";

$error = "";

// Verificar que la conexión exista
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    die("Error: revisa el archivo conexion.php y la conexión con la base de datos.");
}

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($correo === "" || $password === "") {

        $error = "Complete todos los campos.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $error = "Ingrese un correo electrónico válido.";

    } else {

        /*
         * Consultar el usuario.
         * Verifica que email, estado y password sean los nombres
         * reales de las columnas de la tabla usuarios.
         */
        $sql = "SELECT id_usuario, nombre_usuario, nombre_completo,
                       email, password, tipo_usuario, estado
                FROM usuarios
                WHERE email = ? AND estado = 1
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {

            // Mensaje técnico para identificar el problema
            error_log("Error SQL en login.php: " . $conexion->error);
            $error = "No se pudo realizar la consulta. Verifique la tabla usuarios y sus columnas.";

        } else {

            $stmt->bind_param("s", $correo);

            if (!$stmt->execute()) {

                error_log("Error ejecutando login.php: " . $stmt->error);
                $error = "Ocurrió un error al consultar el usuario.";

            } else {

                $resultado = $stmt->get_result();

                if ($usuario = $resultado->fetch_assoc()) {

                    $claveGuardada = (string) $usuario["password"];

                    // Comprobar contraseña con hash o texto antiguo
                    $esHash = password_get_info($claveGuardada)["algo"] !== null
                              && password_get_info($claveGuardada)["algo"] !== 0;

                    $passwordCorrecta = false;

                    if ($esHash) {
                        $passwordCorrecta = password_verify(
                            $password,
                            $claveGuardada
                        );
                    } else {
                        // Compatibilidad temporal con contraseñas antiguas
                        $passwordCorrecta = hash_equals(
                            $claveGuardada,
                            $password
                        );
                    }

                    if ($passwordCorrecta) {

                        // Convertir contraseñas antiguas a hash seguro
                        if (!$esHash ||
                            password_needs_rehash(
                                $claveGuardada,
                                PASSWORD_DEFAULT
                            )) {

                            $nuevoHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $actualizar = $conexion->prepare(
                                "UPDATE usuarios SET password = ? WHERE id_usuario = ?"
                            );

                            if ($actualizar) {
                                $actualizar->bind_param(
                                    "si",
                                    $nuevoHash,
                                    $usuario["id_usuario"]
                                );
                                $actualizar->execute();
                                $actualizar->close();
                            }
                        }

                        // Crear una sesión nueva y segura
                        session_regenerate_id(true);

                        $_SESSION["id_usuario"] = (int) $usuario["id_usuario"];
                        $_SESSION["nombre_usuario"] = $usuario["nombre_usuario"];
                        $_SESSION["nombre_completo"] = $usuario["nombre_completo"];
                        $_SESSION["email"] = $usuario["email"];
                        $_SESSION["tipo_usuario"] = $usuario["tipo_usuario"];

                        // Redirigir según el rol
                        if ($usuario["tipo_usuario"] === "Administrador") {

                            // Usa el nombre real de tu archivo de administrador
                            header("Location: admin.php");
                            exit;

                        } else {

                            header("Location: index.php");
                            exit;

                        }

                    } else {

                        $error = "Correo o contraseña incorrectos.";

                    }

                } else {

                    $error = "Correo o contraseña incorrectos.";

                }
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar Sesión | Parche del Tattoo</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body class="login-body">

<div class="login-container">

    <div class="login-left">

        <h1>Parche del <span>Tattoo</span></h1>

        <p>
            Parchate a compartir tus diseños, conocer artistas,
            u otras personas! ¡Unete ya a la comunidad!
        </p>

        <img src="./img/image-removebg-preview.png" alt="Logo">

    </div>

    <div class="login-right">

        <form id="loginForm" method="POST" action="login.php">

            <h2>Iniciar Sesión</h2>

            <?php if ($error !== ""): ?>
                <div class="mensaje-error">
                    <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                </div>
            <?php endif; ?>

            <div class="input-box">
                <i class="fa fa-envelope"></i>

                <input
                    type="email"
                    name="correo"
                    id="correo"
                    placeholder="Correo electrónico"
                    value="<?= htmlspecialchars($_POST["correo"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                    autocomplete="username"
                    required>
            </div>

            <div class="input-box">
                <i class="fa fa-lock"></i>

                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Contraseña"
                    autocomplete="current-password"
                    required>
            </div>

            <div class="opciones">

                <label>
                    <input type="checkbox" name="recordarme">
                    Recordarme
                </label>

                <a href="#">
                    ¿Olvidaste tu contraseña?
                </a>

            </div>

            <button type="submit">
                <i class="fa-solid fa-right-to-bracket"></i>
                Ingresar
            </button>

            <p class="registro">
                ¿No tienes cuenta?
                <a href="registro.php">Regístrate</a>
            </p>

            <div class="social">

                <h4>o inicia con</h4>

                <div class="iconos">

                    <a href="#" aria-label="Google">
                        <i class="fab fa-google"></i>
                    </a>

                    <a href="#" aria-label="Facebook">
                        <i class="fab fa-facebook"></i>
                    </a>

                    <a href="#" aria-label="GitHub">
                        <i class="fab fa-github"></i>
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>

</body>
</html>
