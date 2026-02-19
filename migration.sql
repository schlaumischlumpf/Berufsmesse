-- Migration Script für Berufsmesse System
-- Erstellt: 2026-02-19
-- Fügt exhibitor_orga_team und user_permission_groups Funktionalität hinzu

-- ============================================================================
-- 1. User Permission Groups Tabelle erstellen
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_permission_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_group` (`user_id`,`group_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_group_id` (`group_id`),
  CONSTRAINT `user_permission_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permission_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `permission_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zuordnung von Berechtigungsgruppen zu Benutzern';

-- ============================================================================
-- 2. Exhibitor Orga Team Tabelle erstellen
-- ============================================================================

CREATE TABLE IF NOT EXISTS `exhibitor_orga_team` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `exhibitor_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_exhibitor` (`user_id`,`exhibitor_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_exhibitor_id` (`exhibitor_id`),
  CONSTRAINT `exhibitor_orga_team_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exhibitor_orga_team_ibfk_2` FOREIGN KEY (`exhibitor_id`) REFERENCES `exhibitors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Orga-Team Zuordnung zu einzelnen Ausstellern';

-- ============================================================================
-- Migration erfolgreich abgeschlossen
-- ============================================================================
-- Die folgenden neuen Funktionen sind nun verfügbar:
-- - Orga-Mitglieder können spezifischen Ausstellern zugewiesen werden
-- - Diese Mitglieder können nur für ihre zugewiesenen Aussteller QR-Codes verwalten
-- - Admins und Benutzer mit qr_codes_verwalten Berechtigung sehen weiterhin alle Aussteller
