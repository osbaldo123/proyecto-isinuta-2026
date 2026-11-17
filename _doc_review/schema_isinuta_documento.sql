-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: db_agua_potable_isinuta
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `usuario` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `afiliados`
--

DROP TABLE IF EXISTS `afiliados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `afiliados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `afiliados_ci_unique` (`ci`)
) ENGINE=InnoDB AUTO_INCREMENT=606 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asistencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `trabajo_id` bigint unsigned NOT NULL,
  `afiliado_id` bigint unsigned NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asistencias_trabajo_id_afiliado_id_unique` (`trabajo_id`,`afiliado_id`),
  KEY `asistencias_afiliado_id_foreign` (`afiliado_id`),
  CONSTRAINT `asistencias_afiliado_id_foreign` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asistencias_trabajo_id_foreign` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `tarifa_agua` decimal(10,2) NOT NULL DEFAULT '17.00',
  `tarifa_alcantarillado` decimal(10,2) NOT NULL DEFAULT '30.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categorias_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cesiones_lote`
--

DROP TABLE IF EXISTS `cesiones_lote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cesiones_lote` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lote_afiliado_id` bigint unsigned NOT NULL,
  `afiliado_anterior_id` bigint unsigned DEFAULT NULL,
  `nombre_anterior` varchar(210) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci_anterior` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiliado_nuevo_id` bigint unsigned DEFAULT NULL,
  `nombre_nuevo` varchar(210) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci_nuevo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cesion` date NOT NULL,
  `motivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento_respaldo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registrado_por` bigint unsigned DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cesiones_lote_afiliado_anterior_id_foreign` (`afiliado_anterior_id`),
  KEY `cesiones_lote_afiliado_nuevo_id_foreign` (`afiliado_nuevo_id`),
  KEY `cesiones_lote_registrado_por_foreign` (`registrado_por`),
  KEY `cesiones_lote_lote_afiliado_id_fecha_cesion_index` (`lote_afiliado_id`,`fecha_cesion`),
  CONSTRAINT `cesiones_lote_afiliado_anterior_id_foreign` FOREIGN KEY (`afiliado_anterior_id`) REFERENCES `afiliados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cesiones_lote_afiliado_nuevo_id_foreign` FOREIGN KEY (`afiliado_nuevo_id`) REFERENCES `afiliados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cesiones_lote_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cesiones_lote_registrado_por_foreign` FOREIGN KEY (`registrado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `configuracion_clave_unique` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuotas`
--

DROP TABLE IF EXISTS `cuotas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuotas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lote_afiliado_id` bigint unsigned NOT NULL,
  `servicio_id` bigint unsigned NOT NULL,
  `periodo` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gestion` smallint unsigned NOT NULL,
  `monto_original` decimal(10,2) NOT NULL,
  `saldo` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cuotas_lote_servicio_periodo_unique` (`lote_afiliado_id`,`servicio_id`,`periodo`),
  KEY `cuotas_servicio_id_foreign` (`servicio_id`),
  KEY `cuotas_lote_afiliado_id_fecha_vencimiento_index` (`lote_afiliado_id`,`fecha_vencimiento`),
  CONSTRAINT `cuotas_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cuotas_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3552 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lote_afiliados`
--

DROP TABLE IF EXISTS `lote_afiliados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lote_afiliados` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `afiliado_id` bigint unsigned NOT NULL,
  `numero_lote` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `otb_id` bigint unsigned DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_afiliacion` date DEFAULT NULL,
  `forma_pago_afiliacion` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'contado',
  `monto_afiliacion` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_afiliacion_agua` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_afiliacion_alcantarillado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_afiliacion_agua` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_afiliacion_alcantarillado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_afiliacion` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `categoria_propiedad` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto_categorizado` decimal(12,2) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lote_afiliados_afiliado_lote_unique` (`afiliado_id`,`numero_lote`),
  UNIQUE KEY `lote_afiliados_numero_lote_unique` (`numero_lote`),
  KEY `lote_afiliados_otb_id_numero_lote_index` (`otb_id`,`numero_lote`),
  KEY `lote_afiliados_categoria_id_foreign` (`categoria_id`),
  CONSTRAINT `lote_afiliados_afiliado_id_foreign` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lote_afiliados_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lote_afiliados_otb_id_foreign` FOREIGN KEY (`otb_id`) REFERENCES `otbs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=577 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lote_servicio`
--

DROP TABLE IF EXISTS `lote_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lote_servicio` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lote_afiliado_id` bigint unsigned NOT NULL,
  `servicio_id` bigint unsigned NOT NULL,
  `tarifa_acordada` decimal(10,2) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lote_servicio_lote_servicio_unique` (`lote_afiliado_id`,`servicio_id`),
  KEY `lote_servicio_servicio_id_foreign` (`servicio_id`),
  CONSTRAINT `lote_servicio_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lote_servicio_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lote_tipo_afiliacion`
--

DROP TABLE IF EXISTS `lote_tipo_afiliacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lote_tipo_afiliacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lote_afiliado_id` bigint unsigned NOT NULL,
  `tipo_afiliacion_id` bigint unsigned NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lote_tipo_afiliacion_unique` (`lote_afiliado_id`,`tipo_afiliacion_id`),
  KEY `lote_tipo_afiliacion_tipo_afiliacion_id_foreign` (`tipo_afiliacion_id`),
  CONSTRAINT `lote_tipo_afiliacion_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lote_tipo_afiliacion_tipo_afiliacion_id_foreign` FOREIGN KEY (`tipo_afiliacion_id`) REFERENCES `tipos_afiliacion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `multas`
--

DROP TABLE IF EXISTS `multas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `multas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `afiliado_id` bigint unsigned NOT NULL,
  `lote_afiliado_id` bigint unsigned DEFAULT NULL,
  `fecha_generacion` date NOT NULL,
  `motivo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gestion` smallint unsigned DEFAULT NULL,
  `periodo` char(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cuotas_vencidas` smallint unsigned DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `saldo` decimal(10,2) DEFAULT NULL,
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `generada_por` bigint unsigned DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  `trabajo_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `multas_lote_motivo_periodo_unique` (`lote_afiliado_id`,`motivo`,`periodo`),
  KEY `multas_afiliado_id_foreign` (`afiliado_id`),
  KEY `multas_generada_por_foreign` (`generada_por`),
  KEY `multas_trabajo_id_foreign` (`trabajo_id`),
  CONSTRAINT `multas_afiliado_id_foreign` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `multas_generada_por_foreign` FOREIGN KEY (`generada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `multas_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE SET NULL,
  CONSTRAINT `multas_trabajo_id_foreign` FOREIGN KEY (`trabajo_id`) REFERENCES `trabajos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=550 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `otbs`
--

DROP TABLE IF EXISTS `otbs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otbs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'OTB',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `zonas_nombre_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pago_detalles`
--

DROP TABLE IF EXISTS `pago_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pago_detalles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pago_id` bigint unsigned NOT NULL,
  `concepto` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `servicio_id` bigint unsigned DEFAULT NULL,
  `multa_id` bigint unsigned DEFAULT NULL,
  `cuota_id` bigint unsigned DEFAULT NULL,
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pago_detalles_pago_id_foreign` (`pago_id`),
  KEY `pago_detalles_servicio_id_foreign` (`servicio_id`),
  KEY `pago_detalles_multa_id_foreign` (`multa_id`),
  KEY `pago_detalles_cuota_id_foreign` (`cuota_id`),
  CONSTRAINT `pago_detalles_cuota_id_foreign` FOREIGN KEY (`cuota_id`) REFERENCES `cuotas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pago_detalles_multa_id_foreign` FOREIGN KEY (`multa_id`) REFERENCES `multas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pago_detalles_pago_id_foreign` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pago_detalles_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero_recibo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_recibo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ingreso',
  `proposito` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposito_label` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiliado_id` bigint unsigned NOT NULL,
  `lote_afiliado_id` bigint unsigned DEFAULT NULL,
  `cobro_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_pago` date NOT NULL,
  `monto_total` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'efectivo',
  `estado` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'emitido',
  `cobrado_por` bigint unsigned DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `anulado_en` timestamp NULL DEFAULT NULL,
  `anulado_por` bigint unsigned DEFAULT NULL,
  `motivo_anulacion` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pagos_numero_recibo_unique` (`numero_recibo`),
  KEY `pagos_afiliado_id_foreign` (`afiliado_id`),
  KEY `pagos_cobrado_por_foreign` (`cobrado_por`),
  KEY `pagos_lote_afiliado_id_foreign` (`lote_afiliado_id`),
  KEY `pagos_anulado_por_foreign` (`anulado_por`),
  KEY `pagos_cobro_id_index` (`cobro_id`),
  CONSTRAINT `pagos_afiliado_id_foreign` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pagos_anulado_por_foreign` FOREIGN KEY (`anulado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pagos_cobrado_por_foreign` FOREIGN KEY (`cobrado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pagos_lote_afiliado_id_foreign` FOREIGN KEY (`lote_afiliado_id`) REFERENCES `lote_afiliados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6902 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permission_role`
--

DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_role_permission_id_role_id_unique` (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_nombre_unique` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `recibo_sequences`
--

DROP TABLE IF EXISTS `recibo_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recibo_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `recibo_sequences_year_unique` (`year`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_user`
--

DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_user_role_id_user_id_unique` (`role_id`,`user_id`),
  KEY `role_user_user_id_foreign` (`user_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `user_id` bigint unsigned DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_nombre_unique` (`nombre`),
  KEY `roles_user_id_foreign` (`user_id`),
  CONSTRAINT `roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servicio_categoria_tarifas`
--

DROP TABLE IF EXISTS `servicio_categoria_tarifas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicio_categoria_tarifas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `servicio_id` bigint unsigned NOT NULL,
  `categoria_id` bigint unsigned NOT NULL,
  `tarifa` decimal(10,2) NOT NULL DEFAULT '0.00',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `servicio_categoria_tarifas_servicio_id_categoria_id_unique` (`servicio_id`,`categoria_id`),
  KEY `servicio_categoria_tarifas_categoria_id_foreign` (`categoria_id`),
  CONSTRAINT `servicio_categoria_tarifas_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `servicio_categoria_tarifas_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agua',
  `tarifa_mensual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `costo_instalacion_contado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `costo_instalacion_plazos` decimal(10,2) NOT NULL DEFAULT '0.00',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `servicios_nombre_unique` (`nombre`),
  UNIQUE KEY `servicios_codigo_unique` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_afiliacion`
--

DROP TABLE IF EXISTS `tipos_afiliacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_afiliacion` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modalidad` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_plazo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `orden` smallint unsigned NOT NULL DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipos_afiliacion_codigo_unique` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trabajos`
--

DROP TABLE IF EXISTS `trabajos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `trabajos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  `otb_id` bigint unsigned NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_inicio` date NOT NULL,
  `monto_multa` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `trabajos_otb_id_fecha_inicio_index` (`otb_id`,`fecha_inicio`),
  CONSTRAINT `trabajos_otb_id_foreign` FOREIGN KEY (`otb_id`) REFERENCES `otbs` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ci` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono_codigo` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '+591',
  `telefono` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_verification_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verification_code_expires_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_system_account` tinyint(1) NOT NULL DEFAULT '0',
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_final` date DEFAULT NULL,
  `afiliado_id` bigint unsigned DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_afiliado_id_unique` (`afiliado_id`),
  UNIQUE KEY `users_telefono_unique` (`telefono_codigo`,`telefono`),
  UNIQUE KEY `users_ci_unique` (`ci`),
  CONSTRAINT `users_afiliado_id_foreign` FOREIGN KEY (`afiliado_id`) REFERENCES `afiliados` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-09  2:50:49
