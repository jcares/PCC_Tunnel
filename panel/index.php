<?php
$sections = [
    'Usuarios' => 'users',
    'Clientes' => 'clients',
    'Tokens' => 'tokens',
    'Túneles' => 'tunnels',
    'Logs' => 'logs',
    'Estadísticas' => 'stats',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>PCC_Tunnel Panel</title>
</head>
<body>
    <main>
        <h1>PCC_Tunnel Panel</h1>
        <nav aria-label="Administración">
            <ul>
                <?php foreach ($sections as $label => $section): ?>
                    <li><a href="?section=<?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <p>El panel administrativo se conectará a la API de gestión en una etapa posterior.</p>
    </main>
</body>
</html>
