<?php
// login.php — Doctor / Staff Login
include 'auth.php';
include 'db.php';

// Already logged in? Go straight to dashboard
if (isLoggedIn()) {
    header("Location: admin.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password']     ?? '';

    $safe_u = mysqli_real_escape_string($conn, $u);
    $res    = $conn->query("SELECT * FROM doctors WHERE username='$safe_u' LIMIT 1");

    if ($res && $row = $res->fetch_assoc()) {
        if ($row['password'] === $p) {
            // ✅ Correct — set session
            $_SESSION['doctor_id']      = $row['id'];
            $_SESSION['doctor_name']    = $row['full_name'];
            $_SESSION['doctor_role']    = $row['role'];
            $_SESSION['doctor_dept_id'] = $row['department_id']; // NULL if admin
            header("Location: admin.php");
            exit();
        }
    }

    $error = "Invalid username or password. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediQueue — Doctor Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <!-- Logo -->
    <div style="text-align:center; margin-bottom:26px;">
      <div class="brand-icon" style="margin:0 auto 12px; width:52px; height:52px; font-size:24px; border-radius:15px;">
        🏥
      </div>
      <div class="brand-name" style="font-size:22px;">MediQueue</div>
      <div style="font-size:12px; color:var(--muted); margin-top:3px;">Doctor &amp; Staff Portal</div>
    </div>

    <!-- Card -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <div class="card-icon" style="background:rgba(167,139,250,0.1);">🔐</div>
          Sign In
        </div>
        <span class="badge badge-purple">Restricted Access</span>
      </div>

      <div class="card-body">

        <?php if ($error): ?>
        <div class="login-error">
          ⚠️ &nbsp;<?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">

          <div class="form-group">
            <label class="form-label">Username</label>
            <input
              class="form-control"
              type="text"
              name="username"
              placeholder="Enter your username"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
              required autofocus
            >
          </div>

          <div class="form-group" style="margin-bottom:22px;">
            <label class="form-label">Password</label>
            <input
              class="form-control"
              type="password"
              name="password"
              placeholder="Enter your password"
              required
            >
          </div>

          <button type="submit" class="btn btn-primary btn-lg btn-block">
            🔓 &nbsp; Sign In to Dashboard
          </button>

        </form>

        <div class="divider"></div>

        <!-- Demo credentials hint -->
        <div style="font-size:12px; color:var(--muted); text-align:center; line-height:2;">
          Demo logins:<br>
          <code>admin / admin123</code> &nbsp;·&nbsp;
          <code>ortho / ortho123</code><br>
          <code>gynae / gynae123</code> &nbsp;·&nbsp;
          <code>cardio / cardio123</code>
        </div>

      </div>
    </div>

    <!-- Footer links -->
    <p style="text-align:center; font-size:12px; color:var(--muted); margin-top:16px;">
      <a href="index.php" style="color:var(--blue); text-decoration:none;">← Patient Registration</a>
      &nbsp;·&nbsp;
      <a href="queue.php" style="color:var(--blue); text-decoration:none;">Public Queue Board →</a>
    </p>

  </div>
</div>

</body>
</html>
