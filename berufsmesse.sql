-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Erstellungszeit: 03. Nov 2025 um 16:23
-- Server-Version: 10.4.32-MariaDB
-- PHP-Version: 8.2.12

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
CREATE DATABASE IF NOT EXISTS `berufsmesse` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `berufsmesse`;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `exhibitors`
--

CREATE TABLE IF NOT EXISTS `exhibitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
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
  PRIMARY KEY (`id`),
  KEY `fk_exhibitor_room` (`room_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `exhibitors`
--

INSERT INTO `exhibitors` (`id`, `name`, `description`, `short_description`, `category`, `logo`, `contact_person`, `email`, `phone`, `website`, `total_slots`, `room_id`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Daimler AG', 'Die Daimler AG bietet vielfältige Ausbildungsmöglichkeiten in technischen und kaufmännischen Berufen sowie duale Studiengänge an. Lernen Sie unsere spannenden Karrieremöglichkeiten kennen.', 'Automobilindustrie - Ausbildung und duale Studiengänge', NULL, NULL, NULL, 'karriere@daimler.com', NULL, 'www.daimler.com/karriere', 30, 1, 1, '2025-10-18 12:00:22', '2025-10-22 20:45:52'),
(2, 'Bosch GmbH', 'Bei Bosch erwarten dich innovative Projekte und eine erstklassige Ausbildung. Wir bieten über 50 verschiedene Ausbildungsberufe und duale Studiengänge in den Bereichen Technik, IT und Wirtschaft.', 'Technik und Innovation - Deine Zukunft bei Bosch', NULL, NULL, NULL, 'ausbildung@bosch.com', NULL, 'www.bosch.de/karriere', 25, 2, 1, '2025-10-18 12:00:22', '2025-10-22 20:45:52'),
(3, 'Sparkasse', 'Die Sparkasse ist einer der größten Ausbildungsbetriebe in Deutschland. Wir bieten dir eine fundierte Ausbildung im Finanzsektor mit exzellenten Übernahmechancen.', 'Banking und Finanzen - Starte deine Karriere', NULL, NULL, NULL, 'karriere@sparkasse.de', NULL, 'www.sparkasse.de', 20, 5, 1, '2025-10-18 12:00:22', '2025-10-22 20:45:52'),
(4, 'Siemens AG', 'Siemens steht für Innovation und Zukunftstechnologien. Entdecke deine Möglichkeiten in den Bereichen Elektrotechnik, Mechatronik, IT und viele mehr.', 'Elektrotechnik und Digitalisierung', NULL, NULL, NULL, 'jobs@siemens.com', NULL, 'www.siemens.com/karriere', 28, 3, 1, '2025-10-18 12:00:22', '2025-10-22 20:45:52'),
(5, 'Deutsche Bahn', 'Bei der Deutschen Bahn kannst du in über 50 Berufen durchstarten. Von Lokführer bis IT-Spezialist - werde Teil unseres Teams!', 'Mobilität der Zukunft mitgestalten', NULL, NULL, NULL, 'ausbildung@bahn.de', NULL, 'www.deutschebahn.com/karriere', 22, 7, 1, '2025-10-18 12:00:22', '2025-10-22 20:47:07'),
(6, 'SAP SE', 'SAP ist Weltmarktführer für Unternehmenssoftware. Starte bei uns in die IT-Welt mit Ausbildungen und dualen Studiengängen in Software-Entwicklung und mehr.', 'IT und Software - Die digitale Zukunft gestalten', NULL, NULL, NULL, 'karriere@sap.com', NULL, 'www.sap.com/karriere', 18, 6, 1, '2025-10-18 12:00:22', '2025-10-22 20:45:52');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `exhibitor_documents`
--

CREATE TABLE IF NOT EXISTS `exhibitor_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exhibitor_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `exhibitor_id` (`exhibitor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `registrations`
--

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `timeslot_id` int(11) NOT NULL,
  `registration_type` enum('manual','automatic') DEFAULT 'manual',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_timeslot` (`user_id`,`timeslot_id`),
  KEY `exhibitor_id` (`exhibitor_id`),
  KEY `timeslot_id` (`timeslot_id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(50) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `building` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 30,
  `floor` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Tabellenstruktur für Tabelle `settings`
--

CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_number` int(11) NOT NULL,
  `slot_name` varchar(100) NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `role` enum('student','admin') DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `firstname`, `lastname`, `class`, `role`, `created_at`) VALUES
(1, 'admin', 'test\r\n', 'Admin', 'User', NULL, 'admin', '2025-10-18 12:00:22'),
(2, 'max.mueller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Max', 'Müller', NULL, 'student', '2025-10-18 12:00:22'),
(3, 'anna.schmidt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anna', 'Schmidt', NULL, 'student', '2025-10-18 12:00:22'),
(4, 'tom.weber', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom', 'Weber', NULL, 'student', '2025-10-18 12:00:22'),
(5, 'admin1', '$2y$10$n7FZIDZAcMouE63CEwipQeXKwvaWD0Zdlb.X1rV/2R9YSbfZQJS.i', 'Admin', 'Admin', NULL, 'admin', '2025-10-18 12:03:59'),
(6, 'lennart.kassal', '$2y$10$gbQyrtio7DxbQUdd.Sy7Oex5Gy0SBDlWTO6ksvbJZ825FUpFW6wiy', 'Lennart', 'Kassal', NULL, 'student', '2025-10-18 12:09:49'),
(19, 'moritz.lingens', '$2y$10$dPtWNVu6NtiTsNuRCEFH1eR03FSikwdb0gNoCvJLFVDOU4kkkioEG', 'Moritz', 'Lingens', 'Q1', 'student', '2025-10-22 21:23:49');

--
-- Constraints der exportierten Tabellen
--

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
-- Constraints der Tabelle `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_3` FOREIGN KEY (`timeslot_id`) REFERENCES `timeslots` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
