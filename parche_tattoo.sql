-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-11-2026 a las 15:12:22
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `parche_tattoo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentarios`
--

CREATE TABLE `comentarios` (
  `id_comentario` int(11) NOT NULL,
  `id_diseno` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `fecha_comentario` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos`
--

CREATE TABLE `contactos` (
  `id_contacto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `asunto` varchar(150) DEFAULT NULL,
  `mensaje` text NOT NULL,
  `fecha_contacto` datetime DEFAULT current_timestamp(),
  `atendido` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `contactos`
--

INSERT INTO `contactos` (`id_contacto`, `nombre`, `email`, `asunto`, `mensaje`, `fecha_contacto`, `atendido`) VALUES
(1, 'Administrador Parche', 'adminparche@example.com', 'ff', 'ddgh', '2026-11-03 21:38:03', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disenos`
--

CREATE TABLE `disenos` (
  `id_diseno` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `imagen` varchar(255) NOT NULL,
  `fecha_publicacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `disenos`
--

INSERT INTO `disenos` (`id_diseno`, `id_usuario`, `titulo`, `descripcion`, `categoria`, `imagen`, `fecha_publicacion`) VALUES
(1, 6, 'df', '565456456', 'Minimalista', 'img/disenos/diseno_6_8eabf773474d109c.jpg', '2026-11-03 21:09:00'),
(2, 7, 'hi', 'rrrrr', 'Otro', 'img/disenos/diseno_7_80c1ac8b93930928.jpg', '2026-11-04 09:10:50'),
(3, 7, 'jdjdsjsnn', 'ggyhnbvxfdyhbbvgh', 'Blackwork', 'img/disenos/diseno_7_1c51e5c0da1fd769.jpg', '2026-11-04 09:12:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `likes`
--

CREATE TABLE `likes` (
  `id_like` int(11) NOT NULL,
  `id_diseno` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_like` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `likes_foro`
--

CREATE TABLE `likes_foro` (
  `id_like` int(11) NOT NULL,
  `id_publicacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_like` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `likes_foro`
--

INSERT INTO `likes_foro` (`id_like`, `id_publicacion`, `id_usuario`, `fecha_like`) VALUES
(2, 2, 5, '2026-11-03 20:31:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` int(11) NOT NULL,
  `id_remitente` int(11) NOT NULL,
  `id_destinatario` int(11) NOT NULL,
  `asunto` varchar(150) DEFAULT NULL,
  `contenido` text NOT NULL,
  `leido` tinyint(1) DEFAULT 0,
  `fecha_envio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mensajes`
--

INSERT INTO `mensajes` (`id_mensaje`, `id_remitente`, `id_destinatario`, `asunto`, `contenido`, `leido`, `fecha_envio`) VALUES
(1, 7, 2, 'tralalero', 'tralala', 0, '2026-11-11 08:51:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `moderacion`
--

CREATE TABLE `moderacion` (
  `id_moderacion` int(11) NOT NULL,
  `id_administrador` int(11) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_accion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `perfiles`
--

CREATE TABLE `perfiles` (
  `id_perfil` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `biografia` varchar(500) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ciudad` varchar(80) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `perfiles`
--

INSERT INTO `perfiles` (`id_perfil`, `id_usuario`, `biografia`, `telefono`, `ciudad`, `instagram`, `especialidad`, `foto_perfil`) VALUES
(2, 5, NULL, NULL, NULL, NULL, NULL, 'img/perfiles/perfil_5_1793757843.png'),
(3, 6, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 3, '', '', '', '', '', 'img/perfiles/perfil_3_1793797382.jpg'),
(5, 7, NULL, NULL, NULL, NULL, NULL, 'img/perfiles/perfil_7_1793801899.jpg');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `piercings`
--

CREATE TABLE `piercings` (
  `id_piercing` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `imagen` varchar(255) NOT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicaciones_foro`
--

CREATE TABLE `publicaciones_foro` (
  `id_publicacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `contenido` text NOT NULL,
  `fecha_publicacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `publicaciones_foro`
--

INSERT INTO `publicaciones_foro` (`id_publicacion`, `id_usuario`, `titulo`, `contenido`, `fecha_publicacion`) VALUES
(2, 5, 'Luz', 'dddddddddddddddddddddddddd', '2026-11-03 20:17:16'),
(3, 7, 'hi', 'hola', '2026-11-04 09:17:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes`
--

CREATE TABLE `reportes` (
  `id_reporte` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo_contenido` enum('Usuario','Diseno','Comentario','Foro') NOT NULL,
  `id_contenido` int(11) NOT NULL,
  `motivo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('Pendiente','En revision','Resuelto','Rechazado') NOT NULL DEFAULT 'Pendiente',
  `fecha_reporte` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_foro`
--

CREATE TABLE `respuestas_foro` (
  `id_respuesta` int(11) NOT NULL,
  `id_publicacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `contenido` text NOT NULL,
  `fecha_respuesta` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `respuestas_foro`
--

INSERT INTO `respuestas_foro` (`id_respuesta`, `id_publicacion`, `id_usuario`, `contenido`, `fecha_respuesta`) VALUES
(1, 2, 5, 'sssss', '2026-11-03 20:31:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre_usuario` varchar(50) NOT NULL,
  `nombre_completo` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `tipo_usuario` enum('Artista','Cliente','Administrador') NOT NULL DEFAULT 'Cliente',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `estado` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `nombre_completo`, `email`, `password`, `telefono`, `tipo_usuario`, `fecha_registro`, `estado`) VALUES
(2, 'AnyDarko', NULL, 'milocongalletasuwu@gmail.com', '$2y$10$jTYeIpHdwUAMsScXA3f7TeHyAOSlP9IDWbBfdFjvpfHYeNKOlywyq', '3186447436', 'Artista', '2026-10-14 08:45:42', 1),
(3, 'AdminParche', 'Administrador Parche', 'adminparche@example.com', '$2y$10$FYfo8uxtiJ3dNfvO3fAwzeixLfoDvFLxR1XuS7Y03RuqoCvPxlJuC', NULL, 'Administrador', '2026-10-14 10:26:48', 1),
(5, 'cindy', NULL, 'cindy@example.com', '$2y$10$CtAy0GgnvqIvyTraODnLQetPme4caR544zLGA9do5JcZAE3mRWcHC', '3001234567', 'Cliente', '2026-11-03 20:11:47', 1),
(6, 'Gtobon', NULL, 'angelica3951@hotmail.com', '$2y$10$pxlIz7V6S12nERwI0KerOOez52ZUDljZTiEisTlwz9lDq/J/RoWQS', '3001234567', 'Artista', '2026-11-03 21:05:00', 1),
(7, 'alex', NULL, 'alex123@gmail.com', '$2y$10$pnKZfd/rZ2dAsLHABhVSmemZj1fS4ESgYaTBbGrA8EEV4UOZryIu2', '', 'Artista', '2026-11-04 09:09:46', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `fk_comentario_diseno` (`id_diseno`),
  ADD KEY `fk_comentario_usuario` (`id_usuario`);

--
-- Indices de la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD PRIMARY KEY (`id_contacto`);

--
-- Indices de la tabla `disenos`
--
ALTER TABLE `disenos`
  ADD PRIMARY KEY (`id_diseno`),
  ADD KEY `fk_diseno_usuario` (`id_usuario`);

--
-- Indices de la tabla `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id_like`),
  ADD UNIQUE KEY `id_diseno` (`id_diseno`,`id_usuario`),
  ADD KEY `fk_like_usuario` (`id_usuario`);

--
-- Indices de la tabla `likes_foro`
--
ALTER TABLE `likes_foro`
  ADD PRIMARY KEY (`id_like`),
  ADD UNIQUE KEY `unico_like` (`id_publicacion`,`id_usuario`),
  ADD KEY `fk_like_foro_usuario` (`id_usuario`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `fk_mensaje_remitente` (`id_remitente`),
  ADD KEY `fk_mensaje_destinatario` (`id_destinatario`);

--
-- Indices de la tabla `moderacion`
--
ALTER TABLE `moderacion`
  ADD PRIMARY KEY (`id_moderacion`),
  ADD KEY `idx_moderacion_admin` (`id_administrador`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notificaciones_usuario` (`id_usuario`);

--
-- Indices de la tabla `perfiles`
--
ALTER TABLE `perfiles`
  ADD PRIMARY KEY (`id_perfil`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `piercings`
--
ALTER TABLE `piercings`
  ADD PRIMARY KEY (`id_piercing`),
  ADD KEY `fk_piercings_usuario` (`id_usuario`);

--
-- Indices de la tabla `publicaciones_foro`
--
ALTER TABLE `publicaciones_foro`
  ADD PRIMARY KEY (`id_publicacion`),
  ADD KEY `fk_publicacion_usuario` (`id_usuario`);

--
-- Indices de la tabla `reportes`
--
ALTER TABLE `reportes`
  ADD PRIMARY KEY (`id_reporte`),
  ADD KEY `idx_reportes_estado` (`estado`),
  ADD KEY `idx_reportes_usuario` (`id_usuario`);

--
-- Indices de la tabla `respuestas_foro`
--
ALTER TABLE `respuestas_foro`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD KEY `fk_respuesta_publicacion` (`id_publicacion`),
  ADD KEY `fk_respuesta_usuario` (`id_usuario`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `comentarios`
--
ALTER TABLE `comentarios`
  MODIFY `id_comentario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `contactos`
--
ALTER TABLE `contactos`
  MODIFY `id_contacto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `disenos`
--
ALTER TABLE `disenos`
  MODIFY `id_diseno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `likes`
--
ALTER TABLE `likes`
  MODIFY `id_like` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `likes_foro`
--
ALTER TABLE `likes_foro`
  MODIFY `id_like` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `moderacion`
--
ALTER TABLE `moderacion`
  MODIFY `id_moderacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `perfiles`
--
ALTER TABLE `perfiles`
  MODIFY `id_perfil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `publicaciones_foro`
--
ALTER TABLE `publicaciones_foro`
  MODIFY `id_publicacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `respuestas_foro`
--
ALTER TABLE `respuestas_foro`
  MODIFY `id_respuesta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `comentarios`
--
ALTER TABLE `comentarios`
  ADD CONSTRAINT `fk_comentario_diseno` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`id_diseno`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_comentario_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `disenos`
--
ALTER TABLE `disenos`
  ADD CONSTRAINT `fk_diseno_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `fk_like_diseno` FOREIGN KEY (`id_diseno`) REFERENCES `disenos` (`id_diseno`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_like_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `fk_mensaje_destinatario` FOREIGN KEY (`id_destinatario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mensaje_remitente` FOREIGN KEY (`id_remitente`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `perfiles`
--
ALTER TABLE `perfiles`
  ADD CONSTRAINT `fk_perfil_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `publicaciones_foro`
--
ALTER TABLE `publicaciones_foro`
  ADD CONSTRAINT `fk_publicacion_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `respuestas_foro`
--
ALTER TABLE `respuestas_foro`
  ADD CONSTRAINT `fk_respuesta_publicacion` FOREIGN KEY (`id_publicacion`) REFERENCES `publicaciones_foro` (`id_publicacion`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_respuesta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
