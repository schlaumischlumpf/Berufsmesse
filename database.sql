-- Datenbank für Berufsmesse-Zuteilung
-- Erstellt: 2025

CREATE DATABASE IF NOT EXISTS berufsmesse CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE berufsmesse;

-- Benutzer Tabelle
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    class VARCHAR(50),
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Räume Tabelle
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(50) NOT NULL,
    room_name VARCHAR(100),
    building VARCHAR(50),
    capacity INT DEFAULT 30,
    floor INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Aussteller Tabelle
CREATE TABLE IF NOT EXISTS exhibitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    category VARCHAR(100),
    logo VARCHAR(255),
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    website VARCHAR(255),
    total_slots INT DEFAULT 25,
    room_id INT,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL
);

-- Aussteller Dokumente
CREATE TABLE IF NOT EXISTS exhibitor_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exhibitor_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exhibitor_id) REFERENCES exhibitors(id) ON DELETE CASCADE
);

-- Zeitslots (3 feste Slots)
CREATE TABLE IF NOT EXISTS timeslots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_number INT NOT NULL,
    slot_name VARCHAR(100) NOT NULL,
    start_time TIME,
    end_time TIME
);

-- Einschreibungen
CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exhibitor_id INT NOT NULL,
    timeslot_id INT NOT NULL,
    registration_type ENUM('manual', 'automatic') DEFAULT 'manual',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (exhibitor_id) REFERENCES exhibitors(id) ON DELETE CASCADE,
    FOREIGN KEY (timeslot_id) REFERENCES timeslots(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_timeslot (user_id, timeslot_id)
);

-- System Einstellungen
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Standard Zeitslots einfügen
-- Slot 1, 3, 5: Feste Zuteilung | Slot 2, 4: Frei wählbar vor Ort
INSERT INTO timeslots (slot_number, slot_name, start_time, end_time) VALUES
(1, 'Slot 1 (Feste Zuteilung)', '09:00:00', '09:30:00'),
(2, 'Slot 2 (Freie Wahl)', '09:40:00', '10:10:00'),
(3, 'Slot 3 (Feste Zuteilung)', '10:40:00', '11:10:00'),
(4, 'Slot 4 (Freie Wahl)', '11:20:00', '11:50:00'),
(5, 'Slot 5 (Feste Zuteilung)', '12:20:00', '12:50:00');

-- Standard Einstellungen
INSERT INTO settings (setting_key, setting_value) VALUES
('registration_start', '2025-10-20 00:00:00'),
('registration_end', '2025-11-01 23:59:59'),
('event_date', '2025-11-15'),
('max_registrations_per_student', '3');

-- Beispiel-Räume hinzufügen
INSERT INTO rooms (room_number, room_name, building, capacity, floor) VALUES
('A101', 'Workshopraum 1', 'Hauptgebäude', 30, 1),
('A102', 'Workshopraum 2', 'Hauptgebäude', 25, 1),
('A103', 'Workshopraum 3', 'Hauptgebäude', 35, 1),
('A104', 'Workshopraum 4', 'Hauptgebäude', 30, 1),
('B201', 'Aula Nord', 'Nebengebäude', 40, 2),
('B202', 'Aula Süd', 'Nebengebäude', 40, 2),
('C301', 'Seminarraum 1', 'Altbau', 20, 3),
('C302', 'Seminarraum 2', 'Altbau', 20, 3);

-- Admin Benutzer erstellen (Passwort: admin123)
INSERT INTO users (username, password, firstname, lastname, class, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', NULL, 'admin');

-- Beispiel Schüler (Passwort: student123)
INSERT INTO users (username, password, firstname, lastname, class, role) VALUES
('max.mueller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Max', 'Müller', '10A', 'student'),
('anna.schmidt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Anna', 'Schmidt', '10B', 'student'),
('tom.weber', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tom', 'Weber', '11A', 'student');

-- Beispiel Aussteller (mit Raum-Zuordnungen)
INSERT INTO exhibitors (name, short_description, description, total_slots, website, email, room_id) VALUES
('Daimler AG', 'Automobilindustrie - Ausbildung und duale Studiengänge', 'Die Daimler AG bietet vielfältige Ausbildungsmöglichkeiten in technischen und kaufmännischen Berufen sowie duale Studiengänge an. Lernen Sie unsere spannenden Karrieremöglichkeiten kennen.', 30, 'www.daimler.com/karriere', 'karriere@daimler.com', 1),
('Bosch GmbH', 'Technik und Innovation - Deine Zukunft bei Bosch', 'Bei Bosch erwarten dich innovative Projekte und eine erstklassige Ausbildung. Wir bieten über 50 verschiedene Ausbildungsberufe und duale Studiengänge in den Bereichen Technik, IT und Wirtschaft.', 25, 'www.bosch.de/karriere', 'ausbildung@bosch.com', 2),
('Sparkasse', 'Banking und Finanzen - Starte deine Karriere', 'Die Sparkasse ist einer der größten Ausbildungsbetriebe in Deutschland. Wir bieten dir eine fundierte Ausbildung im Finanzsektor mit exzellenten Übernahmechancen.', 20, 'www.sparkasse.de', 'karriere@sparkasse.de', 5),
('Siemens AG', 'Elektrotechnik und Digitalisierung', 'Siemens steht für Innovation und Zukunftstechnologien. Entdecke deine Möglichkeiten in den Bereichen Elektrotechnik, Mechatronik, IT und viele mehr.', 28, 'www.siemens.com/karriere', 'jobs@siemens.com', 3),
('Deutsche Bahn', 'Mobilität der Zukunft mitgestalten', 'Bei der Deutschen Bahn kannst du in über 50 Berufen durchstarten. Von Lokführer bis IT-Spezialist - werde Teil unseres Teams!', 22, 'www.deutschebahn.com/karriere', 'ausbildung@bahn.de', 4),
('SAP SE', 'IT und Software - Die digitale Zukunft gestalten', 'SAP ist Weltmarktführer für Unternehmenssoftware. Starte bei uns in die IT-Welt mit Ausbildungen und dualen Studiengängen in Software-Entwicklung und mehr.', 18, 'www.sap.com/karriere', 'karriere@sap.com', 6);
