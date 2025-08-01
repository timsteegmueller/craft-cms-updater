<?php
declare(strict_types=1);

$ts = date('Ymd-His');
$backupFile = __DIR__ . "/../../backups/db/backup-$ts.sql";
@mkdir(dirname($backupFile), 0777, true);

$db = [
    'user' => getenv('DB_USER') ?: 'craft',
    'pass' => getenv('DB_PASS') ?: 'craft',
    'host' => getenv('DB_HOST') ?: 'db',
    'name' => getenv('DB_NAME') ?: 'craft',
];

$cmd = sprintf(
    'mysqldump -u%s -p%s -h%s %s > %s',
    escapeshellarg($db['user']),
    escapeshellarg($db['pass']),
    escapeshellarg($db['host']),
    escapeshellarg($db['name']),
    escapeshellarg($backupFile)
);

exec($cmd, $output, $status);

header('Content-Type: text/plain');
echo $status === 0 ? "✅ Backup erfolgreich: $backupFile" : "❌ Backup fehlgeschlagen!";
