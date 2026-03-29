<?php
// add_patient.php — Process registration, assign token, show confirmation
include 'db.php';

// ── Collect and sanitise inputs ──
$name      = trim(htmlspecialchars($_POST['name']          ?? ''));
$age       = intval($_POST['age']                          ?? 0);
$phone     = trim(htmlspecialchars($_POST['phone']         ?? ''));
$problem   = trim(htmlspecialchars($_POST['problem']       ?? ''));
$dept_id   = intval($_POST['department_id']                ?? 0);
$priority  = ($_POST['priority'] ?? 'normal') === 'emergency' ? 'emergency' : 'normal';

// ── Validate ──
if (empty($name) || empty($problem) || $dept_id === 0) {
    header("Location: index.php");
    exit();
}

// ── Fetch department info ──
$dept_res  = $conn->query("SELECT * FROM departments WHERE id=$dept_id LIMIT 1");
$dept      = $dept_res ? $dept_res->fetch_assoc() : null;
if (!$dept) {
    header("Location: index.php");
    exit();
}

// ── Assign queue number ──
// Queue numbering per department — each dept has its own counter
// Only counts TODAY's patients so numbers reset each day
// Emergencies still appear at top via ORDER BY priority
$today    = date('Y-m-d');
$max_res  = $conn->query(
    "SELECT MAX(queue_number) AS maxq FROM patients
     WHERE department_id=$dept_id
     AND DATE(registered_at) = '$today'"
);
$max_row      = $max_res->fetch_assoc();
$queue_number = ($max_row['maxq'] ?? 0) + 1;

// ── Insert with prepared statement ──
// 7 params: name(s) age(i) phone(s) problem(s) department_id(i) queue_number(i) priority(s)
$stmt = $conn->prepare(
    "INSERT INTO patients (name, age, phone, problem, department_id, queue_number, priority, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting')"
);
$stmt->bind_param("sissiis", $name, $age, $phone, $problem, $dept_id, $queue_number, $priority);
$stmt->execute();
$patient_id = $conn->insert_id;
$stmt->close();

// ── How many are ahead in this dept ──
$ahead_res   = $conn->query(
    "SELECT COUNT(*) AS cnt FROM patients
     WHERE department_id=$dept_id
     AND queue_number < $queue_number
     AND status='waiting'"
);
$ahead       = $ahead_res->fetch_assoc()['cnt'] ?? 0;
$est_wait    = $ahead * 10;

// ── Display token ──
$token_display = str_pad($queue_number, 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediQueue — Your Token</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrap">

  <!-- Header -->
  <header class="page-header">
    <a href="index.php" class="brand">
      <div class="brand-icon">🏥</div>
      <div>
        <div class="brand-name">MediQueue</div>
        <div class="brand-sub">City General Hospital</div>
      </div>
    </a>
    <nav class="header-nav">
      <a href="queue.php" class="btn btn-ghost">📋 View Queue</a>
    </nav>
  </header>

  <!-- Token card -->
  <div class="success-card">
    <div class="card">
      <div class="card-body" style="padding: 32px 28px;">

        <!-- Icon -->
        <div class="success-icon">
          <?= $priority === 'emergency' ? '🚨' : '🎫' ?>
        </div>

        <!-- Status badge -->
        <?php if ($priority === 'emergency'): ?>
          <span class="badge badge-red" style="margin-bottom:14px; font-size:12px; padding:5px 14px;">
            🚨 EMERGENCY — Priority Queue
          </span>
        <?php else: ?>
          <span class="badge badge-green" style="margin-bottom:14px; font-size:12px; padding:5px 14px;">
            ✅ Registration Successful
          </span>
        <?php endif; ?>

        <h2 style="font-family:var(--font-head); font-size:20px; font-weight:700; color:#fff; margin-bottom:6px;">
          Welcome, <?= $name ?>!
        </h2>
        <p style="font-size:13px; color:var(--muted); margin-bottom:24px;">
          Please take a seat. You will be called when it's your turn.
        </p>

        <!-- Token number box -->
        <div style="
          background: <?= $priority === 'emergency' ? 'rgba(248,113,113,0.07)' : 'rgba(56,189,248,0.06)' ?>;
          border: 1px solid <?= $priority === 'emergency' ? 'rgba(248,113,113,0.22)' : 'rgba(56,189,248,0.18)' ?>;
          border-radius: 16px; padding: 24px; margin-bottom: 22px;
        ">

          <!-- Department -->
          <div style="font-size:13px; color:var(--muted); margin-bottom:12px;">
            <?= $dept['icon'] ?> &nbsp;
            <strong style="color:var(--text);"><?= htmlspecialchars($dept['name']) ?></strong>
            Department
          </div>

          <!-- Token number -->
          <div style="font-size:11px; text-transform:uppercase; letter-spacing:1.5px; color:var(--muted); margin-bottom:4px;">
            Your Queue Token
          </div>
          <!-- Dept prefix + number shown together -->
          <div style="display:flex; align-items:baseline; justify-content:center; gap:10px; flex-wrap:wrap;">
            <div style="
              font-family:var(--font-head); font-size:18px; font-weight:700;
              color:var(--muted); letter-spacing:1px; padding:6px 14px;
              background:rgba(255,255,255,0.05); border-radius:8px;
            ">
              <?= strtoupper(substr($dept['slug'], 0, 3)) ?>
            </div>
            <div class="success-qnum" style="<?= $priority === 'emergency' ? 'color:var(--red); text-shadow:0 0 34px rgba(248,113,113,0.5);' : '' ?>">
              <?= $token_display ?>
            </div>
          </div>
          <div style="font-size:12px; color:var(--muted); margin-top:6px;">
            <?= $dept['icon'] ?> <?= htmlspecialchars($dept['name']) ?> Department
          </div>

          <!-- Stats row -->
          <div style="display:flex; gap:16px; justify-content:center; margin-top:18px; flex-wrap:wrap;">
            <div style="text-align:center;">
              <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:3px;">Ahead of you</div>
              <div style="font-family:var(--font-head); font-size:24px; font-weight:800; color:<?= $ahead === 0 ? 'var(--green)' : 'var(--amber)' ?>;">
                <?= $ahead === 0 ? '0' : $ahead ?>
              </div>
            </div>
            <div style="width:1px; background:var(--border);"></div>
            <div style="text-align:center;">
              <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:3px;">Est. Wait</div>
              <div style="font-family:var(--font-head); font-size:24px; font-weight:800; color:var(--amber);">
                <?= $ahead === 0 ? '~Now' : $est_wait . ' min' ?>
              </div>
            </div>
            <div style="width:1px; background:var(--border);"></div>
            <div style="text-align:center;">
              <div style="font-size:10px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:3px;">Priority</div>
              <div style="font-family:var(--font-head); font-size:24px; font-weight:800; color:<?= $priority === 'emergency' ? 'var(--red)' : 'var(--green)' ?>;">
                <?= $priority === 'emergency' ? '🔴' : '🟢' ?>
              </div>
            </div>
          </div>

          <?php if ($ahead === 0): ?>
          <div style="margin-top:14px;">
            <span class="badge badge-green badge-live">⚡ You're next — please go to <?= htmlspecialchars($dept['name']) ?> room!</span>
          </div>
          <?php endif; ?>

        </div>

        <!-- Notification tip -->
        <div class="alert alert-warn" style="text-align:left; margin-bottom:22px;">
          🔔 &nbsp;
          <div>
            <strong>Get notified on your phone!</strong>
            <div style="font-size:12px; margin-top:2px;">
              Open <strong>queue.php</strong> on your phone browser and tap
              "Enable Notifications" — we'll alert you when your turn is 2 patients away.
            </div>
          </div>
        </div>

        <!-- Action buttons -->
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a href="queue.php?dept=<?= $dept_id ?>" class="btn btn-primary" style="flex:1; justify-content:center;">
            📋 Watch My Queue
          </a>
          <a href="index.php" class="btn btn-ghost" style="flex:1; justify-content:center;">
            ← Register Another
          </a>
        </div>

      </div>
    </div>
  </div>

</div>
</body>
</html>
