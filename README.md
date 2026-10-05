# GITEC ERP — Garuda Institute of Technology & Engineering College

A final-year project: a role-based College ERP web portal built with
**PHP + MySQL**, designed to run on **XAMPP** (Apache + MySQL).

## Tech Stack
- Frontend: HTML5, CSS3, vanilla JS
- Backend: PHP 8 (PDO, prepared statements)
- Database: MySQL / MariaDB (via phpMyAdmin)
- Server: Apache (XAMPP)

## Features
- **Authentication & RBAC** — Super Admin, Admin, Faculty, Student, Parent roles
- **Modern UI** — sidebar navigation per role, dashboard analytics charts (Chart.js),
  responsive layout that collapses to a slide-out menu on mobile
- **Security** — bcrypt password hashing, prepared statements (SQL-injection safe),
  CSRF tokens on every form, output escaping (XSS safe), session regeneration on
  login, idle session timeout, account lockout after repeated failed logins,
  audit logging of key actions
- **Student portal** — dashboard with attendance/fee charts, attendance %, results,
  fee balance + QR-code online payment flow, library (borrowed books, history),
  timetable, hostel room info, placement drive listing & apply, grievance
  submission/tracking
- **Faculty portal** — dashboard, mark attendance per subject/date, enter marks
  with automatic grade calculation, teaching timetable
- **Parent portal** — read-only view of linked children's attendance & fees
- **Admin portal** — analytics dashboard (department distribution, fee collection,
  attendance trend charts), security center, student management, library
  management (add books, issue/return, late fines), fee management (create fee
  types, assign to students, record cash/UPI/card payments), **timetable
  builder** (weekly grid per department/semester/section), **hostel management**
  (hostels, rooms, allocate/vacate students), **placement management** (companies,
  drives, applicant tracking), notice/announcement broadcast, paginated audit log
- **Bonus features** — student self-registration, campus Lost & Found board

## Setup (XAMPP)

1. Copy the `GITEC-ERP` folder into `C:\xampp\htdocs\` (Windows) or
   `/Applications/XAMPP/htdocs/` (Mac) / `/opt/lampp/htdocs/` (Linux).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin/**.
4. Go to **Import**, choose `database/schema.sql`, click **Go**.
   This creates the `gitec_erp` database and all tables.
5. Import `database/seed.sql` the same way. This adds demo departments,
   subjects, fee structures, library books and 4 demo accounts.
6. Visit **http://localhost/GITEC-ERP/** in your browser.

### Demo Logins
All demo accounts use the password: `Gitec@123`

| Username    | Role        |
|-------------|-------------|
| superadmin  | Super Admin |
| admin1      | Admin       |
| faculty1    | Faculty     |
| student1    | Student     |

> No demo Parent account is seeded (parents must be linked to a student via
> the `student_parents` table by an admin). Add one manually if needed.

## Configuration
Edit `config/database.php` if your MySQL username/password differ from the
XAMPP default (`root` / empty password).

Edit `config/app.php` → `BASE_URL` if you place the project in a different
folder name than `GITEC-ERP`.

## Already imported the database before? (v1.2 update — Timetable, Hostel, Placements)
Run **`database/upgrade_v1.2.sql`** — it's safe to run on your existing database.
It only creates the new tables (`timetable_slots`, `hostels`, `hostel_rooms`,
`hostel_allocations`, `companies`, `placement_drives`, `placement_applications`)
using `CREATE TABLE IF NOT EXISTS`, so nothing you already have (students, fees,
users, etc.) is touched or dropped. In phpMyAdmin: **Import** → choose
`database/upgrade_v1.2.sql` → **Go**.

## Already imported the database before? (v1.1 update — Library & Fees)
This version adds Library and Fee management pages (the `books`,
`book_issues`, `student_fees` etc. tables were already part of the schema).
If you already ran `schema.sql`/`seed.sql` earlier and just want the demo
data for these new pages, open phpMyAdmin → `gitec_erp` → **SQL** tab and run:
```sql
INSERT INTO student_fees (student_id, fee_structure_id, amount_due, due_date, status) VALUES
(1, 1, 65000.00, '2026-08-15', 'pending'),
(1, 2, 45000.00, '2026-08-15', 'pending'),
(1, 3, 2500.00, '2026-11-01', 'pending');

INSERT INTO book_issues (book_id, student_id, issued_date, due_date, status) VALUES
(1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'issued');

UPDATE books SET available_copies = available_copies - 1 WHERE book_id = 1;
```
(Adjust the `1`s if your demo student/book/fee IDs differ — check `SELECT *`
on those tables first.) Or, for a completely fresh start, drop the
`gitec_erp` database and re-import `schema.sql` then `seed.sql` from scratch.

## Folder Structure
```
GITEC-ERP/
├── index.php              Public homepage
├── config/                DB + app configuration
├── includes/               Shared header/footer/auth-check/functions
├── auth/                  Login, logout, self-registration
├── student/               Student dashboard + modules
├── faculty/               Faculty dashboard + modules
├── parent/                Parent dashboard
├── admin/                 Admin dashboard + modules
├── campus/                Shared utilities (Lost & Found)
├── assets/                CSS / JS
├── uploads/                Student/document uploads (blocked from PHP execution)
└── database/
    ├── schema.sql          Full table structure
    └── seed.sql             Departments, subjects, fees, books, demo users
```

## Security Notes for Your Project Report
- Passwords are hashed with `password_hash()` (bcrypt) — never stored in plain text.
- All SQL queries use PDO **prepared statements** with bound parameters.
- All user-generated output is escaped with `htmlspecialchars()` via the `e()` helper.
- Every state-changing form includes a **CSRF token**, verified server-side.
- Sessions are regenerated on login (`session_regenerate_id`) and expire after
  30 minutes of inactivity.
- Repeated failed logins temporarily lock the account (5 attempts / 15 minutes).
- The `uploads/` folder has an `.htaccess` rule blocking script execution.
- Every significant action (login, logout, attendance marked, marks entered,
  fee paid, notice published, grievance submitted, account locked) is written
  to the `audit_logs` table, viewable from **Admin → Security & Audit Log**.

## Extending the Project
This is a scaffold covering the core ERP flows end-to-end. Natural next
modules to add, following the same pattern (PDO + prepared statements +
CSRF + `requireRole()`): Library issue/return, Hostel & Transport allocation,
Placement drives, Timetable, and an Analytics page using Chart.js fed by
simple `GROUP BY` queries against the existing schema.
