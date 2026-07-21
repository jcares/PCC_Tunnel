<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

session_start();

function is_logged_in(): bool
{
    return isset($_SESSION['admin_email']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ?section=login');
        exit;
    }
}

$section = $_GET['section'] ?? 'dashboard';
$db = database();

// --- Auth ---
if ($section === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $statement = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_email'] = $user['email'];
        header('Location: ?section=dashboard');
        exit;
    }
    $loginError = 'Credenciales inválidas.';
}

if ($section === 'logout') {
    session_destroy();
    header('Location: ?section=login');
    exit;
}

if ($section !== 'login') {
    require_login();
}

// --- Data queries ---
$stats = null;
if ($section === 'dashboard') {
    $stats = [
        'clients_online' => $db->query("SELECT COUNT(*) FROM clients WHERE status = 'online'")->fetchColumn(),
        'clients_total' => $db->query('SELECT COUNT(*) FROM clients')->fetchColumn(),
        'requests_total' => $db->query('SELECT COUNT(*) FROM requests')->fetchColumn(),
        'requests_today' => $db->query("SELECT COUNT(*) FROM requests WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];
}
$clients = $section === 'clients' ? $db->query("SELECT client_id, name, status, last_seen_at, enabled, created_at FROM clients ORDER BY created_at DESC")->fetchAll() : [];
$logs = $section === 'logs' ? $db->query("SELECT * FROM logs ORDER BY created_at DESC LIMIT 250")->fetchAll() : [];
$domains = $section === 'domains' ? $db->query("SELECT d.*, c.name AS client_name FROM domains d JOIN clients c ON c.client_id = d.client_id ORDER BY d.created_at DESC")->fetchAll() : [];
$users = $section === 'users' ? $db->query("SELECT id, email, created_at FROM users ORDER BY created_at DESC")->fetchAll() : [];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PCC_Tunnel — Panel</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex}
aside{width:220px;background:#1e293b;padding:24px 0;display:flex;flex-direction:column;gap:4px;flex-shrink:0}
aside h2{padding:0 24px 20px;font-size:15px;font-weight:700;color:#38bdf8;letter-spacing:.03em}
aside a{display:block;padding:9px 24px;color:#94a3b8;text-decoration:none;font-size:14px;border-left:3px solid transparent;transition:all .15s}
aside a:hover,aside a.active{color:#e2e8f0;background:#0f172a;border-left-color:#38bdf8}
aside .spacer{flex:1}
aside .logout{padding:9px 24px;font-size:13px;color:#ef4444;text-decoration:none;border-top:1px solid #334155;display:block}
main{flex:1;padding:32px;overflow:auto}
h1{font-size:22px;font-weight:700;margin-bottom:24px;color:#f1f5f9}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:32px}
.stat-card{background:#1e293b;border-radius:10px;padding:20px;border:1px solid #334155}
.stat-card p{font-size:12px;color:#94a3b8;margin-bottom:6px}
.stat-card span{font-size:28px;font-weight:700;color:#38bdf8}
table{width:100%;border-collapse:collapse;background:#1e293b;border-radius:10px;overflow:hidden;font-size:14px}
th{background:#0f172a;padding:12px 16px;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
td{padding:12px 16px;border-top:1px solid #334155;color:#cbd5e1}
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.badge-green{background:#064e3b;color:#6ee7b7}
.badge-gray{background:#1e293b;color:#64748b;border:1px solid #334155}
.badge-red{background:#450a0a;color:#fca5a5}
form input{background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:14px;width:100%;margin-bottom:12px}
form button{background:#0284c7;color:#fff;border:none;padding:10px 24px;border-radius:6px;font-size:14px;cursor:pointer}
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0f172a;flex:1}
.login-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:40px;width:340px}
.login-card h1{margin-bottom:24px;font-size:18px;color:#f1f5f9}
.error{color:#f87171;font-size:13px;margin-bottom:12px}
</style>
</head>
<body>
<?php if ($section === 'login'): ?>
<div class="login-wrap">
<div class="login-card">
<h1>PCC_Tunnel</h1>
<?php if (isset($loginError)): ?><p class="error"><?= h($loginError) ?></p><?php endif ?>
<form method="post">
<input type="email" name="email" placeholder="Correo electrónico" required>
<input type="password" name="password" placeholder="Contraseña" required>
<button type="submit">Entrar</button>
</form>
</div>
</div>
<?php else: ?>
<aside>
<h2>PCC_Tunnel</h2>
<?php
$nav = ['dashboard' => 'Dashboard', 'clients' => 'Clientes', 'domains' => 'Dominios', 'logs' => 'Logs', 'users' => 'Usuarios'];
foreach ($nav as $key => $label):
?>
<a href="?section=<?= h($key) ?>" class="<?= $section === $key ? 'active' : '' ?>"><?= h($label) ?></a>
<?php endforeach ?>
<div class="spacer"></div>
<a href="?section=logout" class="logout">Cerrar sesión</a>
</aside>
<main>
<?php if ($section === 'dashboard'): ?>
<h1>Dashboard</h1>
<div class="stats-grid">
<div class="stat-card"><p>Clientes online</p><span><?= (int) $stats['clients_online'] ?></span></div>
<div class="stat-card"><p>Clientes totales</p><span><?= (int) $stats['clients_total'] ?></span></div>
<div class="stat-card"><p>Solicitudes totales</p><span><?= (int) $stats['requests_total'] ?></span></div>
<div class="stat-card"><p>Solicitudes hoy</p><span><?= (int) $stats['requests_today'] ?></span></div>
</div>
<?php elseif ($section === 'clients'): ?>
<h1>Clientes</h1>
<table>
<tr><th>ID</th><th>Nombre</th><th>Estado</th><th>Último heartbeat</th><th>Registrado</th></tr>
<?php foreach ($clients as $client): ?>
<tr>
<td><?= h((string) $client['client_id']) ?></td>
<td><?= h((string) $client['name']) ?></td>
<td><span class="badge <?= $client['status'] === 'online' ? 'badge-green' : 'badge-gray' ?>"><?= h((string) $client['status']) ?></span></td>
<td><?= $client['last_seen_at'] ? h((string) $client['last_seen_at']) : '—' ?></td>
<td><?= h((string) $client['created_at']) ?></td>
</tr>
<?php endforeach ?>
</table>
<?php elseif ($section === 'domains'): ?>
<h1>Dominios</h1>
<table>
<tr><th>Hostname</th><th>Cliente</th><th>Activo</th><th>Registrado</th></tr>
<?php foreach ($domains as $domain): ?>
<tr>
<td><?= h((string) $domain['hostname']) ?></td>
<td><?= h((string) $domain['client_name']) ?></td>
<td><span class="badge <?= $domain['enabled'] ? 'badge-green' : 'badge-red' ?>"><?= $domain['enabled'] ? 'Sí' : 'No' ?></span></td>
<td><?= h((string) $domain['created_at']) ?></td>
</tr>
<?php endforeach ?>
</table>
<?php elseif ($section === 'logs'): ?>
<h1>Logs</h1>
<table>
<tr><th>Fecha</th><th>Nivel</th><th>Evento</th><th>Cliente</th><th>Contexto</th></tr>
<?php foreach ($logs as $log): ?>
<tr>
<td><?= h((string) $log['created_at']) ?></td>
<td><span class="badge <?= $log['level'] === 'error' ? 'badge-red' : ($log['level'] === 'warn' ? 'badge-gray' : 'badge-green') ?>"><?= h((string) $log['level']) ?></span></td>
<td><?= h((string) $log['event']) ?></td>
<td><?= $log['client_id'] ? h((string) $log['client_id']) : '—' ?></td>
<td style="font-size:12px;font-family:monospace;max-width:300px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis"><?= h((string) ($log['context'] ?? '{}')) ?></td>
</tr>
<?php endforeach ?>
</table>
<?php elseif ($section === 'users'): ?>
<h1>Usuarios</h1>
<table>
<tr><th>ID</th><th>Correo</th><th>Registrado</th></tr>
<?php foreach ($users as $user): ?>
<tr>
<td><?= (int) $user['id'] ?></td>
<td><?= h((string) $user['email']) ?></td>
<td><?= h((string) $user['created_at']) ?></td>
</tr>
<?php endforeach ?>
</table>
<?php endif ?>
</main>
<?php endif ?>
</body>
</html>
