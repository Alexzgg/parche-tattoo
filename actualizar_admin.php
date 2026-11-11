<?php

require_once("conexion.php");

$correo = "adminparche@example.com";
$password = "ParcheAdmin2026!";

// Generar el hash seguro
$hash = password_hash($password, PASSWORD_DEFAULT);

// Actualizar únicamente la contraseña del administrador
$sql = "UPDATE usuarios
        SET password = ?
        WHERE id_usuario = 3
        AND email = ?
        AND tipo_usuario = 'Administrador'";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $hash, $correo);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    echo "Contraseña del administrador actualizada correctamente.";
} else {
    echo "No se actualizó la contraseña. Verifica los datos del administrador.";
}

$stmt->close();
$conexion->close();

?>
