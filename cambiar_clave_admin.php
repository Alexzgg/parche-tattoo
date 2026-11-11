
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/conexion.php";

$mensaje = "";
$tipoMensaje = "";

// Esta herramienta de recuperación solo debe usarse en localhost.
$ip = $_SERVER["REMOTE_ADDR"] ?? "";

if (!in_array($ip, ["127.0.0.1", "::1"], true)) {
    http_response_code(403);
    exit("Acceso denegado. Utiliza esta herramienta únicamente desde localhost.");
}

// Comprobar conexión
if (!isset($conexion) || !($conexion instanceof mysqli)) {
    exit("Error: revisa conexion.php. No se encontró la conexión \$conexion.");
}

// Comprobar que se seleccionó la base de datos correcta
if ($conexion->connect_errno) {
    exit("Error de conexión MySQL: " .
        htmlspecialchars($conexion->connect_error, ENT_QUOTES, "UTF-8"));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $correo = trim($_POST["correo"] ?? "");
    $claveNueva = $_POST["clave_nueva"] ?? "";
    $confirmarClave = $_POST["confirmar_clave"] ?? "";

    if ($correo === "" || $claveNueva === "" || $confirmarClave === "") {

        $mensaje = "Completa todos los campos.";
        $tipoMensaje = "error";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $mensaje = "Escribe un correo electrónico válido.";
        $tipoMensaje = "error";

    } elseif (strlen($claveNueva) < 10) {

        $mensaje = "La contraseña debe tener al menos 10 caracteres.";
        $tipoMensaje = "error";

    } elseif ($claveNueva !== $confirmarClave) {

        $mensaje = "Las contraseñas no coinciden.";
        $tipoMensaje = "error";

    } else {

        // Esta herramienta está limitada a la cuenta AdminParche.
        $sql = "SELECT id_usuario, nombre_usuario, email, tipo_usuario
                FROM usuarios
                WHERE email = ?
                  AND nombre_usuario = 'AdminParche'
                  AND tipo_usuario = 'Administrador'
                LIMIT 1";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {

            $mensaje = "Error SQL al buscar el administrador: " .
                $conexion->error;
            $tipoMensaje = "error";

        } else {

            $stmt->bind_param("s", $correo);

            if (!$stmt->execute()) {

                $mensaje = "Error al ejecutar la búsqueda: " .
                    $stmt->error;
                $tipoMensaje = "error";

                $stmt->close();

            } else {

                $resultado = $stmt->get_result();
                $usuario = $resultado
                    ? $resultado->fetch_assoc()
                    : null;

                $stmt->close();

                if (!$usuario) {

                    $mensaje = "No se encontró la cuenta AdminParche con ese correo y rol. Comprueba los datos en phpMyAdmin.";
                    $tipoMensaje = "error";

                } else {

                    $hash = password_hash(
                        $claveNueva,
                        PASSWORD_DEFAULT
                    );

                    if ($hash === false) {

                        $mensaje = "PHP no pudo generar el hash de la contraseña.";
                        $tipoMensaje = "error";

                    } else {

                        $sqlUpdate = "UPDATE usuarios
                                      SET password = ?
                                      WHERE id_usuario = ?
                                        AND nombre_usuario = 'AdminParche'
                                        AND tipo_usuario = 'Administrador'";

                        $actualizar = $conexion->prepare($sqlUpdate);

                        if (!$actualizar) {

                            $mensaje = "Error SQL al actualizar: " .
                                $conexion->error;
                            $tipoMensaje = "error";

                        } else {

                            $id = (int) $usuario["id_usuario"];

                            $actualizar->bind_param("si", $hash, $id);

                            if (!$actualizar->execute()) {

                                $mensaje = "Error al guardar la contraseña: " .
                                    $actualizar->error;
                                $tipoMensaje = "error";

                            } else {

                                $mensaje = "Contraseña actualizada correctamente. Ya puedes entrar a login.php con el correo del administrador y la nueva contraseña.";
                                $tipoMensaje = "exito";

                            }

                            $actualizar->close();
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña | Parche del Tattoo</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #101010;
            color: #fff;
            font-family: Arial, sans-serif;
        }

        .contenedor {
            width: 100%;
            max-width: 430px;
            padding: 30px;
            background: #1b1b1b;
            border: 1px solid #333;
            border-radius: 14px;
            box-shadow: 0 8px 30px #0008;
        }

        h1 {
            margin-top: 0;
            text-align: center;
            font-size: 26px;
        }

        h1 span {
            color: #ff6a00;
        }

        p {
            color: #ccc;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 16px 0 7px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 7px;
            background: #101010;
            color: #fff;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #ff6a00;
            border-color: #ff6a00;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 7px;
            background: #ff6a00;
            color: white;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #e65f00;
        }

        .mensaje {
            margin: 15px 0;
            padding: 12px;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .error {
            background: #471c1c;
            color: #ffbaba;
        }

        .exito {
            background: #183d24;
            color: #b5f5c5;
        }

        .volver {
            display: block;
            margin-top: 20px;
            color: #ff6a00;
            text-align: center;
            text-decoration: none;
        }

        .nota {
            font-size: 13px;
            color: #aaa;
        }
    </style>
</head>

<body>
<div class="contenedor">

    <h1>Parche del <span>Tattoo</span></h1>
    <p>Cambiar contraseña del administrador</p>

    <?php if ($mensaje !== ""): ?>
        <div class="mensaje <?= $tipoMensaje === "exito" ? "exito" : "error" ?>">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="cambiar_clave_admin.php">

        <label for="correo">Correo del administrador</label>
        <input
            type="email"
            id="correo"
            name="correo"
            value="<?= htmlspecialchars($_POST["correo"] ?? "adminparche@example.com", ENT_QUOTES, "UTF-8") ?>"
            required>

        <label for="clave_nueva">Nueva contraseña</label>
        <input
            type="password"
            id="clave_nueva"
            name="clave_nueva"
            minlength="10"
            autocomplete="new-password"
            required>

        <label for="confirmar_clave">Confirmar contraseña</label>
        <input
            type="password"
            id="confirmar_clave"
            name="confirmar_clave"
            minlength="10"
            autocomplete="new-password"
            required>

        <p class="nota">
            Usa al menos 10 caracteres y no compartas tu contraseña.
        </p>

        <button type="submit">Actualizar contraseña</button>
    </form>

    <a class="volver" href="login.php">Volver al inicio de sesión</a>

</div>
</body>
</html>
