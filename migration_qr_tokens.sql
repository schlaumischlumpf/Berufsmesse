-- Migration: QR-Tokens Tabelle erstellen
-- Datum: 2026-02-18
-- Beschreibung: Erstellt die qr_tokens Tabelle für die QR-Code Anwesenheitsprüfung

CREATE TABLE IF NOT EXISTS qr_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exhibitor_id INT NOT NULL,
    timeslot_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (exhibitor_id) REFERENCES exhibitors(id) ON DELETE CASCADE,
    FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exhibitor_slot_token (exhibitor_id, timeslot_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporäre QR-Codes für Anwesenheitsprüfung';

-- Prüfen ob die Tabelle erstellt wurde
SELECT 'QR-Tokens Tabelle wurde erfolgreich erstellt oder existiert bereits.' as Status;
