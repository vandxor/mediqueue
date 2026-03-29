<?php
// queue.php — Patient-Facing Live Queue Dashboard
include 'db.php';

// Active department filter (from URL or default to all)
$selected_dept = isset($_GET['dept']) ? intval($_GET['dept']) : 0;

// Fetch all departments for tab bar
$dept_res = $conn->query("SELECT * FROM departments WHERE is_active=1 ORDER BY id ASC");
$depts    = [];
while ($d = $dept_res->fetch_assoc()) $depts[] = $d;

// Initial data for first render (before JS takes over)
if ($selected_dept > 0) {
    $dept_info_res = $conn->query("SELECT * FROM departments WHERE id=$selected_dept LIMIT 1");
    $dept_info     = $dept_info_res ? $dept_info_res->fetch_assoc() : null;

    $wait_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$selected_dept AND status='waiting'");
    $done_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE department_id=$selected_dept AND status='done'");
    $rows_res  = $conn->query("SELECT * FROM patients WHERE department_id=$selected_dept AND status='waiting' ORDER BY queue_number ASC");
} else {
    $dept_info = null;
    $wait_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='waiting'");
    $done_res  = $conn->query("SELECT COUNT(*) AS cnt FROM patients WHERE status='done'");
    $rows_res  = $conn->query("SELECT p.*, d.name AS dept_name, d.icon AS dept_icon FROM patients p JOIN departments d ON p.department_id=d.id WHERE p.status='waiting' ORDER BY p.queue_number ASC");
}

$waiting = $wait_res->fetch_assoc()['cnt'] ?? 0;
$done    = $done_res->fetch_assoc()['cnt']  ?? 0;
$rows    = [];
while ($r = $rows_res->fetch_assoc()) $rows[] = $r;
$first   = $rows[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediQueue — Live Queue</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-wrap wide">

  <!-- Header -->
  <header class="page-header">
    <a href="index.php" class="brand">
      <div class="brand-icon">🏥</div>
      <div>
        <div class="brand-name">MediQueue</div>
        <div class="brand-sub">Live Queue Board</div>
      </div>
    </a>
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
      <div class="live-indicator badge-live">
        <div class="live-dot"></div>
        Live &nbsp;·&nbsp; <span id="lastUpdate">just now</span>
      </div>
      <a href="index.php" class="btn btn-primary btn-sm">+ Register</a>
      <a href="login.php" class="btn btn-ghost btn-sm">🔐 Doctor Login</a>
    </div>
  </header>

  <!-- Notification permission bar -->
  <div class="notif-setup" id="notifBar">
    <div style="flex:1;">
      <p>🔔 <strong>Get notified when your turn is near</strong></p>
      <small>Select your department, enter your token number, then tap Notify Me</small>
    </div>
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <select id="myDeptInput" class="form-control" style="width:150px; padding:7px 12px; font-size:13px;">
        <option value="0">Select Dept...</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?= $d['id'] ?>"><?= $d['icon'] ?> <?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" id="myTokenInput" placeholder="Token no."
        min="1" class="form-control" style="width:110px; padding:7px 12px; font-size:13px;">
      <button class="btn btn-warn btn-sm" onclick="startWatching()">🔔 Notify Me</button>
    </div>
  </div>


  <!-- My turn tracker bar -->
  <div id="myTurnBar" class="tracker-box" style="display:none;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
      <div>
        <div style="font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:3px;">Tracking Your Token</div>
        <div style="display:flex; align-items:center; gap:12px;">
          <span style="font-family:var(--font-head); font-size:22px; font-weight:800; color:var(--blue);" id="myTurnToken">—</span>
          <span style="font-size:13px; color:var(--soft);" id="myTurnName"></span>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <span id="myTurnStatus"></span>
        <button onclick="clearMyTurn()" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:20px; line-height:1;">×</button>
      </div>
    </div>
  </div>

  <!-- Department tab bar -->
  <div class="tabs" id="deptTabs">
    <button class="tab <?= $selected_dept === 0 ? 'active' : '' ?>"
      onclick="switchDept(0, this)">
      🏥 All Departments
    </button>
    <?php foreach ($depts as $d): ?>
    <button class="tab <?= $selected_dept === $d['id'] ? 'active' : '' ?>"
      onclick="switchDept(<?= $d['id'] ?>, this)">
      <?= $d['icon'] ?> <?= htmlspecialchars($d['name']) ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Stats row -->
  <div class="stats-row">
    <div class="stat-card blue"  style="animation-delay:.05s"><div class="stat-label">Waiting</div>      <div class="stat-val blue"  id="statWaiting"><?= $waiting ?></div></div>
    <div class="stat-card green" style="animation-delay:.10s"><div class="stat-label">Seen Today</div>   <div class="stat-val green" id="statDone"><?= $done ?></div></div>
    <div class="stat-card amber" style="animation-delay:.15s"><div class="stat-label">Est. Wait (min)</div><div class="stat-val amber" id="statEst"><?= $waiting * 10 ?></div></div>
    <div class="stat-card red"   style="animation-delay:.20s"><div class="stat-label">Emergencies</div>  <div class="stat-val red"   id="statEmerg">0</div></div>
  </div>

  <!-- Now Serving -->
  <div id="nowServingWrap">
  <?php if ($first): ?>
  <div class="now-serving-bar <?= $first['priority']==='emergency' ? 'emerg-bar' : '' ?>" id="nowServingBar">
    <div class="ns-pulse"><?= $first['priority']==='emergency' ? '🚨' : '🩺' ?></div>
    <div>
      <div class="ns-label"><?= $first['priority']==='emergency' ? '🚨 EMERGENCY' : '▶ Now Serving' ?></div>
      <div class="ns-number" id="nsToken">
        <?= $first['priority']==='emergency'
          ? 'E-'.str_pad($first['queue_number']+1,2,'0',STR_PAD_LEFT)
          : str_pad($first['queue_number'],3,'0',STR_PAD_LEFT) ?>
      </div>
    </div>
    <div class="ns-divider"></div>
    <div>
      <div class="ns-name" id="nsName"><?= htmlspecialchars($first['name']) ?></div>
      <div class="ns-sub" id="nsDept">
        <?= isset($first['dept_icon']) ? $first['dept_icon'].' '.$first['dept_name'] : ($dept_info['icon'].' '.$dept_info['name']) ?>
      </div>
    </div>
    <div style="margin-left:auto;">
      <span class="badge <?= $first['priority']==='emergency' ? 'badge-red' : 'badge-green' ?> badge-live">
        <?= $first['priority']==='emergency' ? '🚨 Emergency' : '🟢 Active' ?>
      </span>
    </div>
  </div>
  <?php endif; ?>
  </div>

  <!-- Queue table -->
  <div class="card" style="animation:fadeUp .6s ease .25s both;">
    <div class="card-header">
      <div class="card-title">
        <div class="card-icon" style="background:rgba(56,189,248,0.1);">📋</div>
        <span id="tableTitle">
          <?= $dept_info ? htmlspecialchars($dept_info['name']).' Queue' : 'All Queues' ?>
        </span>
      </div>
      <div class="badge badge-blue" id="queueCount"><?= $waiting ?> waiting</div>
    </div>

    <div id="queueBody">
      <?php if (count($rows) === 0): ?>
        <div class="empty-state"><div class="ei">✅</div><p>Queue is clear right now.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table class="mq-table">
          <thead>
            <tr>
              <th>Token</th>
              <th>Patient</th>
              <th>Department</th>
              <th>Complaint</th>
              <th>Position</th>
            </tr>
          </thead>
          <tbody id="queueTableBody">
            <?php foreach ($rows as $i => $r):
              $token = $r['priority']==='emergency'
                ? 'E-'.str_pad($r['queue_number']+1,2,'0',STR_PAD_LEFT)
                : str_pad($r['queue_number'],3,'0',STR_PAD_LEFT);
              $isEmerg = $r['priority'] === 'emergency';
            ?>
            <tr class="<?= $i===0?'row-first':'' ?> <?= $isEmerg?'row-emerg':'' ?>">
              <td>
                <span class="q-num <?= $i===0?'first':'' ?> <?= $isEmerg?'emerg':'' ?>">
                  <?= $token ?>
                </span>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($r['name']) ?></div>
                <?php if ($r['age']): ?>
                  <div style="font-size:11px; color:var(--muted);">Age <?= $r['age'] ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if (isset($r['dept_icon'])): ?>
                  <span class="badge badge-blue"><?= $r['dept_icon'] ?> <?= htmlspecialchars($r['dept_name']) ?></span>
                <?php else: ?>
                  <span class="badge badge-blue"><?= $dept_info['icon'] ?> <?= htmlspecialchars($dept_info['name']) ?></span>
                <?php endif; ?>
              </td>
              <td style="color:var(--soft); max-width:200px;"><?= htmlspecialchars($r['problem']) ?></td>
              <td>
                <?php if ($isEmerg): ?>
                  <span class="badge badge-red">🚨 Emergency</span>
                <?php elseif ($i===0): ?>
                  <span class="badge badge-blue">▶ Next</span>
                <?php else: ?>
                  <span class="badge badge-amber"><?= $i ?> ahead</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <p style="text-align:center; font-size:11px; color:var(--muted); margin-top:16px;">
    Updates every 5 seconds &nbsp;·&nbsp;
    <a href="" style="color:var(--blue); text-decoration:none;">Refresh now ↻</a>
  </p>

</div><!-- /page-wrap -->

<script>
// ══════════════════════════════════════════
//  MediQueue — Patient Queue Live Dashboard
// ══════════════════════════════════════════

let currentDept      = <?= $selected_dept ?>;
let lastToken        = '<?= $first ? ($first['priority']==='emergency' ? 'E-'.str_pad($first['queue_number']+1,2,'0',STR_PAD_LEFT) : str_pad($first['queue_number'],3,'0',STR_PAD_LEFT)) : '' ?>';
let myQueueNumber    = parseInt(localStorage.getItem('mq_queue_number') || '0');
let myName           = localStorage.getItem('mq_name') || '';
let myDept           = parseInt(localStorage.getItem('mq_dept') || '0');
let notifSentFor     = parseInt(localStorage.getItem('mq_notif_sent') || '0');

// ── On load ──
window.addEventListener('load', () => {
  if (Notification.permission !== 'granted') {
  }
  // Read ?my=TOKEN&name=X&dept=Y from URL to set tracker
  const p = new URLSearchParams(window.location.search);
  if (p.get('my')) {
    myQueueNumber = parseInt(p.get('my'));
    myName        = p.get('name') || '';
    myDept        = parseInt(p.get('dept') || '0');
    localStorage.setItem('mq_queue_number', myQueueNumber);
    localStorage.setItem('mq_name',         myName);
    localStorage.setItem('mq_dept',         myDept);
  }
  if (myQueueNumber > 0) showMyTurnBar();
  pollQueue();
});

// ── Department tab switch ──
function switchDept(deptId, tabEl) {
  currentDept = deptId;
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  tabEl.classList.add('active');
  // Update URL without reload
  const url = new URL(window.location);
  if (deptId > 0) url.searchParams.set('dept', deptId);
  else            url.searchParams.delete('dept');
  window.history.pushState({}, '', url);
  // Immediately fetch fresh data
  pollQueue();
}

// ── Start watching a token + request notification permission ──
function startWatching() {
  const input    = document.getElementById('myTokenInput');
  const deptSel  = document.getElementById('myDeptInput');
  const token    = parseInt(input.value);
  const deptId   = parseInt(deptSel.value);

  if (!deptId || deptId < 1) {
    deptSel.style.borderColor = 'var(--red)';
    return;
  }
  if (!token || token < 1) {
    input.style.borderColor = 'var(--red)';
    input.placeholder = 'Enter token!';
    return;
  }
  deptSel.style.borderColor = '';
  input.style.borderColor   = '';

  Notification.requestPermission().then(perm => {
    if (perm === 'granted') {
      myQueueNumber = token;
      myDept        = deptId;
      notifSentFor  = ''; // reset so fresh tracking starts
      localStorage.setItem('mq_queue_number', token);
      localStorage.setItem('mq_dept',         deptId);
      localStorage.setItem('mq_notif_sent',   '');
      document.getElementById('notifBar').style.display = 'none';
      showMyTurnBar();
      showNotif(
        'MediQueue — Watching Token ' + String(token).padStart(3,'0'),
        'We will notify you when your turn is near. Keep this page open!'
      );
      playDing(1);
    } else {
      alert('Please tap Allow when browser asks for notification permission!');
    }
  });
}


function showNotif(title, body) {
  if (Notification.permission === 'granted') {
    new Notification(title, { body });
  }
}

// ── Soft ding using Web Audio ──
function playDing(times = 1) {
  try {
    const ctx  = new (window.AudioContext || window.webkitAudioContext)();
    const play = (delay) => {
      const osc  = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.3, ctx.currentTime + delay);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.7);
      osc.start(ctx.currentTime + delay);
      osc.stop(ctx.currentTime  + delay + 0.7);
    };
    for (let i = 0; i < times; i++) play(i * 0.5);
  } catch(e) {}
}

// ── Animate number change ──
function animateNum(id, val) {
  const el = document.getElementById(id);
  if (!el) return;
  const v = String(val);
  if (el.textContent !== v) {
    el.classList.remove('num-pop');
    void el.offsetWidth;
    el.textContent = v;
    el.classList.add('num-pop');
  }
}

// ── My turn tracker ──
function showMyTurnBar() {
  if (!myQueueNumber) return;
  const bar = document.getElementById('myTurnBar');
  bar.style.display = 'block';

  // Show token + dept name
  const deptNames = {
    <?php foreach ($depts as $d): ?>
    <?= $d['id'] ?>: '<?= $d['icon'] ?> <?= addslashes($d['name']) ?>',
    <?php endforeach; ?>
  };
  const deptLabel = myDept && deptNames[myDept] ? deptNames[myDept] : '';
  document.getElementById('myTurnToken').textContent = String(myQueueNumber).padStart(3, '0');
  document.getElementById('myTurnName').textContent  = deptLabel ? deptLabel : 'Watching your token';
}


function clearMyTurn() {
  localStorage.removeItem('mq_queue_number');
  localStorage.removeItem('mq_name');
  localStorage.removeItem('mq_dept');
  localStorage.removeItem('mq_notif_sent');
  myQueueNumber = 0;
  document.getElementById('myTurnBar').style.display = 'none';
}

function checkMyTurn(queue) {
  if (!myQueueNumber || !queue) return;

  const statusEl = document.getElementById('myTurnStatus');
  if (!statusEl) return;

  // Filter queue to only this patient's department
  const deptQueue = myDept > 0
    ? queue.filter(r => r.department_id == myDept || !r.department_id)
    : queue;

  // Find my token position in my department's queue
  const idx = deptQueue.findIndex(r => r.queue_number == myQueueNumber);

  // ── Not found in waiting list ──
  if (idx === -1) {
    // Only show Done if we already sent at least one status update
    // (avoids wrongly showing Done on fresh page load with empty queue)
    if (notifSentFor !== '') {
      statusEl.innerHTML = '<span class="badge badge-green">✅ Done — You have been seen!</span>';
      if (notifSentFor !== 'done') {
        showNotif('MediQueue — Complete', 'Your consultation is done. Thank you!');
        localStorage.setItem('mq_notif_sent', 'done');
        notifSentFor = 'done';
      }
    } else {
      // Fresh load, queue empty or token not found yet — show waiting
      statusEl.innerHTML = '<span style="font-size:12px; color:var(--soft);">Waiting in queue...</span>';
    }
    return;
  }

  const ahead = idx; // 0 = you are next, 1 = 1 person ahead, etc.

  // ── Status messages based on position ──
  if (ahead === 0) {
    statusEl.innerHTML = '<span class="badge badge-green badge-live">🩺 In Process — Go to doctor now!</span>';
    if (notifSentFor !== 'inprocess') {
      showNotif('YOUR TURN — MediQueue', 'Please proceed to the doctor room immediately!');
      playDing(2);
      localStorage.setItem('mq_notif_sent', 'inprocess');
      notifSentFor = 'inprocess';
    }

  } else if (ahead === 1) {
    statusEl.innerHTML = '<span class="badge badge-amber">⚠️ 1 patient ahead — Please be ready!</span>';
    if (notifSentFor !== '1ahead') {
      showNotif('Almost Your Turn — MediQueue', 'Only 1 patient ahead. Please come back and be ready!');
      playDing(1);
      localStorage.setItem('mq_notif_sent', '1ahead');
      notifSentFor = '1ahead';
    }

  } else if (ahead === 2) {
    statusEl.innerHTML = '<span class="badge badge-amber">⏳ 2 patients ahead — Start heading back</span>';
    if (notifSentFor !== '2ahead') {
      showNotif('MediQueue Reminder', '2 patients ahead of you. Start making your way back.');
      playDing(1);
      localStorage.setItem('mq_notif_sent', '2ahead');
      notifSentFor = '2ahead';
    }

  } else {
    statusEl.innerHTML = `<span style="font-size:12px; color:var(--soft);">
      ${ahead} patients ahead &nbsp;·&nbsp; ~${ahead * 10} min wait
    </span>`;
  }
}



// ── HTML escape ──
function esc(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Main poll ──
function pollQueue() {
  const url = currentDept > 0
    ? `api_queue.php?dept=${currentDept}`
    : `api_queue.php`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (data.mode === 'dept') {
        updateDeptView(data);
      } else {
        updateAllView(data);
      }
      updateLastSeen();
    })
    .catch(() => {
      document.getElementById('lastUpdate').textContent = 'connection error';
    });
}

// ── Update: single dept view ──
function updateDeptView(data) {
  animateNum('statWaiting', data.waiting);
  animateNum('statDone',    data.done);
  animateNum('statEst',     data.est_wait);
  animateNum('statEmerg',   data.emergencies);
  document.getElementById('queueCount').textContent = data.waiting + ' waiting';
  document.getElementById('tableTitle').textContent = (data.dept?.name || '') + ' Queue';

  // Now serving bar
  const nsWrap = document.getElementById('nowServingWrap');
  if (data.next) {
    const isEmerg = data.next.priority === 'emergency';
    if (data.next.token !== lastToken) {
      playDing(isEmerg ? 2 : 1);
      lastToken = data.next.token;
    }
    nsWrap.innerHTML = `
      <div class="now-serving-bar ${isEmerg ? 'emerg-bar' : ''}" id="nowServingBar">
        <div class="ns-pulse">${isEmerg ? '🚨' : '🩺'}</div>
        <div>
          <div class="ns-label">${isEmerg ? '🚨 EMERGENCY' : '▶ Now Serving'}</div>
          <div class="ns-number" id="nsToken">${esc(data.next.token)}</div>
        </div>
        <div class="ns-divider"></div>
        <div>
          <div class="ns-name">${esc(data.next.name)}</div>
          <div class="ns-sub">${esc(data.dept?.icon||'')} ${esc(data.dept?.name||'')}</div>
        </div>
        <div style="margin-left:auto;">
          <span class="badge ${isEmerg?'badge-red':'badge-green'} badge-live">
            ${isEmerg ? '🚨 Emergency' : '🟢 Active'}
          </span>
        </div>
      </div>`;
  } else {
    nsWrap.innerHTML = '<div class="alert alert-info">🎉 &nbsp; Queue is clear for this department.</div>';
  }

  // Table
  renderTable(data.queue, false);
  checkMyTurn(data.queue);
}

// ── Update: all depts view ──
function updateAllView(data) {
  animateNum('statWaiting', data.total_waiting);
  animateNum('statDone',    data.total_done);
  animateNum('statEst',     data.total_waiting * 10);
  animateNum('statEmerg',   data.total_emergencies);
  document.getElementById('queueCount').textContent = data.total_waiting + ' waiting';
  document.getElementById('tableTitle').textContent = 'All Departments';

  // Now serving — show any emergency first, else hide
  const nsWrap = document.getElementById('nowServingWrap');
  if (data.total_emergencies > 0 && data.all_emergencies.length > 0) {
    const e = data.all_emergencies[0];
    nsWrap.innerHTML = `
      <div class="emerg-bar">
        <div class="ns-pulse">🚨</div>
        <div>
          <div class="ns-label">🚨 Active Emergency</div>
          <div class="ns-number" style="color:var(--red);text-shadow:0 0 20px rgba(248,113,113,0.5);">${esc(e.token)}</div>
        </div>
        <div class="ns-divider"></div>
        <div>
          <div class="ns-name">${esc(e.name)}</div>
          <div class="ns-sub">${esc(e.dept_icon)} ${esc(e.dept_name)}</div>
        </div>
        <div style="margin-left:auto;">
          <span class="badge badge-red badge-live">🚨 ${data.total_emergencies} Emergency${data.total_emergencies>1?'s':''}</span>
        </div>
      </div>`;
  } else {
    nsWrap.innerHTML = '';
  }

  // Department cards grid
  const body = document.getElementById('queueBody');
  if (!data.departments || data.departments.length === 0) {
    body.innerHTML = '<div class="empty-state"><div class="ei">✅</div><p>All queues are clear.</p></div>';
    return;
  }

  let html = '<div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; padding:18px 22px;">';
  data.departments.forEach(dept => {
    const hasEmerg = dept.emergencies > 0;
    html += `
      <div style="background:rgba(255,255,255,0.03); border:1px solid ${hasEmerg?'rgba(248,113,113,0.25)':'var(--border)'}; border-radius:14px; padding:16px; cursor:pointer; transition:all .2s;"
           onclick="switchDept(${dept.id}, document.querySelectorAll('.tab')[${dept.id}])">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:20px;">${esc(dept.icon)}</span>
            <span style="font-weight:700; font-size:14px;">${esc(dept.name)}</span>
          </div>
          ${hasEmerg ? `<span class="badge badge-red">🚨 ${dept.emergencies}</span>` : ''}
        </div>
        <div style="display:flex; gap:14px; margin-bottom:10px;">
          <div style="text-align:center;">
            <div style="font-family:var(--font-head); font-size:22px; font-weight:800; color:var(--blue);">${dept.waiting}</div>
            <div style="font-size:10px; color:var(--muted); text-transform:uppercase;">Waiting</div>
          </div>
          <div style="text-align:center;">
            <div style="font-family:var(--font-head); font-size:22px; font-weight:800; color:var(--green);">${dept.done}</div>
            <div style="font-size:10px; color:var(--muted); text-transform:uppercase;">Done</div>
          </div>
          <div style="text-align:center;">
            <div style="font-family:var(--font-head); font-size:22px; font-weight:800; color:var(--amber);">${dept.waiting*10}</div>
            <div style="font-size:10px; color:var(--muted); text-transform:uppercase;">Est. Min</div>
          </div>
        </div>
        ${dept.next
          ? `<div style="font-size:12px; color:var(--muted);">Next: <strong style="color:var(--text);">${esc(dept.next.token)} — ${esc(dept.next.name)}</strong></div>`
          : `<div style="font-size:12px; color:var(--green);">✅ Queue clear</div>`
        }
      </div>`;
  });
  html += '</div>';
  body.innerHTML = html;
}

// ── Render queue table (single dept) ──
function renderTable(queue, showDept) {
  const body = document.getElementById('queueBody');
  if (!queue || queue.length === 0) {
    body.innerHTML = '<div class="empty-state"><div class="ei">✅</div><p>Queue is clear right now.</p></div>';
    return;
  }
  let html = `
    <div class="table-wrap">
      <table class="mq-table">
        <thead><tr>
          <th>Token</th><th>Patient</th>
          ${showDept ? '<th>Department</th>' : ''}
          <th>Complaint</th><th>Position</th>
        </tr></thead>
        <tbody id="queueTableBody">`;

  queue.forEach((r, i) => {
    const isEmerg = r.priority === 'emergency';
    html += `
      <tr class="${i===0?'row-first':''} ${isEmerg?'row-emerg':''}">
        <td><span class="q-num ${i===0?'first':''} ${isEmerg?'emerg':''}">${esc(r.token)}</span></td>
        <td>
          <div style="font-weight:600;">${esc(r.name)}</div>
          ${r.age ? `<div style="font-size:11px;color:var(--muted);">Age ${r.age}</div>` : ''}
        </td>
        ${showDept ? `<td><span class="badge badge-blue">${esc(r.dept_icon)} ${esc(r.dept_name)}</span></td>` : ''}
        <td style="color:var(--soft); max-width:200px;">${esc(r.problem)}</td>
        <td>${isEmerg
          ? '<span class="badge badge-red">🚨 Emergency</span>'
          : i===0
            ? '<span class="badge badge-blue">▶ Next</span>'
            : `<span class="badge badge-amber">${i} ahead</span>`
        }</td>
      </tr>`;
  });

  html += '</tbody></table></div>';
  body.innerHTML = html;
}

// ── Last updated counter ──
function updateLastSeen() {
  const el = document.getElementById('lastUpdate');
  if (el) el.textContent = 'just now';
  let s = 0;
  const t = setInterval(() => {
    s++;
    if (s >= 5) { clearInterval(t); return; }
    if (el) el.textContent = s + 's ago';
  }, 1000);
}

// ── Poll every 5 seconds for UI updates ──
setInterval(pollQueue, 5000);

// ── Poll every 1 second ONLY for notifications (fast response) ──
setInterval(() => {
  if (!myQueueNumber) return; // only run if patient is being tracked
  fetch(currentDept > 0 ? `api_queue.php?dept=${currentDept}` : `api_queue.php?dept=${myDept}`)
    .then(r => r.json())
    .then(data => {
      if (data.queue) checkMyTurn(data.queue);
    })
    .catch(() => {});
}, 1000);
</script>

</body>
</html>
