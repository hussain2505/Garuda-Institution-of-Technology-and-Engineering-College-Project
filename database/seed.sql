-- ============================================================
-- GITEC ERP — SEED DATA
-- Import AFTER schema.sql
-- ============================================================
USE gitec_erp;

-- Roles
INSERT INTO roles (role_name, description) VALUES
('Super Admin', 'Complete system access'),
('Admin', 'Administrative management'),
('Faculty', 'Faculty portal access'),
('Student', 'Student portal access'),
('Parent', 'Parent portal access');

-- Departments
INSERT INTO departments (department_code, department_name, hod_name) VALUES
('CSE', 'Computer Science and Engineering', 'Dr. R. Venkatesh'),
('ECE', 'Electronics and Communication Engineering', 'Dr. S. Lakshmi'),
('EEE', 'Electrical and Electronics Engineering', 'Dr. K. Prasad'),
('MECH', 'Mechanical Engineering', 'Dr. M. Rao'),
('CIVIL', 'Civil Engineering', 'Dr. A. Reddy');

-- Academic Year
INSERT INTO academic_years (year_name, start_date, end_date, is_current) VALUES
('2026-27', '2026-06-01', '2027-05-31', TRUE);

-- Subjects (CSE, semester 3, as an example)
INSERT INTO subjects (department_id, subject_code, subject_name, semester, credits) VALUES
(1, 'CS301', 'Data Structures', 3, 4.0),
(1, 'CS302', 'Database Management Systems', 3, 4.0),
(1, 'CS303', 'Operating Systems', 3, 4.0),
(1, 'CS304', 'Object Oriented Programming', 3, 3.0);

-- Fee structure for current academic year
INSERT INTO fee_structures (academic_year_id, fee_name, amount, due_date) VALUES
(1, 'Tuition Fee - Semester 1', 65000.00, '2026-08-15'),
(1, 'Hostel & Mess Fee', 45000.00, '2026-08-15'),
(1, 'Examination Fee', 2500.00, '2026-11-01');

-- Sample library books
INSERT INTO books (isbn, title, author, category, total_copies, available_copies, shelf_location) VALUES
('978-0262033848', 'Introduction to Algorithms', 'Cormen, Leiserson, Rivest, Stein', 'Computer Science', 6, 6, 'A1-12'),
('978-0073523323', 'Database System Concepts', 'Silberschatz, Korth, Sudarshan', 'Computer Science', 5, 5, 'A1-18'),
('978-1118063330', 'Operating System Concepts', 'Silberschatz, Galvin, Gagne', 'Computer Science', 4, 4, 'A1-20');

-- ------------------------------------------------------------
-- Demo accounts.
-- IMPORTANT: Passwords below are bcrypt hashes of "Gitec@123"
-- generated with PHP's password_hash(). NEVER hardcode plain
-- text passwords in SQL for a real system — use auth/register.php
-- or the password_hash() helper described in README.md instead.
-- ------------------------------------------------------------
INSERT INTO users (username, email, password_hash, role_id, status) VALUES
('superadmin', 'superadmin@gitec.edu.in', '$2b$12$U3dbqWa090Ay0u6EvPoB.uh/OHQvKO2lwIJb0QU9YnnOzj6KgD5km', 1, 'active'),
('admin1',     'admin@gitec.edu.in',      '$2b$12$U3dbqWa090Ay0u6EvPoB.uh/OHQvKO2lwIJb0QU9YnnOzj6KgD5km', 2, 'active'),
('faculty1',   'r.venkatesh@gitec.edu.in','$2b$12$U3dbqWa090Ay0u6EvPoB.uh/OHQvKO2lwIJb0QU9YnnOzj6KgD5km', 3, 'active'),
('student1',   'arjun.k@gitec.edu.in',    '$2b$12$U3dbqWa090Ay0u6EvPoB.uh/OHQvKO2lwIJb0QU9YnnOzj6KgD5km', 4, 'active');

INSERT INTO faculty (user_id, employee_id, first_name, last_name, email, phone, department_id, designation, qualification, joining_date) VALUES
(3, 'GITEC-FAC-001', 'Ramesh', 'Venkatesh', 'r.venkatesh@gitec.edu.in', '9876543210', 1, 'Associate Professor', 'Ph.D (CSE)', '2018-06-15');

INSERT INTO students (user_id, roll_number, first_name, last_name, date_of_birth, gender, phone, email, department_id, academic_year_id, current_year, current_semester, section, status) VALUES
(4, '24471A0501', 'Arjun', 'Kumar', '2006-04-12', 'Male', '9876501234', 'arjun.k@gitec.edu.in', 1, 1, 2, 3, 'A', 'active');

-- A welcome notice
INSERT INTO notifications (title, message, notification_type, sender_id) VALUES
('Welcome to GITEC ERP', 'Welcome to the Garuda Institute of Technology & Engineering College ERP portal. Please change your password after first login.', 'general', 1);

-- Assign the seeded fee structures to the demo student (student_id = 1)
INSERT INTO student_fees (student_id, fee_structure_id, amount_due, due_date, status) VALUES
(1, 1, 65000.00, '2026-08-15', 'pending'),
(1, 2, 45000.00, '2026-08-15', 'pending'),
(1, 3, 2500.00, '2026-11-01', 'pending');

-- Timetable demo slots for CSE, semester 3, section A
INSERT INTO timetable_slots (department_id, semester, section, day_of_week, period_number, start_time, end_time, subject_id, faculty_id) VALUES
(1, 3, 'A', 'Monday', 1, '09:00:00', '09:50:00', 1, 1),
(1, 3, 'A', 'Monday', 2, '09:50:00', '10:40:00', 2, NULL),
(1, 3, 'A', 'Tuesday', 1, '09:00:00', '09:50:00', 3, NULL),
(1, 3, 'A', 'Wednesday', 1, '09:00:00', '09:50:00', 4, NULL);

-- Hostel demo data
INSERT INTO hostels (hostel_name, hostel_type, capacity) VALUES
('Garuda Boys Hostel - Block A', 'boys', 200),
('Garuda Girls Hostel - Block B', 'girls', 150);

INSERT INTO hostel_rooms (hostel_id, room_number, capacity) VALUES
(1, 'A-101', 2), (1, 'A-102', 2), (2, 'B-101', 2);

-- Placement demo data
INSERT INTO companies (company_name, industry, contact_email) VALUES
('TechNova Solutions', 'Information Technology', 'hr@technova.example'),
('BuildRight Infrastructure', 'Civil & Construction', 'careers@buildright.example');

INSERT INTO placement_drives (company_id, drive_title, drive_date, job_role, package_amount, eligibility, status) VALUES
(1, 'Campus Drive 2026 - Software Engineer', '2026-09-15', 'Software Engineer', 650000.00, 'CSE/ECE, no active backlogs', 'open'),
(2, 'Campus Drive 2026 - Site Engineer', '2026-10-05', 'Site Engineer', 480000.00, 'CIVIL, no active backlogs', 'upcoming');


-- Issue a demo library book to the demo student
INSERT INTO book_issues (book_id, student_id, issued_date, due_date, status) VALUES
(1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'issued');

UPDATE books SET available_copies = available_copies - 1 WHERE book_id = 1;
