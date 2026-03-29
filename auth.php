<?php
// auth.php — Session & Login Helper
// Include this at the top of any doctor-only page

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Call this on protected pages ──
// Redirects to login if not logged in
function requireLogin() {
    if (empty($_SESSION['doctor_id'])) {
        header("Location: login.php");
        exit();
    }
}

// ── Check if someone is logged in ──
function isLoggedIn() {
    return !empty($_SESSION['doctor_id']);
}

// ── Check if logged-in user is admin ──
// Admin can see ALL departments
function isAdmin() {
    return ($_SESSION['doctor_role'] ?? '') === 'admin';
}

// ── Get the department ID of logged-in doctor ──
// Returns NULL if admin (admin has no fixed dept)
function doctorDeptId() {
    return $_SESSION['doctor_dept_id'] ?? null;
}

// ── Get display name of logged-in doctor ──
function doctorName() {
    return $_SESSION['doctor_name'] ?? 'Doctor';
}

// ── Get role of logged-in doctor ──
function doctorRole() {
    return $_SESSION['doctor_role'] ?? 'doctor';
}
