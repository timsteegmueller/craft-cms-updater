<?php
declare(strict_types=1);

/**
 * Craft CMS Backup Helper
 *
 * Führt den offiziellen Craft-Befehl `craft db/backup` aus
 * innerhalb eines Docker-Containers.
 */

$projectRoot = dirname(__DIR__);
$craftDir = $projectRoot . '/craft';
$dockerComposePath = shell_exec('which docker-compose');
$dockerPath = shell_exec('which docker');

if ($dockerComposePath !== null && trim($dockerComposePath) !== '') {
    $docker = trim($dockerComposePath);
    $cmd = "$docker exec craft php craft db/backup";
} elseif ($dockerPath !== null && trim($dockerPath) !== '') {
    $docker = trim($dockerPath);
    $cmd = "$docker compose exec craft php craft db/backup";
} else {
    http_response_code(500);
    die("❌ Docker oder Docker Compose wurde nicht gefunden. Stelle sicher, dass exec() erlaubt ist.");
}
$cmd = "$docker compose exec craft php craft db/backup";

// Logging
function logLine(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

// Ausführung
$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

