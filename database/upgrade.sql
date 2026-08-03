-- ============================================================
-- GITEC ERP — UPGRADE SCRIPT (v1.2)
-- Run this ONCE on an existing gitec_erp database that was set
-- up before Timetable / Hostel / Placements existed.
-- Safe to run even if you have real student data already —
-- it only ADDS new tables, it never drops or alters existing ones.
-- ============================================================
USE gitec_erp;

CREATE TABLE IF NOT EXISTS timetable (
    timetable_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    section VARCHAR(20) NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    INDEX idx_timetable_lookup (department_id, semester, section, day_of_week)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hostels (
    hostel_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL,
    hostel_type ENUM('boys','girls','other') NOT NULL,
    warden_name VARCHAR(150),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hostel_rooms (
    room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT UNSIGNED NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 2,
    occupied_beds INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('available','full','maintenance') DEFAULT 'available',
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    UNIQUE (hostel_id, room_number)
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

CREATE TABLE IF NOT EXISTS companies (
    company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(150),
    website VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS placement_drives (
    drive_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    drive_title VARCHAR(255) NOT NULL,
    job_role VARCHAR(255),
    package_amount DECIMAL(12,2),
    minimum_cgpa DECIMAL(4,2) DEFAULT 0,
    drive_date DATE,
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
    UNIQUE (drive_id, student_id)
) ENGINE=InnoDB;

-- Optional demo rows for the new modules (safe to skip if you don't want demo data)
INSERT IGNORE INTO hostels (hostel_id, hostel_name, hostel_type, warden_name) VALUES
(1, 'Garuda Boys Hostel - Block A', 'boys', 'Mr. S. Naidu'),
(2, 'Garuda Girls Hostel - Block B', 'girls', 'Mrs. P. Sharma');

INSERT IGNORE INTO hostel_rooms (hostel_id, room_number, capacity) VALUES
(1, 'A-101', 2), (1, 'A-102', 2), (2, 'B-101', 2);

INSERT IGNORE INTO companies (company_id, company_name, industry) VALUES
(1, 'Infosys', 'IT Services'),
(2, 'TCS', 'IT Services'),
(3, 'Zoho', 'Product/SaaS');

INSERT IGNORE INTO placement_drives (drive_id, company_id, drive_title, job_role, package_amount, minimum_cgpa, drive_date, status) VALUES
(1, 1, 'Infosys Campus Drive 2026', 'Systems Engineer', 450000.00, 6.0, '2026-09-15', 'upcoming'),
(2, 3, 'Zoho Off-Campus Drive', 'Software Developer', 800000.00, 7.5, '2026-10-05', 'upcoming');

INSERT IGNORE INTO timetable (department_id, semester, section, subject_id, faculty_id, day_of_week, start_time, end_time, room) VALUES
(1, 3, 'A', 1, 1, 'Monday', '09:00:00', '10:00:00', 'CSE-201'),
(1, 3, 'A', 2, 1, 'Monday', '10:00:00', '11:00:00', 'CSE-201'),
(1, 3, 'A', 3, 1, 'Tuesday', '09:00:00', '10:00:00', 'CSE-201'),
(1, 3, 'A', 4, 1, 'Wednesday', '11:00:00', '12:00:00', 'CSE-Lab-1');
