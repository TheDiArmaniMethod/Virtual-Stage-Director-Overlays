<?php
// ============================================================
// Virtual Stage Director — Raffle Admin Panel
// Password protected. Access at /raffle/admin.php
// ============================================================
session_start();
require_once 'db.php';

$error   = '';
$message = '';

// Login
if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === ADMIN_PASSWORD) {
        $_SESSION['raffle_admin'] = true;
    } else {
        $error = 'Incorrect password.';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$authed = !empty($_SESSION['raffle_admin']);
$config = [];
$registrants = [];

if ($authed) {
    try {
        initDB();
        $pdo = getDB();

        if (!empty($_POST['action'])) {
            switch ($_POST['action']) {

                case 'toggle':
                    $cur = $pdo->query("SELECT is_open FROM event_config WHERE id = 1")->fetch();
                    if ($cur['is_open']) {
                        $pdo->exec("UPDATE event_config SET is_open = 0, closed_at = NOW() WHERE id = 1");
                        $message = 'Registration closed.';
                    } else {
                        $pdo->exec("DELETE FROM registrations");
                        $pdo->exec("UPDATE event_config SET is_open = 1, opened_at = NOW(), closed_at = NULL WHERE id = 1");
                        $message = 'Registration opened and list cleared.';
                    }
                    break;

                case 'remove':
                    $id = (int)($_POST['id'] ?? 0);
                    $pdo->prepare("DELETE FROM registrations WHERE id = ?")->execute([$id]);
                    $message = 'Registrant removed.';
                    break;

                case 'clear':
                    $pdo->exec("DELETE FROM registrations");
                    $message = 'All registrants cleared.';
                    break;
            }
        }

        $config      = $pdo->query("SELECT * FROM event_config WHERE id = 1")->fetch();
        $registrants = $pdo->query("SELECT id, name, registered_at FROM registrations ORDER BY registered_at ASC")->fetchAll();

    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

$isOpen = !empty($config['is_open']);
$count  = count($registrants);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raffle Admin</title>
    <style>
        *  { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif; background:#1a1a1a; color:#d4d4d4; min-height:100vh; }
        .wrap { max-width:780px; margin:0 auto; padding:32px 16px 64px; }

        h1   { font-size:22px; color:#ffe6a7; }
        .sub { color:#666; font-size:13px; margin-top:4px; margin-bottom:28px; }

        .card { background:#252525; border:1px solid #333; border-radius:10px; padding:22px; margin-bottom:18px; }
        .card h2 { font-size:14px; font-weight:600; color:#ffe6a7; margin-bottom:16px; text-transform:uppercase; letter-spacing:.5px; }

        .badge        { display:inline-block; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:600; }
        .badge-open   { background:#1a3a1a; color:#4caf50; }
        .badge-closed { background:#3a1a1a; color:#f44336; }

        .btn          { padding:10px 20px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-green    { background:#345830; color:#ffe6a7; }
        .btn-red      { background:#941b0c; color:#ffe6a7; }
        .btn-grey     { background:#333; color:#d4d4d4; }
        .btn-sm       { padding:4px 10px; font-size:12px; border:none; border-radius:5px; cursor:pointer; background:#3a1a1a; color:#f44336; }

        .row          { display:flex; align-items:center; justify-content:space-between; padding:11px 0; border-bottom:1px solid #2e2e2e; }
        .row:last-child { border-bottom:none; }
        .row .name    { font-size:15px; }
        .row .time    { font-size:12px; color:#555; margin-left:10px; }

        .count-big    { font-size:36px; font-weight:700; color:#ffe6a7; line-height:1; }

        .msg          { padding:10px 16px; border-radius:7px; margin-bottom:18px; font-size:14px; }
        .msg-ok       { background:#1a3a1a; color:#4caf50; }
        .msg-err      { background:#3a1a1a; color:#f44336; }

        .empty        { color:#555; font-style:italic; text-align:center; padding:20px 0; font-size:14px; }

        /* Login */
        .login-wrap   { max-width:340px; margin:80px auto; }
        .login-wrap h1 { font-size:20px; margin-bottom:6px; }
        .login-wrap p  { color:#666; font-size:13px; margin-bottom:22px; }
        input[type=password] { width:100%; padding:11px 13px; background:#1e1e1e; border:1px solid #333; border-radius:7px; color:#d4d4d4; font-size:15px; margin-bottom:12px; }

        .topbar { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:4px; }
        .logout { color:#555; font-size:13px; text-decoration:none; margin-top:4px; }
        .logout:hover { color:#d4d4d4; }
    </style>
</head>
<body>

<?php if (!$authed): ?>

<div class="wrap login-wrap">
    <h1>🎡 Raffle Admin</h1>
    <p>Enter your admin password to continue.</p>
    <?php if ($error): ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <input type="password" name="login_password" placeholder="Admin password" autofocus>
        <button class="btn btn-green" style="width:100%" type="submit">Login</button>
    </form>
</div>

<?php else: ?>

<div class="wrap">

    <div class="topbar">
        <div>
            <h1>🎡 Raffle Admin</h1>
            <div class="sub">
                <?= htmlspecialchars($config['org_name'] ?? '') ?>
                <?php if (!empty($config['event_name'])): ?> &mdash; <?= htmlspecialchars($config['event_name']) ?><?php endif; ?>
            </div>
        </div>
        <a href="?logout=1" class="logout">Logout</a>
    </div>

    <?php if ($message): ?><div class="msg msg-ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="msg msg-err"><?= htmlspecialchars($error)   ?></div><?php endif; ?>

    <!-- Status card -->
    <div class="card">
        <h2>Registration Status</h2>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <span class="badge <?= $isOpen ? 'badge-open' : 'badge-closed' ?>">
                    <?= $isOpen ? '● OPEN' : '● CLOSED' ?>
                </span>
                <?php if (!empty($config['opened_at'])): ?>
                <div style="font-size:12px;color:#555;margin-top:6px;">Opened: <?= htmlspecialchars($config['opened_at']) ?></div>
                <?php endif; ?>
                <?php if (!empty($config['closed_at'])): ?>
                <div style="font-size:12px;color:#555;margin-top:2px;">Closed: <?= htmlspecialchars($config['closed_at']) ?></div>
                <?php endif; ?>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="toggle">
                <button class="btn <?= $isOpen ? 'btn-red' : 'btn-green' ?>" type="submit">
                    <?= $isOpen ? 'Close Registration' : 'Open Registration (clears list)' ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Registrants card -->
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:baseline;gap:10px;">
                <h2 style="margin:0;">Registrants</h2>
                <div class="count-big"><?= $count ?></div>
            </div>
            <?php if ($count > 0): ?>
            <form method="POST" onsubmit="return confirm('Clear all registrants? This cannot be undone.')">
                <input type="hidden" name="action" value="clear">
                <button class="btn btn-red" type="submit">Clear All</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($count === 0): ?>
            <div class="empty">No registrants yet.</div>
        <?php else: ?>
            <?php foreach ($registrants as $r): ?>
            <div class="row">
                <div>
                    <span class="name"><?= htmlspecialchars($r['name']) ?></span>
                    <span class="time"><?= htmlspecialchars($r['registered_at']) ?></span>
                </div>
                <form method="POST"
                      onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($r['name'])) ?>?')">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                    <button class="btn-sm" type="submit">Remove</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php endif; ?>
</body>
</html>
