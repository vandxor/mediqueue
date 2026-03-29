<?php
// admin.php — Doctor Dashboard (login required)
include 'auth.php';
requireLogin();
include 'db.php';

$is_admin  = isAdmin();
$dept_id   = doctorDeptId();   // NULL if admin
$doc_name  = doctorName();

// ── Handle "Call Next" action ──
$message      = '';
$message_type = 'success';

if (isset($_POST['call_next'])) {
    $target_dept = intval($_POST['dept_id'] ?? $dept_id);

    // Get the first waiting patient in this dept (emergencies first = lowest queue_number)
    $res = $conn->query(
        "SELECT id, name, queue_number, priority
         FROM patients
         WHERE department_id=$target_dept AND status='waiting'
         ORDER BY queue_number ASC LIMIT 1"
    );
    if ($res && $p = $res->fetch_assoc()) {
        $conn->query("UPDATE patients SET status='done' WHERE id=" . (int)$p['id']);
        $token = str_pad($p['queue_number'], 3, '0', STR_PAD_LEFT);
        $message = "✅ Called <strong>{$token} — " . htmlspecialchars($p['name']) . "</strong>. Marked as done.";
    } else {
        $message      = "ℹ️ No patients waiting in this department.";
        $message_type = 'info';
    }
}

// ── Handle "Remove patient" ──
if (isset($_GET['remove'])) {
    $rid = (int)$_GET['remove'];
    $conn->query("DELETE FROM patients WHERE id=$rid");
    header("Location: admin.php?msg=removed");
    exit();
}

// ── Fetch departments ──
$all_depts_res = $conn->query("SELECT * FROM departments WHERE is_active=1 ORDER BY id ASC");
$all_depts     = [];
while ($d = $all_depts_res->fetch_assoc()) $all_depts[] = $d;

// ── For doctors: only their dept. For admin: first dept by default ──
$active_dept_id = $dept_id ?? ($all_depts[0]['id'] ?? 1);

// ── Fetch current dept info ──
$dept_info_res = $conn->query("SELECT * FROM departments WHERE id=$active_dept_id LIMIT 1");
$dept_info     = $dept_info_res ? $dept_info_res->fetch_assoc() : null;

// ── Stats for active dept ──
$wait_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$active_dept_id AND status='waiting'");
$done_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$active_dept_id AND status='done'");
$emerg_res = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$active_dept_id AND status='waiting' AND priority='emergency'");

$waiting     = $wait_res->fetch_assoc()['cnt']  ?? 0;
$done        = $done_res->fetch_assoc()['cnt']   ?? 0;
$emergencies = $emerg_res->fetch_assoc()['cnt']  ?? 0;

// ── Next patient ──
$next_res = $conn->query(
    "SELECT * FROM patients WHERE department_id=$active_dept_id AND status='waiting'
     ORDER BY queue_number ASC LIMIT 1"
);
$next = $next_res ? $next_res->fetch_assoc() : null;

// ── All waiting patients in this dept ──
$all_wait_res = $conn->query(
    "SELECT * FROM patients WHERE department_id=$active_dept_id AND status='waiting'
     ORDER BY queue_number ASC"
);
$all_waiting = [];
while ($r = $all_wait_res->fetch_assoc()) $all_waiting[] = $r;

// ── Emergency patients across ALL depts (for emergency tab) ──
$emerg_all_res = $conn->query(
    "SELECT p.*, d.name AS dept_name, d.icon AS dept_icon
     FROM patients p JOIN departments d ON p.department_id=d.id
     WHERE p.status='waiting' AND p.priority='emergency'
     ORDER BY p.queue_number ASC"
);
$all_emergencies = [];
while ($r = $emerg_all_res->fetch_assoc()) $all_emergencies[] = $r;

// ── Global stats (admin only) ──
$global_wait = 0; $global_done = 0; $global_emerg = 0;
if ($is_admin) {
    $gw = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='waiting'");
    $gd = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='done'");
    $ge = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='waiting' AND priority='emergency'");
    $global_wait  = $gw->fetch_assoc()['cnt'] ?? 0;
    $global_done  = $gd->fetch_assoc()['cnt']  ?? 0;
    $global_emerg = $ge->fetch_assoc()['cnt']  ?? 0;
}

// Helper: format token
function token($row) {
    return str_pad($row['queue_number'], 3, '0', STR_PAD_LEFT);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediQueue — Doctor Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrap wide">

  <!-- ── Header ── -->
  <header class="page-header">
    <a href="index.php" class="brand">
      <div class="brand-icon">🏥</div>
      <div>
        <div class="brand-name">MediQueue</div>
        <div class="brand-sub"><?= $is_admin ? 'Admin Dashboard' : 'Doctor Dashboard' ?></div>
      </div>
    </a>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
      <div class="live-indicator badge-live">
        <div class="live-dot"></div>
        Live &nbsp;·&nbsp; <span id="lastUpdate">now</span>
      </div>
      <span class="badge badge-purple">
        <?= $is_admin ? '⚙️' : '👨‍⚕️' ?> <?= htmlspecialchars($doc_name) ?>
      </span>
      <a href="queue.php" class="btn btn-ghost btn-sm">Public Board →</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
  </header>

  <?php if ($message): ?>
  <div class="alert alert-<?= $message_type ?>">
    <?= $message ?>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'removed'): ?>
  <div class="alert alert-warn">🗑️ Patient removed from queue.</div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════
       ADMIN VIEW — tabs per department
       ══════════════════════════════════════ -->
  <?php if ($is_admin): ?>

  <!-- Global stats -->
  <div class="stats-row">
    <div class="stat-card blue"  style="animation-delay:.05s"><div class="stat-label">Total Waiting</div>    <div class="stat-val blue"  id="gStatWait"><?= $global_wait ?></div></div>
    <div class="stat-card green" style="animation-delay:.10s"><div class="stat-label">Total Seen Today</div> <div class="stat-val green" id="gStatDone"><?= $global_done ?></div></div>
    <div class="stat-card red"   style="animation-delay:.15s"><div class="stat-label">Emergencies</div>      <div class="stat-val red"   id="gStatEmerg"><?= $global_emerg ?></div></div>
    <div class="stat-card amber" style="animation-delay:.20s"><div class="stat-label">Departments Active</div><div class="stat-val amber"><?= count($all_depts) ?></div></div>
  </div>

  <!-- Department tabs -->
  <div class="tabs" id="adminDeptTabs">
    <?php foreach ($all_depts as $i => $d): ?>
    <button class="tab <?= $i===0?'active':'' ?>"
      onclick="adminSwitchDept(<?= $d['id'] ?>, this)"
      id="adminTab<?= $d['id'] ?>">
      <?= $d['icon'] ?> <?= htmlspecialchars($d['name']) ?>
      <span class="badge badge-blue" id="adminTabBadge<?= $d['id'] ?>" style="margin-left:4px;">—</span>
    </button>
    <?php endforeach; ?>
    <!-- Emergency tab -->
    <button class="tab" onclick="adminSwitchDept('emergency', this)" id="adminTabEmerg">
      🚨 Emergencies
      <span class="badge badge-red badge-live" id="adminTabBadgeEmerg"><?= count($all_emergencies) ?></span>
    </button>
  </div>

  <!-- Dept pane (dynamically updated) -->
  <div id="adminDeptPane">
    <!-- JS renders this -->
    <div style="text-align:center; padding:40px; color:var(--muted);">Loading...</div>
  </div>

  <?php else: ?>
  <!-- ══════════════════════════════════════
       DOCTOR VIEW — their dept only
       ══════════════════════════════════════ -->

  <!-- Dept header -->
  <div style="display:flex; align-items:center; gap:12px; margin-bottom:22px; animation:fadeUp .4s ease both;">
    <div style="font-size:32px;"><?= $dept_info['icon'] ?? '🏥' ?></div>
    <div>
      <h1 class="page-title" style="margin-bottom:0;"><?= htmlspecialchars($dept_info['name'] ?? 'Department') ?></h1>
      <p style="font-size:12px; color:var(--muted);">Your queue for today</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card blue"  style="animation-delay:.05s"><div class="stat-label">Waiting</div>      <div class="stat-val blue"  id="dStatWait"><?= $waiting ?></div></div>
    <div class="stat-card green" style="animation-delay:.10s"><div class="stat-label">Seen Today</div>   <div class="stat-val green" id="dStatDone"><?= $done ?></div></div>
    <div class="stat-card red"   style="animation-delay:.15s"><div class="stat-label">Emergencies</div>  <div class="stat-val red"   id="dStatEmerg"><?= $emergencies ?></div></div>
    <div class="stat-card amber" style="animation-delay:.20s"><div class="stat-label">Est. Remaining</div><div class="stat-val amber" id="dStatEst"><?= $waiting * 10 ?> <span style="font-size:13px; font-weight:500;">min</span></div></div>
  </div>

  <!-- Doctor tabs: Queue | Emergencies -->
  <div class="tabs">
    <button class="tab active" onclick="showDocTab('queue', this)">📋 My Queue</button>
    <button class="tab"        onclick="showDocTab('emergency', this)">
      🚨 Emergencies
      <span class="badge badge-red badge-live" id="docEmergBadge"><?= $emergencies ?></span>
    </button>
  </div>

  <!-- Queue tab -->
  <div class="tab-pane active" id="tab-queue">

    <!-- Next patient bar -->
    <div id="docNextBar">
    <?php if ($next): ?>
    <div class="now-serving-bar <?= $next['priority']==='emergency'?'emerg-bar':'' ?>">
      <div class="ns-pulse"><?= $next['priority']==='emergency'?'🚨':'👤' ?></div>
      <div>
        <div class="ns-label"><?= $next['priority']==='emergency'?'🚨 EMERGENCY':'▶ Next Patient' ?></div>
        <div class="ns-number" id="docNextToken" style="<?= $next['priority']==='emergency'?'color:var(--red);text-shadow:0 0 20px rgba(248,113,113,0.5);':'' ?>">
          <?= token($next) ?>
        </div>
      </div>
      <div class="ns-divider"></div>
      <div style="flex:1;">
        <div class="ns-name" id="docNextName"><?= htmlspecialchars($next['name']) ?></div>
        <div class="ns-sub"><?= $next['age'] ? 'Age '.$next['age'].' · ' : '' ?><?= htmlspecialchars($next['problem']) ?></div>
      </div>
      <form method="POST" style="flex-shrink:0;">
        <input type="hidden" name="dept_id" value="<?= $active_dept_id ?>">
        <button type="submit" name="call_next"
          class="btn <?= $next['priority']==='emergency'?'btn-danger':'btn-success' ?> btn-lg"
          onclick="return confirm('Mark <?= htmlspecialchars($next['name'],ENT_QUOTES) ?> as done and call next patient?')">
          ✅ Done — Call Next
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="alert alert-info">🎉 &nbsp; No patients waiting right now.</div>
    <?php endif; ?>
    </div>

    <!-- Full queue table -->
    <div class="card" style="animation:fadeUp .5s ease .2s both;">
      <div class="card-header">
        <div class="card-title">
          <div class="card-icon" style="background:rgba(56,189,248,0.1);">📋</div>
          Waiting List
        </div>
        <div class="badge badge-blue" id="docQueueCount"><?= $waiting ?> patients</div>
      </div>
      <div id="docQueueBody">
        <?php if (count($all_waiting) === 0): ?>
          <div class="empty-state"><div class="ei">🏁</div><p>Queue is clear!</p></div>
        <?php else: ?>
        <div class="table-wrap">
          <table class="mq-table">
            <thead>
              <tr><th>Token</th><th>Patient</th><th>Problem</th><th>Priority</th><th>Action</th></tr>
            </thead>
            <tbody id="docQueueTable">
              <?php foreach ($all_waiting as $i => $r): ?>
              <tr class="<?= $i===0?'row-first':'' ?> <?= $r['priority']==='emergency'?'row-emerg':'' ?>">
                <td><span class="q-num <?= $i===0?'first':'' ?> <?= $r['priority']==='emergency'?'emerg':'' ?>"><?= token($r) ?></span></td>
                <td>
                  <div style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></div>
                  <?php if ($r['age']): ?><div style="font-size:11px;color:var(--muted);">Age <?= $r['age'] ?></div><?php endif; ?>
                  <?php if ($r['phone']): ?><div style="font-size:11px;color:var(--muted);">📞 <?= htmlspecialchars($r['phone']) ?></div><?php endif; ?>
                </td>
                <td style="color:var(--soft); max-width:220px;"><?= htmlspecialchars($r['problem']) ?></td>
                <td>
                  <?= $r['priority']==='emergency'
                    ? '<span class="badge badge-red">🚨 Emergency</span>'
                    : '<span class="badge badge-green">🟢 Normal</span>' ?>
                </td>
                <td>
                  <a href="admin.php?remove=<?= $r['id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Remove <?= htmlspecialchars($r['name'],ENT_QUOTES) ?> from queue?')">
                    ✕ Remove
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /tab-queue -->

  <!-- Emergency tab -->
  <div class="tab-pane" id="tab-emergency">
    <div class="card" style="animation:fadeUp .4s ease both;">
      <div class="card-header">
        <div class="card-title">
          <div class="card-icon" style="background:rgba(248,113,113,0.1);">🚨</div>
          Emergency Patients — All Departments
        </div>
        <span class="badge badge-red badge-live"><?= count($all_emergencies) ?> active</span>
      </div>
      <?php if (count($all_emergencies) === 0): ?>
        <div class="empty-state"><div class="ei">✅</div><p>No emergencies right now.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="mq-table">
          <thead>
            <tr><th>Token</th><th>Patient</th><th>Department</th><th>Problem</th><th>Registered</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($all_emergencies as $i => $r): ?>
            <tr class="row-emerg">
              <td><span class="q-num emerg"><?= token($r) ?></span></td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></div>
                <?php if ($r['age']): ?><div style="font-size:11px;color:var(--muted);">Age <?= $r['age'] ?></div><?php endif; ?>
                <?php if ($r['phone']): ?><div style="font-size:11px;color:var(--muted);">📞 <?= htmlspecialchars($r['phone']) ?></div><?php endif; ?>
              </td>
              <td><span class="badge badge-red"><?= $r['dept_icon'] ?> <?= htmlspecialchars($r['dept_name']) ?></span></td>
              <td style="color:var(--soft); max-width:200px;"><?= htmlspecialchars($r['problem']) ?></td>
              <td style="color:var(--muted); font-size:12px;"><?= date('h:i A', strtotime($r['registered_at'])) ?></td>
              <td>
                <a href="admin.php?remove=<?= $r['id'] ?>"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Remove this emergency patient?')">
                  ✕ Remove
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div><!-- /tab-emergency -->

  <?php endif; // end doctor view ?>

</div><!-- /page-wrap -->

<script>
// ══════════════════════════════════════════
//  MediQueue — Doctor Dashboard JS
// ══════════════════════════════════════════

const IS_ADMIN   = <?= $is_admin ? 'true' : 'false' ?>;
const MY_DEPT_ID = <?= $active_dept_id ?>;
let adminCurrentDept = <?= $all_depts[0]['id'] ?? 1 ?>;

// ── Simple tab switch (doctor view) ──
function showDocTab(name, el) {
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-' + name).classList.add('active');
}

// ── Admin dept switch ──
function adminSwitchDept(deptId, el) {
  adminCurrentDept = deptId;
  document.querySelectorAll('#adminDeptTabs .tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  renderAdminPane(deptId);
}

// ── Render admin dept pane from API ──
function renderAdminPane(deptId) {
  const pane = document.getElementById('adminDeptPane');
  pane.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);">Loading...</div>';

  if (deptId === 'emergency') {
    fetch('api_queue.php')
      .then(r => r.json())
      .then(data => renderEmergencyPane(data.all_emergencies || []));
    return;
  }

  fetch(`api_queue.php?dept=${deptId}`)
    .then(r => r.json())
    .then(data => {
      const next = data.next;
      const queue = data.queue || [];
      let html = '';

      // Next patient + call button
      if (next) {
        const isEmerg = next.priority === 'emergency';
        html += `
          <div class="now-serving-bar ${isEmerg?'emerg-bar':''}" style="margin-bottom:20px;">
            <div class="ns-pulse">${isEmerg?'🚨':'👤'}</div>
            <div>
              <div class="ns-label">${isEmerg?'🚨 EMERGENCY':'▶ Next Patient'}</div>
              <div class="ns-number" style="${isEmerg?'color:var(--red);text-shadow:0 0 20px rgba(248,113,113,0.5);':''}">${esc(next.token)}</div>
            </div>
            <div class="ns-divider"></div>
            <div style="flex:1;">
              <div class="ns-name">${esc(next.name)}</div>
              <div class="ns-sub">${esc(next.problem)}</div>
            </div>
            <form method="POST" style="flex-shrink:0;">
              <input type="hidden" name="dept_id" value="${deptId}">
              <button type="submit" name="call_next"
                class="btn ${isEmerg?'btn-danger':'btn-success'} btn-lg"
                onclick="return confirm('Mark ${esc(next.name)} as done?')">
                ✅ Done — Call Next
              </button>
            </form>
          </div>`;
      } else {
        html += '<div class="alert alert-info">🎉 &nbsp; No patients waiting in this department.</div>';
      }

      // Queue table
      html += `
        <div class="card">
          <div class="card-header">
            <div class="card-title">
              <div class="card-icon" style="background:rgba(56,189,248,0.1);">📋</div>
              ${esc(data.dept?.name||'')} — Waiting List
            </div>
            <div class="badge badge-blue">${data.waiting} patients</div>
          </div>`;

      if (queue.length === 0) {
        html += '<div class="empty-state"><div class="ei">🏁</div><p>Queue is clear!</p></div>';
      } else {
        html += `<div class="table-wrap"><table class="mq-table">
          <thead><tr><th>Token</th><th>Patient</th><th>Problem</th><th>Priority</th><th>Action</th></tr></thead>
          <tbody>`;
        queue.forEach((r, i) => {
          const isEmerg = r.priority === 'emergency';
          html += `
            <tr class="${i===0?'row-first':''} ${isEmerg?'row-emerg':''}">
              <td><span class="q-num ${i===0?'first':''} ${isEmerg?'emerg':''}">${esc(r.token)}</span></td>
              <td>
                <div style="font-weight:600;">${esc(r.name)}</div>
                ${r.age ? `<div style="font-size:11px;color:var(--muted);">Age ${r.age}</div>` : ''}
              </td>
              <td style="color:var(--soft);max-width:220px;">${esc(r.problem)}</td>
              <td>${isEmerg
                ? '<span class="badge badge-red">🚨 Emergency</span>'
                : '<span class="badge badge-green">🟢 Normal</span>'}</td>
              <td>
                <a href="admin.php?remove=${r.id}"
                   class="btn btn-danger btn-sm"
                   onclick="return confirm('Remove ${esc(r.name)}?')">
                  ✕ Remove
                </a>
              </td>
            </tr>`;
        });
        html += '</tbody></table></div>';
      }
      html += '</div>';
      pane.innerHTML = html;
    });
}

function renderEmergencyPane(emergencies) {
  const pane = document.getElementById('adminDeptPane');
  if (!emergencies || emergencies.length === 0) {
    pane.innerHTML = '<div class="empty-state"><div class="ei">✅</div><p>No emergencies across all departments.</p></div>';
    return;
  }
  let html = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <div class="card-icon" style="background:rgba(248,113,113,0.1);">🚨</div>
          All Active Emergencies
        </div>
        <span class="badge badge-red badge-live">${emergencies.length} active</span>
      </div>
      <div class="table-wrap">
        <table class="mq-table">
          <thead><tr><th>Token</th><th>Patient</th><th>Department</th><th>Problem</th><th>Time</th><th>Action</th></tr></thead>
          <tbody>`;
  emergencies.forEach(r => {
    html += `
      <tr class="row-emerg">
        <td><span class="q-num emerg">${esc(r.token)}</span></td>
        <td><div style="font-weight:600;">${esc(r.name)}</div>${r.age?`<div style="font-size:11px;color:var(--muted);">Age ${r.age}</div>`:''}</td>
        <td><span class="badge badge-red">${esc(r.dept_icon)} ${esc(r.dept_name)}</span></td>
        <td style="color:var(--soft);max-width:200px;">${esc(r.problem)}</td>
        <td style="color:var(--muted);font-size:12px;">${esc(r.registered_at)}</td>
        <td><a href="admin.php?remove=${r.id}" class="btn btn-danger btn-sm" onclick="return confirm('Remove?')">✕ Remove</a></td>
      </tr>`;
  });
  html += '</tbody></table></div></div>';
  pane.innerHTML = html;
}

// ── Animate number ──
function animateNum(id, val) {
  const el = document.getElementById(id);
  if (!el) return;
  const v = String(val);
  if (el.textContent.trim() !== v) {
    el.classList.remove('num-pop');
    void el.offsetWidth;
    el.textContent = v;
    el.classList.add('num-pop');
  }
}

function esc(str) {
  return String(str||'')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Live poll ──
function pollAdmin() {
  if (IS_ADMIN) {
    // Update global stats
    fetch('api_queue.php')
      .then(r => r.json())
      .then(data => {
        animateNum('gStatWait',  data.total_waiting);
        animateNum('gStatDone',  data.total_done);
        animateNum('gStatEmerg', data.total_emergencies);
        document.getElementById('lastUpdate').textContent = 'just now';

        // Update tab badges
        (data.departments || []).forEach(d => {
          const badge = document.getElementById('adminTabBadge' + d.id);
          if (badge) badge.textContent = d.waiting;
        });
        const eBadge = document.getElementById('adminTabBadgeEmerg');
        if (eBadge) eBadge.textContent = data.total_emergencies;

        // Refresh current pane
        renderAdminPane(adminCurrentDept);
      });
  } else {
    // Doctor: update their dept stats only
    fetch(`api_queue.php?dept=${MY_DEPT_ID}`)
      .then(r => r.json())
      .then(data => {
        animateNum('dStatWait',  data.waiting);
        animateNum('dStatDone',  data.done);
        animateNum('dStatEmerg', data.emergencies);
        animateNum('dStatEst',   data.est_wait);
        document.getElementById('lastUpdate').textContent = 'just now';

        const badge = document.getElementById('docEmergBadge');
        if (badge) badge.textContent = data.emergencies;

        // Update next patient token if changed
        if (data.next) {
          const tokenEl = document.getElementById('docNextToken');
          const nameEl  = document.getElementById('docNextName');
          if (tokenEl) tokenEl.textContent = data.next.token;
          if (nameEl)  nameEl.textContent  = data.next.name;
        }

        document.getElementById('docQueueCount').textContent = data.waiting + ' patients';
      });
  }
}

// ── Init ──
window.addEventListener('load', () => {
  if (IS_ADMIN) {
    // Load first dept pane on admin
    renderAdminPane(adminCurrentDept);
  }
});

setInterval(pollAdmin, 5000);
</script>

</body>
</html>
