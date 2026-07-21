<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    header('Location: panel/', true, 302);
    exit;
}

$errors = [];
$success = false;

function setup_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setup_value(string $name): string
{
    return trim((string) ($_POST[$name] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = setup_value('db_host');
    $dbName = setup_value('db_name');
    $dbUser = setup_value('db_user');
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $grantUser = setup_value('grant_user');
    $grantPass = (string) ($_POST['grant_pass'] ?? '');
    $adminEmail = strtolower(setup_value('admin_email'));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');
    $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');
    $updateChannel = setup_value('update_channel') ?: 'stable';
    $githubRepository = setup_value('github_repository');
    $updateCheckInterval = setup_value('update_check_interval') ?: '86400';

    if ($host === '' || strlen($host) > 253 || !preg_match('/^[a-zA-Z0-9.:%_-]+$/', $host)) {
        $errors[] = 'Indica un host de base de datos válido.';
    }
    if ($dbName === '' || strlen($dbName) > 64 || !preg_match('/^[a-zA-Z0-9_$-]+$/', $dbName)) {
        $errors[] = 'Indica un nombre de base de datos válido.';
    }
    if ($dbUser === '' || strlen($dbUser) > 128 || !preg_match('/^[a-zA-Z0-9_$@.-]+$/', $dbUser)) {
        $errors[] = 'Indica un usuario de base de datos válido.';
    }
    if (strlen($dbPass) > 512) {
        $errors[] = 'La contraseña de base de datos es demasiado larga.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 254) {
        $errors[] = 'Indica un correo de administrador válido.';
    }
    if (strlen($adminPassword) < 12 || strlen($adminPassword) > 512) {
        $errors[] = 'La contraseña del administrador debe tener entre 12 y 512 caracteres.';
    }
    if (!hash_equals($adminPassword, $adminPasswordConfirm)) {
        $errors[] = 'Las contraseñas del administrador no coinciden.';
    }
    if (!in_array($updateChannel, ['stable', 'beta', 'dev'], true)) {
        $errors[] = 'Indica un canal de actualización válido.';
    }
    if (strlen($githubRepository) > 255 || ($githubRepository !== '' && !preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $githubRepository))) {
        $errors[] = 'Indica un repositorio de GitHub válido.';
    }
    if (!ctype_digit($updateCheckInterval) || (int) $updateCheckInterval < 60 || (int) $updateCheckInterval > 604800) {
        $errors[] = 'El intervalo de actualización debe estar entre 60 y 604800 segundos.';
    }

    if ($errors === []) {
        $installationStep = 'connection';
        try {
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('La extensión pdo_mysql no está disponible.');
            }

            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $installationStep = 'migration';
            $migrationDir = __DIR__ . '/database/migrations';
            $migrationFiles = glob($migrationDir . '/*.sql') ?: [];
            sort($migrationFiles, SORT_NATURAL);
            if ($migrationFiles === []) {
                throw new RuntimeException('No se encontraron migraciones de base de datos.');
            }

            $pdo->beginTransaction();
            foreach ($migrationFiles as $migrationFile) {
                $sql = file_get_contents($migrationFile);
                if ($sql === false) {
                    throw new RuntimeException('No se pudo leer una migración.');
                }
                foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        $pdo->exec($statement);
                    }
                }
            }

            $statement = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
            $statement->execute([$adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $statement = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ([
                'update_channel' => $updateChannel,
                'github_repository' => $githubRepository,
                'update_check_interval' => $updateCheckInterval,
                'wizard_completed' => '1',
            ] as $key => $value) {
                $statement->execute([$key, $value]);
            }
            $pdo->commit();

            $installationStep = 'configuration';
            $config = [
                'PCC_DB_HOST' => $host,
                'PCC_DB_NAME' => $dbName,
                'PCC_DB_USER' => $dbUser,
                'PCC_DB_PASS' => $dbPass,
            ];
            $configContents = "<?php\n// Generated by the cPanel setup wizard. Keep this file outside public access.\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configPath, $configContents, LOCK_EX) === false) {
                throw new RuntimeException('No se pudo guardar la configuración.');
            }
            @chmod($configPath, 0600);
            $success = true;
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = match ($installationStep) {
                'connection' => 'No se pudo conectar a MySQL. Verifica el host, la base de datos, el usuario, la contraseña y que PHP tenga habilitada la extensión pdo_mysql.',
                'migration' => 'MySQL se conectó, pero no pudo crear las tablas. Asigna al usuario permisos CREATE, ALTER, INDEX e INSERT sobre esta base de datos.',
                'configuration' => 'La base de datos se configuró, pero el servidor no pudo guardar config.php. Concede permisos de escritura a la carpeta de instalación y vuelve a intentarlo.',
                default => 'No se pudo completar la instalación. Verifica los datos y los permisos del servidor.',
            };
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PCC_Tunnel — Instalación</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.setup-card{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:32px;width:100%;max-width:560px;box-shadow:0 18px 50px rgba(0,0,0,.2)}
h1{font-size:22px;color:#f1f5f9;margin-bottom:8px}.intro{font-size:14px;color:#94a3b8;margin-bottom:24px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.field{display:flex;flex-direction:column}.field-wide{grid-column:1/-1}label{font-size:12px;color:#94a3b8;margin:12px 0 6px}input,select{background:#0f172a;border:1px solid #334155;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:14px;width:100%}button{background:#0284c7;color:#fff;border:0;padding:11px 24px;border-radius:6px;font-size:14px;cursor:pointer;margin-top:24px}.error{color:#fca5a5;background:#450a0a;border:1px solid #7f1d1d;border-radius:6px;padding:10px 12px;font-size:13px;margin-bottom:16px}.success{color:#6ee7b7;background:#064e3b;border:1px solid #047857;border-radius:6px;padding:12px;font-size:14px}.success a{color:#a7f3d0}.hint{font-size:12px;color:#64748b;margin-top:5px}@media(max-width:520px){.fields{display:block}}
</style>
</head>
<body>
<main class="setup-card">
<h1>Configuración inicial</h1>
<p class="intro">Configura la base de datos y crea el primer administrador de PCC_Tunnel.</p>
<?php if ($success): ?>
<p class="success">Instalación completada. <a href="panel/">Abrir el panel</a>.</p>
<?php else: ?>
<?php foreach ($errors as $error): ?><p class="error"><?= setup_h($error) ?></p><?php endforeach ?>
<form method="post" autocomplete="off">
<div class="fields">
<div class="field"><label for="db_host">Host de base de datos</label><input id="db_host" name="db_host" value="<?= setup_h(setup_value('db_host') ?: '127.0.0.1') ?>" maxlength="253" required></div>
<div class="field"><label for="db_name">Nombre de la BD</label><input id="db_name" name="db_name" value="<?= setup_h(setup_value('db_name')) ?>" maxlength="64" required></div>
<div class="field"><label for="db_user">Usuario de la BD</label><input id="db_user" name="db_user" value="<?= setup_h(setup_value('db_user')) ?>" maxlength="128" required></div>
<div class="field"><label for="db_pass">Contraseña de la BD</label><input id="db_pass" name="db_pass" type="password" maxlength="512"></div>
<div class="field field-wide"><label for="admin_email">Correo del administrador</label><input id="admin_email" name="admin_email" type="email" value="<?= setup_h(setup_value('admin_email')) ?>" maxlength="254" required></div>
<div class="field"><label for="admin_password">Contraseña del administrador</label><input id="admin_password" name="admin_password" type="password" minlength="12" maxlength="512" required><span class="hint">Mínimo: 12 caracteres.</span></div>
<div class="field"><label for="admin_password_confirm">Repite la contraseña</label><input id="admin_password_confirm" name="admin_password_confirm" type="password" minlength="12" maxlength="512" required></div>
<div class="field field-wide"><label for="update_channel">Canal de actualización</label><select id="update_channel" name="update_channel"><option value="stable" <?= setup_value('update_channel') === 'beta' || setup_value('update_channel') === 'dev' ? '' : 'selected' ?>>stable</option><option value="beta" <?= setup_value('update_channel') === 'beta' ? 'selected' : '' ?>>beta</option><option value="dev" <?= setup_value('update_channel') === 'dev' ? 'selected' : '' ?>>dev</option></select></div>
<div class="field field-wide"><label for="github_repository">Repositorio GitHub</label><input id="github_repository" name="github_repository" value="<?= setup_h(setup_value('github_repository')) ?>" maxlength="255" placeholder="organización/repositorio"><span class="hint">Opcional. Se usa para consultar nuevas versiones.</span></div>
<div class="field field-wide"><label for="update_check_interval">Intervalo de comprobación (segundos)</label><input id="update_check_interval" name="update_check_interval" type="number" min="60" max="604800" value="<?= setup_h(setup_value('update_check_interval') ?: '86400') ?>" required></div>
</div>
<button type="submit">Instalar y crear administrador</button>
</form>
<?php endif ?>
</main>
</body>
</html>
