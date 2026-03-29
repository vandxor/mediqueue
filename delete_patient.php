<?php
// delete_patient.php — Remove a patient from the queue
// Called via link: delete_patient.php?id=5&from=admin  OR  from=queue
include 'db.php';

if (isset($_GET['id'])) {
    $id   = (int)$_GET['id'];  // cast to int — prevents SQL injection
    $from = $_GET['from'] ?? 'queue';

    $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Redirect back to wherever the request came from
    if ($from === 'admin') {
        header("Location: admin.php?msg=removed");
    } else {
        header("Location: queue.php");
    }
} else {
    // No ID given — just go home
    header("Location: index.php");
}

exit();
