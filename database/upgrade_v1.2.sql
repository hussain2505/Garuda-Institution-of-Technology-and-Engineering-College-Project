-- ============================================================
-- GITEC ERP — UPGRADE SCRIPT (v1.2)
-- Adds: timetable, hostel, and placement tables.
-- SAFE TO RUN on your EXISTING database — it only creates new
-- tables (IF NOT EXISTS) and does not touch any data you already
-- have in roles/users/students/fees/etc.
--
-- How to run: phpMyAdmin -> select gitec_erp -> Import ->
-- choose this file -> Go.
-- ============================================================

USE gitec_erp;

-- =====================================================
-- TIMETABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS timetable_slots (
    slot_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    section VARCHAR(20) NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    period_number TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    UNIQUE KEY uniq_slot (department_id, semester, section, day_of_week, period_number)
) ENGINE=InnoDB;

-- =====================================================
-- HOSTEL
-- =====================================================
CREATE TABLE IF NOT EXISTS hostels (
    hostel_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL,
    hostel_type ENUM('boys','girls','other') NOT NULL DEFAULT 'other',
    address TEXT,
    capacity INT UNSIGNED DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hostel_rooms (
    room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT UNSIGNED NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 1,
    occupied_beds INT UNSIGNED DEFAULT 0,
    status ENUM('available','full','maintenance') DEFAULT 'available',
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_room (hostel_id, room_number)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hostel_allocations (
    allocation_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    allocated_date DATE NOT NULL,
    vacated_date DATE NULL,
    status ENUM('active','vacated') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(room_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- PLACEMENTS
-- =====================================================
CREATE TABLE IF NOT EXISTS companies (
    company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(150),
    contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS placement_drives (
    drive_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    drive_title VARCHAR(255) NOT NULL,
    drive_date DATE,
    job_role VARCHAR(255),
    package_amount DECIMAL(12,2),
    eligibility TEXT,
    status ENUM('upcoming','open','closed') DEFAULT 'upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS placement_applications (
    application_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    drive_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('applied','shortlisted','selected','rejected') DEFAULT 'applied',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drive_id) REFERENCES placement_drives(drive_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_application (drive_id, student_id)
) ENGINE=InnoDB;

-- =====================================================
-- Demo data for the new modules (safe: only inserts if empty)
-- =====================================================
INSERT INTO hostels (hostel_name, hostel_type, capacity)
SELECT 'Garuda Boys Hostel - Block A', 'boys', 200
WHERE NOT EXISTS (SELECT 1 FROM hostels WHERE hostel_name = 'Garuda Boys Hostel - Block A');

INSERT INTO hostel_rooms (hostel_id, room_number, capacity)
SELECT h.hostel_id, 'A-101', 2 FROM hostels h WHERE h.hostel_name = 'Garuda Boys Hostel - Block A'
AND NOT EXISTS (SELECT 1 FROM hostel_rooms WHERE room_number = 'A-101');

INSERT INTO companies (company_name, industry, contact_email)
SELECT 'TechNova Solutions', 'Information Technology', 'hr@technova.example'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE company_name = 'TechNova Solutions');

INSERT INTO placement_drives (company_id, drive_title, drive_date, job_role, package_amount, eligibility, status)
SELECT c.company_id, 'Campus Drive 2026 - Software Engineer', '2026-09-15', 'Software Engineer', 650000, 'CSE/ECE, no active backlogs', 'open'
FROM companies c WHERE c.company_name = 'TechNova Solutions'
AND NOT EXISTS (SELECT 1 FROM placement_drives WHERE drive_title = 'Campus Drive 2026 - Software Engineer');
