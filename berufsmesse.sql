-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Erstellungszeit: 18. Feb 2026 um 18:48
-- Server-Version: 10.9.8-MariaDB-1:10.9.8+maria~ubu2204
-- PHP-Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `berufsmesse`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `attendance`
--

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `qr_token` varchar(12) DEFAULT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anwesenheit von Schülern bei Ausstellern (QR-Check-In)';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `audit_logs`
--

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit Logs für alle Nutzeraktionen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `exhibitors`
--

CREATE TABLE IF NOT EXISTS `exhibitors` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `category` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `total_slots` int(11) DEFAULT 25,
  `room_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `visible_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Definiert welche Felder für Schüler sichtbar sind' CHECK (json_valid(`visible_fields`)),
  `jobs` text DEFAULT NULL COMMENT 'Typische Berufe/Tätigkeiten im Unternehmen',
  `features` text DEFAULT NULL COMMENT 'Besonderheiten des Unternehmens',
  `offer_types` text DEFAULT NULL COMMENT 'JSON: {selected: [...], custom: "..."}',
  `equipment` varchar(500) DEFAULT NULL COMMENT 'Benötigte Ausstattung (z.B. Beamer, WLAN)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `exhibitors`
--

INSERT INTO `exhibitors` (`id`, `name`, `description`, `short_description`, `category`, `logo`, `contact_person`, `email`, `phone`, `website`, `total_slots`, `room_id`, `active`, `created_at`, `updated_at`, `visible_fields`, `jobs`, `features`, `offer_types`) VALUES
(1, 'Daimler AG', 'Die Daimler AG bietet vielfältige Ausbildungsmöglichkeiten in technischen und kaufmännischen Berufen sowie duale Studiengänge an. Lernen Sie unsere spannenden Karrieremöglichkeiten kennen.', 'Automobilindustrie - Ausbildung und duale Studiengänge', NULL, NULL, NULL, 'karriere@daimler.com', NULL, 'www.daimler.com/karriere', 30, 1, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL),
(2, 'Bosch GmbH', 'Bei Bosch erwarten dich innovative Projekte und eine erstklassige Ausbildung. Wir bieten über 50 verschiedene Ausbildungsberufe und duale Studiengänge in den Bereichen Technik, IT und Wirtschaft.', 'Technik und Innovation - Deine Zukunft bei Bosch', NULL, NULL, NULL, 'ausbildung@bosch.com', NULL, 'www.bosch.de/karriere', 25, 2, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL),
(3, 'Sparkasse', 'Die Sparkasse ist einer der größten Ausbildungsbetriebe in Deutschland. Wir bieten dir eine fundierte Ausbildung im Finanzsektor mit exzellenten Übernahmechancen.', 'Banking und Finanzen - Starte deine Karriere', NULL, NULL, NULL, 'karriere@sparkasse.de', NULL, 'www.sparkasse.de', 20, 5, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL),
(4, 'Siemens AG', 'Siemens steht für Innovation und Zukunftstechnologien. Entdecke deine Möglichkeiten in den Bereichen Elektrotechnik, Mechatronik, IT und viele mehr.', 'Elektrotechnik und Digitalisierung', NULL, NULL, NULL, 'jobs@siemens.com', NULL, 'www.siemens.com/karriere', 28, 3, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL),
(5, 'Deutsche Bahn', 'Bei der Deutschen Bahn kannst du in über 50 Berufen durchstarten. Von Lokführer bis IT-Spezialist - werde Teil unseres Teams!', 'Mobilität der Zukunft mitgestalten', NULL, NULL, NULL, 'ausbildung@bahn.de', NULL, 'www.deutschebahn.com/karriere', 22, 7, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL),
(6, 'SAP SE', 'SAP ist Weltmarktführer für Unternehmenssoftware. Starte bei uns in die IT-Welt mit Ausbildungen und dualen Studiengängen in Software-Entwicklung und mehr.', 'IT und Software - Die digitale Zukunft gestalten', NULL, NULL, NULL, 'karriere@sap.com', NULL, 'www.sap.com/karriere', 18, 6, 1, '2025-10-18 12:00:22', '2026-02-18 15:43:52', '[\"name\", \"short_description\", \"description\", \"category\", \"website\"]', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `exhibitor_documents`
--

CREATE TABLE IF NOT EXISTS `exhibitor_documents` (
  `id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `industries`
--

CREATE TABLE IF NOT EXISTS `industries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Verwaltbare Branchen/Kategorien fuer Aussteller';

--
-- Daten für Tabelle `industries`
--

INSERT INTO `industries` (`id`, `name`, `sort_order`, `created_at`) VALUES
(1, 'Automobilindustrie', 1, '2026-02-18 15:43:53'),
(2, 'Handwerk', 2, '2026-02-18 15:43:53'),
(3, 'Gesundheitswesen', 3, '2026-02-18 15:43:53'),
(4, 'IT & Software', 4, '2026-02-18 15:43:53'),
(5, 'Dienstleistung', 5, '2026-02-18 15:43:53'),
(6, 'Öffentlicher Dienst', 6, '2026-02-18 15:43:53'),
(7, 'Bildung', 7, '2026-02-18 15:43:53'),
(8, 'Gastronomie & Hotellerie', 8, '2026-02-18 15:43:53'),
(9, 'Handel & Verkauf', 9, '2026-02-18 15:43:53'),
(10, 'Sonstiges', 99, '2026-02-18 15:43:53');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `qr_tokens`
--

CREATE TABLE IF NOT EXISTS `qr_tokens` (
  `id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `token` varchar(12) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QR-Code Tokens für Aussteller-Check-In';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `permission_groups`
--

CREATE TABLE IF NOT EXISTS `permission_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungsgruppen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `permission_group_items`
--

CREATE TABLE IF NOT EXISTS `permission_group_items` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berechtigungen in Gruppen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `registrations`
--

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) DEFAULT NULL,
  `registration_type` enum('manual','automatic') DEFAULT 'manual',
  `priority` int(11) DEFAULT NULL COMMENT 'Priorität der Anmeldung (1 = höchste Priorität)',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `registrations`
--

INSERT INTO `registrations` (`id`, `user_id`, `exhibitor_id`, `timeslot_id`, `registration_type`, `registered_at`) VALUES
(17, 19, 3, 1, 'manual', '2025-10-22 21:25:00'),
(19, 19, 5, 3, 'manual', '2025-10-22 21:25:08'),
(20, 2, 2, 1, 'automatic', '2025-10-23 17:11:10'),
(21, 2, 6, 3, 'automatic', '2025-10-23 17:11:10'),
(22, 2, 5, 5, 'automatic', '2025-10-23 17:11:10'),
(23, 3, 4, 1, 'automatic', '2025-10-23 17:11:10'),
(24, 3, 3, 3, 'automatic', '2025-10-23 17:11:10'),
(25, 3, 2, 5, 'automatic', '2025-10-23 17:11:10'),
(26, 4, 5, 1, 'automatic', '2025-10-23 17:11:10'),
(27, 4, 2, 3, 'automatic', '2025-10-23 17:11:10'),
(28, 4, 1, 5, 'automatic', '2025-10-23 17:11:10'),
(29, 6, 1, 1, 'automatic', '2025-10-23 17:11:10'),
(30, 6, 4, 3, 'automatic', '2025-10-23 17:11:10'),
(31, 6, 3, 5, 'automatic', '2025-10-23 17:11:10'),
(32, 19, 4, 5, 'automatic', '2025-10-23 17:11:10');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `rooms`
--

CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 30,
  `equipment` varchar(500) DEFAULT NULL COMMENT 'Raumausstattung (z.B. Beamer, Smartboard)',
  `floor` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_name`, `building`, `capacity`, `floor`, `created_at`) VALUES
(1, 'A101', 'Workshopraum 1', 'Hauptgebäude', 30, 1, '2025-10-22 20:41:55'),
(2, 'A102', 'Workshopraum 2', 'Hauptgebäude', 25, 1, '2025-10-22 20:41:55'),
(3, 'A103', 'Workshopraum 3', 'Hauptgebäude', 35, 1, '2025-10-22 20:41:55'),
(4, 'A104', 'Workshopraum 4', 'Hauptgebäude', 30, 1, '2025-10-22 20:41:55'),
(5, 'B201', 'Aula Nord', 'Nebengebäude', 40, 2, '2025-10-22 20:41:55'),
(6, 'B202', 'Aula Süd', 'Nebengebäude', 40, 2, '2025-10-22 20:41:55'),
(7, 'C301', 'Seminarraum 1', 'Altbau', 20, 3, '2025-10-22 20:41:55'),
(8, 'C302', 'Seminarraum 2', 'Altbau', 20, 3, '2025-10-22 20:41:55');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `room_slot_capacities`
--

CREATE TABLE IF NOT EXISTS `room_slot_capacities` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `capacity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Slot-spezifische Raumkapazitäten';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'registration_start', '2025-10-17T00:00', '2025-10-18 12:05:22'),
(2, 'registration_end', '2025-11-01T23:59', '2025-10-18 12:05:22'),
(3, 'event_date', '2025-11-15', '2025-10-18 12:00:22'),
(4, 'max_registrations_per_student', '3', '2025-10-18 12:00:22');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `timeslots`
--

CREATE TABLE IF NOT EXISTS `timeslots` (
  `id` int(11) NOT NULL,
  `slot_number` int(11) NOT NULL,
  `slot_name` varchar(100) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `timeslots`
--

INSERT INTO `timeslots` (`id`, `slot_number`, `slot_name`, `start_time`, `end_time`) VALUES
(1, 1, 'Slot 1 (Feste Zuteilung)', '09:00:00', '09:30:00'),
(2, 2, 'Slot 2 (Freie Wahl)', '09:40:00', '10:10:00'),
(3, 3, 'Slot 3 (Feste Zuteilung)', '10:40:00', '11:10:00'),
(4, 4, 'Slot 4 (Freie Wahl)', '11:20:00', '11:50:00'),
(5, 5, 'Slot 5 (Feste Zuteilung)', '12:20:00', '12:50:00');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `must_change_password` tinyint(1) DEFAULT 0 COMMENT 'Erzwingt Passwortänderung beim nächsten Login'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `firstname`, `lastname`, `class`, `role`, `created_at`, `must_change_password`) VALUES
(1, 'admin', NULL, 'test\r\n', 'Admin', 'User', NULL, 'admin', '2025-10-18 12:00:22', 0),
(2, 'max.mueller', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Max', 'Müller', NULL, 'student', '2025-10-18 12:00:22', 0),
(3, 'anna.schmidt', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anna', 'Schmidt', NULL, 'student', '2025-10-18 12:00:22', 0),
(4, 'tom.weber', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom', 'Weber', NULL, 'student', '2025-10-18 12:00:22', 0),
(5, 'admin1', NULL, '$2y$10$n7FZIDZAcMouE63CEwipQeXKwvaWD0Zdlb.X1rV/2R9YSbfZQJS.i', 'Admin', 'Admin', NULL, 'admin', '2025-10-18 12:03:59', 0),
(6, 'lennart.kassal', NULL, '$2y$10$gbQyrtio7DxbQUdd.Sy7Oex5Gy0SBDlWTO6ksvbJZ825FUpFW6wiy', 'Lennart', 'Kassal', NULL, 'student', '2025-10-18 12:09:49', 0),
(19, 'moritz.lingens', NULL, '$2y$10$dPtWNVu6NtiTsNuRCEFH1eR03FSikwdb0gNoCvJLFVDOU4kkkioEG', 'Moritz', 'Lingens', 'Q1', 'student', '2025-10-22 21:23:49', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_permissions`
--

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` varchar(50) NOT NULL,
  `granted_by` int(11) DEFAULT NULL COMMENT 'Admin der die Berechtigung erteilt hat',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Granulare Benutzerberechtigungen';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_permission_groups`
--

CREATE TABLE IF NOT EXISTS `user_permission_groups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung von Berechtigungsgruppen zu Benutzern';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `exhibitor_orga_team`
--

CREATE TABLE IF NOT EXISTS `exhibitor_orga_team` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orga-Team Zuordnung zu einzelnen Ausstellern';

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_exhibitor_timeslot` (`user_id`,`exhibitor_id`,`timeslot_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_exhibitor_id` (`exhibitor_id`),
  ADD KEY `idx_timeslot_id` (`timeslot_id`),
  ADD KEY `idx_checked_in_at` (`checked_in_at`);

--
-- Indizes für die Tabelle `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indizes für die Tabelle `exhibitors`
--
ALTER TABLE `exhibitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_exhibitor_room` (`room_id`);

--
-- Indizes für die Tabelle `exhibitor_documents`
--
ALTER TABLE `exhibitor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exhibitor_id` (`exhibitor_id`);

--
-- Indizes für die Tabelle `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Indizes für die Tabelle `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_exhibitor_timeslot` (`exhibitor_id`,`timeslot_id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_exhibitor_id` (`exhibitor_id`),
  ADD KEY `idx_timeslot_id` (`timeslot_id`);

--
-- Indizes für die Tabelle `permission_groups`
--
ALTER TABLE `permission_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_group_name` (`name`),
  ADD KEY `created_by` (`created_by`);

--
-- Indizes für die Tabelle `permission_group_items`
--
ALTER TABLE `permission_group_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_group_permission` (`group_id`,`permission`);

--
-- Indizes für die Tabelle `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_exhibitor` (`user_id`,`exhibitor_id`),
  ADD KEY `exhibitor_id` (`exhibitor_id`),
  ADD KEY `timeslot_id` (`timeslot_id`),
  ADD KEY `idx_timeslot_only` (`timeslot_id`),
  ADD KEY `idx_user_only` (`user_id`),
  ADD KEY `idx_user_timeslot` (`user_id`,`timeslot_id`);

--
-- Indizes für die Tabelle `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `room_slot_capacities`
--
ALTER TABLE `room_slot_capacities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_slot` (`room_id`,`timeslot_id`),
  ADD KEY `idx_room_id` (`room_id`),
  ADD KEY `idx_timeslot_id` (`timeslot_id`);

--
-- Indizes für die Tabelle `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indizes für die Tabelle `timeslots`
--
ALTER TABLE `timeslots`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indizes für die Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_permission` (`permission`);

--
-- Indizes für die Tabelle `user_permission_groups`
--
ALTER TABLE `user_permission_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_group` (`user_id`,`group_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_group_id` (`group_id`);

--
-- Indizes für die Tabelle `exhibitor_orga_team`
--
ALTER TABLE `exhibitor_orga_team`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_exhibitor` (`user_id`,`exhibitor_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_exhibitor_id` (`exhibitor_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `exhibitors`
--
ALTER TABLE `exhibitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `exhibitor_documents`
--
ALTER TABLE `exhibitor_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT für Tabelle `permission_groups`
--
ALTER TABLE `permission_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `qr_tokens`
--
ALTER TABLE `qr_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `permission_group_items`
--
ALTER TABLE `permission_group_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT für Tabelle `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT für Tabelle `room_slot_capacities`
--
ALTER TABLE `room_slot_capacities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT für Tabelle `timeslots`
--
ALTER TABLE `timeslots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT für Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_permission_groups`
--
ALTER TABLE `user_permission_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `exhibitor_orga_team`
--
ALTER TABLE `exhibitor_orga_team`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `exhibitors`
--
ALTER TABLE `exhibitors`
  ADD CONSTRAINT `fk_exhibitor_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `exhibitor_documents`
--
ALTER TABLE `exhibitor_documents`
  ADD CONSTRAINT `exhibitor_documents_ibfk_1` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `permission_groups`
--
ALTER TABLE `permission_groups`
  ADD CONSTRAINT `permission_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `permission_group_items`
--
ALTER TABLE `permission_group_items`
  ADD CONSTRAINT `permission_group_items_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `qr_tokens`
--
ALTER TABLE `qr_tokens`
  ADD CONSTRAINT `qr_tokens_ibfk_1` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `qr_tokens_ibfk_2` FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_3` FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `room_slot_capacities`
--
ALTER TABLE `room_slot_capacities`
  ADD CONSTRAINT `room_slot_capacities_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_slot_capacities_ibfk_2` FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `user_permission_groups`
--
ALTER TABLE `user_permission_groups`
  ADD CONSTRAINT `user_permission_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permission_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `exhibitor_orga_team`
--
ALTER TABLE `exhibitor_orga_team`
  ADD CONSTRAINT `exhibitor_orga_team_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exhibitor_orga_team_ibfk_2` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
