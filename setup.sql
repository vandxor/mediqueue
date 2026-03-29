-- ============================================
--  MediQueue v3 — Database Setup
--  Run this in phpMyAdmin > Select DB > SQL tab
-- ============================================

CREATE DATABASE IF NOT EXISTS clinic_queue;
USE clinic_queue;

-- ── 1. Departments ──────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    slug      VARCHAR(30) NOT NULL UNIQUE,
    name      VARCHAR(60) NOT NULL,
    icon      VARCHAR(10) DEFAULT '🏥',
    is_active TINYINT(1)  DEFAULT 1
);

INSERT IGNORE INTO departments (slug, name, icon) VALUES
    ('general', 'General / OPD', '🩺'),
    ('ortho',   'Orthopaedics',  '🦴'),
    ('gynae',   'Gynaecology',   '👩'),
    ('paeds',   'Paediatrics',   '👶'),
    ('cardio',  'Cardiology',    '❤️'),
    ('surgery', 'Surgery',       '🔬');


-- ── 2. Doctors / Staff ──────────────────────
CREATE TABLE IF NOT EXISTS doctors (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(40)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    full_name     VARCHAR(80)  NOT NULL,
    department_id INT          DEFAULT NULL,   -- NULL = admin, sees all depts
    role          ENUM('doctor','admin')        DEFAULT 'doctor',
    created_at    TIMESTAMP                     DEFAULT CURRENT_TIMESTAMP
);

-- Default accounts
-- Password format: username + 123  (e.g. ortho → ortho123)
INSERT IGNORE INTO doctors (username, password, full_name, department_id, role) VALUES
    ('admin',   'admin123',   'Admin / Reception',     NULL, 'admin'),
    ('general', 'general123', 'Dr. Sharma (General)',     1, 'doctor'),
    ('ortho',   'ortho123',   'Dr. Mehta (Ortho)',        2, 'doctor'),
    ('gynae',   'gynae123',   'Dr. Verma (Gynae)',        3, 'doctor'),
    ('paeds',   'paeds123',   'Dr. Iyer (Paeds)',         4, 'doctor'),
    ('cardio',  'cardio123',  'Dr. Khan (Cardio)',        5, 'doctor'),
    ('surgery', 'surgery123', 'Dr. Nair (Surgery)',       6, 'doctor');


-- ── 3. Patients ─────────────────────────────
CREATE TABLE IF NOT EXISTS patients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    age           INT          DEFAULT NULL,
    phone         VARCHAR(20)  DEFAULT '',
    problem       TEXT,
    department_id INT          NOT NULL,
    queue_number  INT          NOT NULL,
    priority      ENUM('normal','emergency')    DEFAULT 'normal',
    status        ENUM('waiting','done')        DEFAULT 'waiting',
    registered_at TIMESTAMP                     DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_dept_status ON patients (department_id, status);
CREATE INDEX IF NOT EXISTS idx_priority    ON patients (priority, status);
CREATE INDEX IF NOT EXISTS idx_queue_num   ON patients (queue_number);
