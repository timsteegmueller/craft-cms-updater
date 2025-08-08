<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$status = [
    'status' => 'ok',
    'checks' => [],
    'time' => date('c'),
];

try {
    Craft::$app->db->createCommand('SELECT 1')->execute();
    $status['checks']['db'] = 'ok';
} catch (Throwable $e) {
    $status['checks']['db'] = 'fail';
    $status['status'] = 'problem';
    $status['error']['db'] = $e->getMessage();
}

try {
    $q = Craft::$app->queue->getHasWaitingJobs() ? 'pending' : 'clear';
    $status['checks']['queue'] = $q;
    if ($q !== 'clear') {
        $status['status'] = 'problem';
    }
} catch (Throwable $e) {
    $status['checks']['queue'] = 'unknown';
    $status['status'] = 'problem';
    $status['error']['queue'] = $e->getMessage();
}

$status['craftVersion'] = Craft::$app->getVersion();

http_response_code($status['status'] === 'ok' ? 200 : 503);
echo json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
