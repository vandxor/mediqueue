<?php
// next_patient.php — Mark first waiting patient as done
// Used as a fallback POST target (admin.php handles this inline now,
// but this file exists for any direct form submissions)
include 'auth.php';
requireLogin();
include 'db.php';

$dept_id = isset($_POST['dept_id'])
    ? intval($_POST['dept_id'])
    : intval(doctorDeptId() ?? 0);

if ($dept_id > 0) {
    // Get the first waiting patient in this dept
    // Emergencies (queue_number < 100) always come first due to sort
    $res = $conn->query(
        "SELECT id, name, queue_number, priority
         FROM patients
         WHERE department_id = $dept_id AND status = 'waiting'
         ORDER BY queue_number ASC
         LIMIT 1"
    );

    if ($res && $p = $res->fetch_assoc()) {
        // Mark as done — keeps record for history
        $stmt = $conn->prepare("UPDATE patients SET status='done' WHERE id = ?");
        $stmt->bind_param("i", $p['id']);
        $stmt->execute();
        $stmt->close();
    }
}

// Go back to doctor dashboard
header("Location: admin.php");
exit();
