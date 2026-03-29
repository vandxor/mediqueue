<?php
// api_queue.php — Live Queue Data API
// Called silently by JavaScript every 5 seconds
// Returns JSON — never shown directly to users

include 'db.php';
header('Content-Type: application/json');

// ── Optional filters from JS ──
// ?dept=2          → filter by department
// ?role=admin      → return all departments summary
$dept_id = isset($_GET['dept']) ? intval($_GET['dept']) : 0;

// ════════════════════════════════════════
//  SECTION 1 — Per-department data
//  Used by: queue.php (patient view)
//           admin.php (doctor view, filtered to their dept)
// ════════════════════════════════════════

if ($dept_id > 0) {

    // Dept info
    $dept_res = $conn->query("SELECT * FROM departments WHERE id=$dept_id LIMIT 1");
    $dept     = $dept_res ? $dept_res->fetch_assoc() : null;

    // Counts
    $wait_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$dept_id AND status='waiting'");
    $done_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$dept_id AND status='done'");
    $emerg_res = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$dept_id AND status='waiting' AND priority='emergency'");

    $waiting  = $wait_res->fetch_assoc()['cnt']  ?? 0;
    $done     = $done_res->fetch_assoc()['cnt']   ?? 0;
    $emergencies = $emerg_res->fetch_assoc()['cnt'] ?? 0;

    // Next patient (emergencies first, then by queue_number)
    $next_res = $conn->query(
        "SELECT p.*, d.name AS dept_name, d.icon AS dept_icon
         FROM patients p
         JOIN departments d ON p.department_id = d.id
         WHERE p.department_id=$dept_id AND p.status='waiting'
         ORDER BY CASE WHEN p.priority='emergency' THEN 0 ELSE 1 END ASC, p.queue_number ASC
         LIMIT 1"
    );
    $next = $next_res ? $next_res->fetch_assoc() : null;

    // Full waiting list
    $all_res = $conn->query(
        "SELECT p.*, d.name AS dept_name, d.icon AS dept_icon
         FROM patients p
         JOIN departments d ON p.department_id = d.id
         WHERE p.department_id=$dept_id AND p.status='waiting'
         ORDER BY CASE WHEN p.priority='emergency' THEN 0 ELSE 1 END ASC, p.queue_number ASC"
    );
    $queue = [];
    while ($r = $all_res->fetch_assoc()) {
        $queue[] = [
            'id'           => (int)$r['id'],
            'name'         => $r['name'],
            'age'          => $r['age'],
            'problem'      => $r['problem'],
            'queue_number' => (int)$r['queue_number'],
            'priority'     => $r['priority'],
            'dept_name'    => $r['dept_name'],
            'dept_icon'    => $r['dept_icon'],
            'registered_at'=> $r['registered_at'],
            // Token display format
            'token'        => str_pad($r['queue_number'], 3, '0', STR_PAD_LEFT),
        ];
    }

    echo json_encode([
        'mode'         => 'dept',
        'dept'         => $dept ? ['id' => $dept['id'], 'name' => $dept['name'], 'icon' => $dept['icon']] : null,
        'waiting'      => (int)$waiting,
        'done'         => (int)$done,
        'emergencies'  => (int)$emergencies,
        'est_wait'     => (int)$waiting * 10,
        'next'         => $next ? [
            'id'           => (int)$next['id'],
            'name'         => $next['name'],
            'queue_number' => (int)$next['queue_number'],
            'priority'     => $next['priority'],
            'problem'      => $next['problem'],
            'token'        => str_pad($next['queue_number'], 3, '0', STR_PAD_LEFT),
        ] : null,
        'queue'        => $queue,
        'timestamp'    => time(),
    ]);

// ════════════════════════════════════════
//  SECTION 2 — All departments summary
//  Used by: admin.php when role = admin
// ════════════════════════════════════════

} else {

    // Global counts
    $total_wait_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='waiting'");
    $total_done_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='done'");
    $total_emerg_res = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='waiting' AND priority='emergency'");

    $total_waiting    = $total_wait_res->fetch_assoc()['cnt']  ?? 0;
    $total_done       = $total_done_res->fetch_assoc()['cnt']   ?? 0;
    $total_emergencies= $total_emerg_res->fetch_assoc()['cnt']  ?? 0;

    // Per-department summary
    $dept_summary_res = $conn->query(
        "SELECT d.id, d.name, d.icon,
                COUNT(CASE WHEN p.status='waiting' THEN 1 END)                          AS waiting,
                COUNT(CASE WHEN p.status='waiting' AND p.priority='emergency' THEN 1 END) AS emergencies,
                COUNT(CASE WHEN p.status='done'    THEN 1 END)                          AS done
         FROM departments d
         LEFT JOIN patients p ON p.department_id = d.id
         WHERE d.is_active = 1
         GROUP BY d.id
         ORDER BY d.id ASC"
    );

    $departments = [];
    while ($r = $dept_summary_res->fetch_assoc()) {
        // Next patient per dept
        $nxt_res = $conn->query(
            "SELECT name, queue_number, priority FROM patients
             WHERE department_id={$r['id']} AND status='waiting'
             ORDER BY CASE WHEN priority='emergency' THEN 0 ELSE 1 END ASC, queue_number ASC LIMIT 1"
        );
        $nxt = $nxt_res ? $nxt_res->fetch_assoc() : null;

        $departments[] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'icon'        => $r['icon'],
            'waiting'     => (int)$r['waiting'],
            'emergencies' => (int)$r['emergencies'],
            'done'        => (int)$r['done'],
            'next'        => $nxt ? [
                'name'         => $nxt['name'],
                'queue_number' => (int)$nxt['queue_number'],
                'priority'     => $nxt['priority'],
                'token'        => str_pad($nxt['queue_number'], 3, '0', STR_PAD_LEFT),
            ] : null,
        ];
    }

    // All emergencies across hospital (for admin emergency tab)
    $all_emerg_res = $conn->query(
        "SELECT p.*, d.name AS dept_name, d.icon AS dept_icon
         FROM patients p
         JOIN departments d ON p.department_id = d.id
         WHERE p.status='waiting' AND p.priority='emergency'
         ORDER BY CASE WHEN p.priority='emergency' THEN 0 ELSE 1 END ASC, p.queue_number ASC"
    );
    $all_emergencies = [];
    while ($r = $all_emerg_res->fetch_assoc()) {
        $all_emergencies[] = [
            'id'        => (int)$r['id'],
            'name'      => $r['name'],
            'age'       => $r['age'],
            'problem'   => $r['problem'],
            'dept_name' => $r['dept_name'],
            'dept_icon' => $r['dept_icon'],
            'token' => str_pad($r['queue_number'], 3, '0', STR_PAD_LEFT),
            'registered_at' => $r['registered_at'],
        ];
    }

    echo json_encode([
        'mode'             => 'all',
        'total_waiting'    => (int)$total_waiting,
        'total_done'       => (int)$total_done,
        'total_emergencies'=> (int)$total_emergencies,
        'departments'      => $departments,
        'all_emergencies'  => $all_emergencies,
        'timestamp'        => time(),
    ]);
}
