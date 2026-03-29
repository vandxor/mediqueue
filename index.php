<?php
// index.php — Patient Registration
include 'db.php';

// Fetch all active departments for the selector
$dept_res  = $conn->query("SELECT * FROM departments WHERE is_active=1 ORDER BY id ASC");
$depts     = [];
while ($d = $dept_res->fetch_assoc()) $depts[] = $d;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediQueue — Register</title>
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
      <a href="login.php" class="btn btn-ghost">🔐 Doctor Login</a>
    </nav>
  </header>

  <!-- Page title -->
  <h1 class="page-title">Patient Registration</h1>
  <p class="page-subtitle">Fill in your details below to receive a queue token</p>

  <!-- Form card -->
  <div class="card" style="max-width:580px; animation: fadeUp 0.5s ease 0.2s both;">
    <div class="card-header">
      <div class="card-title">
        <div class="card-icon" style="background:rgba(56,189,248,0.1);">📋</div>
        New Patient
      </div>
      <span class="badge badge-green badge-live">🟢 Queue Open</span>
    </div>

    <div class="card-body">
      <form action="add_patient.php" method="POST" id="regForm" autocomplete="off">

        <!-- Name + Age -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input
              class="form-control"
              type="text"
              name="name"
              placeholder="e.g. Rahul Sharma"
              required autofocus
            >
          </div>
          <div class="form-group">
            <label class="form-label">Age</label>
            <input
              class="form-control"
              type="number"
              name="age"
              placeholder="e.g. 34"
              min="0" max="120"
            >
          </div>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <label class="form-label">
            Phone Number
            <span style="color:var(--muted); font-weight:400;">(for turn alerts)</span>
          </label>
          <input
            class="form-control"
            type="tel"
            name="phone"
            placeholder="e.g. 9876543210"
            maxlength="15"
          >
        </div>

        <!-- Department selector -->
        <div class="form-group">
          <label class="form-label">Select Department *</label>
          <div class="dept-grid" id="deptGrid">
            <?php foreach ($depts as $d): ?>
            <label class="dept-card" onclick="selectDept(this, <?= $d['id'] ?>)">
              <input type="radio" name="department_id" value="<?= $d['id'] ?>" required>
              <div class="dept-icon"><?= $d['icon'] ?></div>
              <div class="dept-name"><?= htmlspecialchars($d['name']) ?></div>
            </label>
            <?php endforeach; ?>
          </div>
          <div id="deptError" style="font-size:12px; color:var(--red); margin-top:6px; display:none;">
            ⚠️ Please select a department
          </div>
        </div>

        <!-- Chief complaint -->
        <div class="form-group">
          <label class="form-label">Chief Complaint *</label>
          <textarea
            class="form-control"
            name="problem"
            placeholder="Briefly describe your symptoms or reason for visit..."
            required
          ></textarea>
        </div>

        <!-- Priority -->
        <div class="form-group">
          <label class="form-label">Visit Type *</label>
          <div class="priority-row">

            <label class="priority-opt sel-normal" id="opt-normal" onclick="selectPriority('normal')">
              <input type="radio" name="priority" value="normal" checked>
              <div class="p-icon">🟢</div>
              <div class="p-label">Normal</div>
              <div class="p-desc">Regular consultation</div>
            </label>

            <label class="priority-opt" id="opt-emergency" onclick="selectPriority('emergency')">
              <input type="radio" name="priority" value="emergency">
              <div class="p-icon">🔴</div>
              <div class="p-label">Emergency</div>
              <div class="p-desc">Urgent / critical condition</div>
            </label>

          </div>
        </div>

        <!-- Emergency warning -->
        <div id="emergWarn" class="alert alert-danger" style="display:none;">
          🚨 &nbsp; <div>
            <strong>Emergency selected.</strong>
            You will be placed at the top of the queue.
            For life-threatening emergencies, please go directly to the Emergency Ward.
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:6px;">
          🎫 &nbsp; Get My Queue Token
        </button>

      </form>
    </div>
  </div>

  <p style="text-align:center; font-size:12px; color:var(--muted); margin-top:20px;">
    Already registered?
    <a href="queue.php" style="color:var(--blue); text-decoration:none; font-weight:600;">
      Check your queue position →
    </a>
  </p>

</div><!-- /page-wrap -->

<script>
// ── Department card selector ──
function selectDept(el, id) {
  document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  el.querySelector('input').checked = true;
  document.getElementById('deptError').style.display = 'none';
}

// ── Priority selector ──
function selectPriority(val) {
  const normal = document.getElementById('opt-normal');
  const emerg  = document.getElementById('opt-emergency');
  const warn   = document.getElementById('emergWarn');

  if (val === 'emergency') {
    normal.classList.remove('sel-normal');
    emerg.classList.add('sel-emergency');
    warn.style.display = 'flex';
    document.querySelector('input[value="emergency"]').checked = true;
  } else {
    emerg.classList.remove('sel-emergency');
    normal.classList.add('sel-normal');
    warn.style.display = 'none';
    document.querySelector('input[value="normal"]').checked = true;
  }
}

// ── Form validation — make sure dept is selected ──
document.getElementById('regForm').addEventListener('submit', function(e) {
  const deptPicked = document.querySelector('input[name="department_id"]:checked');
  if (!deptPicked) {
    e.preventDefault();
    document.getElementById('deptError').style.display = 'block';
    document.getElementById('deptGrid').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
});
</script>

</body>
</html>
