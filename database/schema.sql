-- ============================================================
-- GARUDA INSTITUTE OF TECHNOLOGY & ENGINEERING COLLEGE (GITEC)
-- ERP DATABASE SCHEMA
-- Import this file through phpMyAdmin (XAMPP) to set everything up.
-- ============================================================

CREATE DATABASE IF NOT EXISTS gitec_erp
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gitec_erp;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. ROLES
-- =====================================================
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. PERMISSIONS
-- =====================================================
CREATE TABLE permissions (
    permission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 3. ACADEMIC YEARS / DEPARTMENTS
-- =====================================================
DROP TABLE IF EXISTS academic_years;
CREATE TABLE academic_years (
    academic_year_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_name VARCHAR(20) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS departments;
CREATE TABLE departments (
    department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_code VARCHAR(20) NOT NULL UNIQUE,
    department_name VARCHAR(150) NOT NULL UNIQUE,
    hod_name VARCHAR(150),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 4. USERS (central authentication table)
-- =====================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    status ENUM('active','inactive','locked') DEFAULT 'active',
    failed_login_attempts INT UNSIGNED DEFAULT 0,
    locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

-- =====================================================
-- 5. STUDENTS
-- =====================================================
CREATE TABLE students (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE,
    roll_number VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    date_of_birth DATE,
    gender VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(255),
    department_id INT UNSIGNED NOT NULL,
    academic_year_id INT UNSIGNED,
    current_year TINYINT UNSIGNED,
    current_semester TINYINT UNSIGNED,
    section VARCHAR(20),
    address TEXT,
    profile_photo VARCHAR(255),
    status ENUM('active','inactive','graduated','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id),
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(academic_year_id) ON DELETE SET NULL,
    INDEX idx_student_department (department_id)
) ENGINE=InnoDB;

-- =====================================================
-- 6. FACULTY
-- =====================================================
CREATE TABLE faculty (
    faculty_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE,
    employee_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    department_id INT UNSIGNED NOT NULL,
    designation VARCHAR(100),
    qualification VARCHAR(255),
    joining_date DATE,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB;

-- =====================================================
-- 7. PARENTS
-- =====================================================
CREATE TABLE parents (
    parent_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE student_parents (
    student_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NOT NULL,
    relationship VARCHAR(50),
    PRIMARY KEY (student_id, parent_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(parent_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 8. SUBJECTS
-- =====================================================
CREATE TABLE subjects (
    subject_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    subject_name VARCHAR(200) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    credits DECIMAL(4,2) DEFAULT 0,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
    UNIQUE (department_id, subject_code)
) ENGINE=InnoDB;

-- =====================================================
-- 9. ATTENDANCE
-- =====================================================
CREATE TABLE attendance (
    attendance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    faculty_id INT UNSIGNED,
    attendance_date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL,
    remarks VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL,
    UNIQUE (student_id, subject_id, attendance_date),
    INDEX idx_attendance_date (attendance_date)
) ENGINE=InnoDB;

-- =====================================================
-- 10. EXAMS / MARKS
-- =====================================================
CREATE TABLE exams (
    exam_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(150) NOT NULL,
    exam_type ENUM('internal','midterm','semester','practical') NOT NULL,
    semester TINYINT UNSIGNED,
    start_date DATE,
    end_date DATE
) ENGINE=InnoDB;

CREATE TABLE marks (
    mark_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    marks_obtained DECIMAL(6,2),
    maximum_marks DECIMAL(6,2) DEFAULT 100,
    grade VARCHAR(10),
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    UNIQUE (exam_id, student_id, subject_id)
) ENGINE=InnoDB;

-- =====================================================
-- 11. FEES
-- =====================================================
CREATE TABLE fee_structures (
    fee_structure_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id INT UNSIGNED NOT NULL,
    fee_name VARCHAR(150) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    due_date DATE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(academic_year_id)
) ENGINE=InnoDB;

CREATE TABLE student_fees (
    student_fee_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    fee_structure_id INT UNSIGNED NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    due_date DATE,
    status ENUM('pending','partial','paid','overdue') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(fee_structure_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_fee_id BIGINT UNSIGNED NOT NULL,
    transaction_reference VARCHAR(150) UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','card','upi','bank_transfer','online') NOT NULL,
    payment_status ENUM('pending','success','failed','refunded') DEFAULT 'pending',
    paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(student_fee_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 12. LIBRARY
-- =====================================================
CREATE TABLE books (
    book_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    category VARCHAR(100),
    total_copies INT UNSIGNED DEFAULT 0,
    available_copies INT UNSIGNED DEFAULT 0,
    shelf_location VARCHAR(100)
) ENGINE=InnoDB;

CREATE TABLE book_issues (
    issue_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    issued_date DATE NOT NULL,
    due_date DATE NOT NULL,
    returned_date DATE NULL,
    fine_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('issued','returned','overdue') DEFAULT 'issued',
    FOREIGN KEY (book_id) REFERENCES books(book_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 13. EVENTS / NOTIFICATIONS
-- =====================================================
CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE,
    venue VARCHAR(255),
    organizer_id INT UNSIGNED,
    status ENUM('draft','published','completed','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES faculty(faculty_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(100) DEFAULT 'general',
    sender_id INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notification_recipients (
    notification_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME NULL,
    PRIMARY KEY (notification_id, user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 14. GRIEVANCES (bonus feature)
-- =====================================================
CREATE TABLE grievances (
    grievance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    category VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('submitted','under_review','in_progress','resolved','closed') DEFAULT 'submitted',
    resolution TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 15. LOST & FOUND (bonus feature, campus utility)
-- =====================================================
CREATE TABLE lost_found_items (
    item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reported_by INT UNSIGNED NOT NULL,
    item_type ENUM('lost','found') NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    reported_date DATE DEFAULT (CURRENT_DATE),
    status ENUM('open','claimed','closed') DEFAULT 'open',
    FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 19. AUDIT LOGS
-- =====================================================
CREATE TABLE audit_logs (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100),
    record_id VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

-- =====================================================
-- 17. TIMETABLE
-- =====================================================
CREATE TABLE timetable_slots (
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
-- 18. HOSTEL
-- =====================================================
CREATE TABLE hostels (
    hostel_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_name VARCHAR(150) NOT NULL,
    hostel_type ENUM('boys','girls','other') NOT NULL DEFAULT 'other',
    address TEXT,
    capacity INT UNSIGNED DEFAULT 0,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE hostel_rooms (
    room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT UNSIGNED NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 1,
    occupied_beds INT UNSIGNED DEFAULT 0,
    status ENUM('available','full','maintenance') DEFAULT 'available',
    FOREIGN KEY (hostel_id) REFERENCES hostels(hostel_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_room (hostel_id, room_number)
) ENGINE=InnoDB;

CREATE TABLE hostel_allocations (
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
-- 19. PLACEMENTS
-- =====================================================
CREATE TABLE companies (
    company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    industry VARCHAR(150),
    contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE placement_drives (
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

CREATE TABLE placement_applications (
    application_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    drive_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    status ENUM('applied','shortlisted','selected','rejected') DEFAULT 'applied',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (drive_id) REFERENCES placement_drives(drive_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY uniq_application (drive_id, student_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
