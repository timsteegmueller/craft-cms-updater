<?php
/**
 * n8n Webhook Endpoint für Craft CMS Updates - GitHub Actions Bridge
 * 
 * Diese Datei stellt einen sicheren HTTP-Endpunkt bereit, der Webhook-Calls von n8n
 * empfängt und entsprechende GitHub Repository Dispatch Events auslöst.
 * 
 * Funktionsweise:
 * 1. n8n sendet POST Request an /craft/web/webhook.php
 * 2. Request wird validiert und Daten extrahiert
 * 3. GitHub API wird aufgerufen (Repository Dispatch)
 * 4. GitHub Actions Workflow wird automatisch gestartet
 * 5. Erfolg/Fehler Response an n8n zurückgesendet
 * 
 * Sicherheitsfeatures:
 * - CORS Headers für n8n Browser-Kompatibilität
 * - HTTP Method Validation (nur POST erlaubt)
 * - Request Logging für Audit-Trail
 * - Token-basierte GitHub Authentifizierung
 * - Input Sanitization und Validation
 * 
 * Environment Variables erforderlich:
 * - GITHUB_PAT: GitHub Personal Access Token mit repo/workflow Berechtigung
 * - Optional: GITHUB_TOKEN als Alternative zu GITHUB_PAT
 * 
 * @author Tim Steegmüller
 * @version 1.0  
 * @since 2025-08-04
 * @link https://docs.github.com/en/rest/repos/repos#create-a-repository-dispatch-event
 */
declare(strict_types=1);

// ==========================================================================
// CORS HEADERS FÜR n8n KOMPATIBILITÄT SETZEN
// ==========================================================================
// n8n führt Browser-basierte HTTP Requests aus, die CORS-Unterstützung benötigen
// Diese Headers ermöglichen Cross-Origin Requests von n8n Interface
header('Access-Control-Allow-Origin: *');                    // Erlaube alle Origins (für n8n)
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');  // Erlaubte HTTP Methods
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With'); // Erlaubte Headers

// Security Headers hinzufügen
header('X-Content-Type-Options: nosniff');                   // Verhindert MIME-Type Sniffing
header('X-Frame-Options: DENY');                             // Verhindert Clickjacking

// ==========================================================================
// PREFLIGHT OPTIONS REQUEST BEHANDLUNG
// ==========================================================================
// Browser senden automatisch OPTIONS Request vor POST (CORS Preflight)
// Dieser muss mit HTTP 200 beantwortet werden damit der echte Request folgen kann
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;  // Verarbeitung hier beenden
}

// ==========================================================================
// HTTP METHOD VALIDATION
// ==========================================================================
// Nur POST Requests sind erlaubt - alle anderen Methods werden abgelehnt
// Dies verhindert versehentliche GET Requests die keine Aktionen auslösen sollen
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);  // Method Not Allowed
    header('Allow: POST, OPTIONS');  // Mitteilen welche Methods erlaubt sind
    echo json_encode([
        'error' => 'HTTP Method nicht erlaubt',
        'allowed_methods' => ['POST', 'OPTIONS'],
        'received_method' => $_SERVER['REQUEST_METHOD']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================================================
// RESPONSE CONTENT-TYPE FESTLEGEN
// ==========================================================================
// JSON Content-Type für strukturierte API-Responses
header('Content-Type: application/json; charset=utf-8');

try {
    // ==========================================================================
    // REQUEST BODY EINLESEN UND VALIDIEREN
    // ==========================================================================
    // Raw POST Body als String einlesen (enthält JSON von n8n)
    $input = file_get_contents('php://input');
    
    // Prüfen ob Request Body leer ist
    if (empty($input)) {
        http_response_code(400);  // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'Request Body ist leer',
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // JSON decoding mit Fehlerbehandlung
    $data = json_decode($input, true);
    
    // JSON Parse Error prüfen
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);  // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'Ungültiges JSON Format',
            'json_error' => json_last_error_msg(),
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ==========================================================================
    // REQUEST PARAMETER EXTRAKTION UND SANITIZATION
    // ==========================================================================
    // Standard-Werte für fehlende Parameter setzen
    $action = isset($data['action']) ? trim(strtolower($data['action'])) : 'update';
    $source = isset($data['source']) ? trim($data['source']) : 'manual';
    
    // Action Parameter validieren (nur erlaubte Werte)
    $allowedActions = ['update', 'backup', 'maintenance', 'test'];
    if (!in_array($action, $allowedActions)) {
        http_response_code(400);  // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'Ungültige Action',
            'received_action' => $action,
            'allowed_actions' => $allowedActions,
            'timestamp' => date('c')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ==========================================================================
    // REQUEST LOGGING FÜR AUDIT TRAIL
    // ==========================================================================
    // Alle eingehenden Requests loggen für Debugging und Security Monitoring
    $logEntry = sprintf(
        "[WEBHOOK REQUEST] %s - Action: %s, Source: %s, IP: %s, User-Agent: %s",
        date('Y-m-d H:i:s'),
        $action,
        $source,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    error_log($logEntry);
    
    // ==========================================================================
    // GITHUB REPOSITORY DISPATCH AUSLÖSEN
    // ==========================================================================
    // Hauptfunktion: GitHub API aufrufen um Actions Workflow zu starten
    $githubResponse = triggerGitHubAction($action, $source);
    
    // ==========================================================================
    // ERFOLGREICHE RESPONSE ZURÜCKSENDEN
    // ==========================================================================
    if ($githubResponse['success']) {
        http_response_code(200);  // OK
        
        // Strukturierte Erfolgs-Response mit allen relevanten Informationen
        echo json_encode([
            'status' => 'success',
            'message' => 'GitHub Action erfolgreich ausgelöst',
            'timestamp' => date('c'),
            'request_id' => uniqid('req_', true),  // Eindeutige Request ID für Tracking
            'action' => $action,
            'source' => $source,
            'github_response' => $githubResponse['data']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Erfolg auch ins Log schreiben
        error_log(sprintf(
            "[WEBHOOK SUCCESS] %s - GitHub Dispatch erfolgreich für Action: %s",
            date('Y-m-d H:i:s'),
            $action
        ));
    } else {
        // ==========================================================================
        // FEHLER-RESPONSE BEI GITHUB API PROBLEMEN
        // ==========================================================================
        http_response_code(500);  // Internal Server Error
        
        echo json_encode([
            'status' => 'error',
            'message' => 'GitHub Action konnte nicht ausgelöst werden',
            'error' => $githubResponse['error'],
            'timestamp' => date('c'),
            'action' => $action,
            'source' => $source
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        // Fehler detailliert loggen
        error_log(sprintf(
            "[WEBHOOK ERROR] %s - GitHub Dispatch fehlgeschlagen: %s",
            date('Y-m-d H:i:s'),
            $githubResponse['error']
        ));
    }
    
} catch (Exception $e) {
    // ==========================================================================
    // GLOBALER EXCEPTION HANDLER
    // ==========================================================================
    // Fängt alle unerwarteten Fehler ab und loggt sie für Debugging
    
    $errorMessage = sprintf(
        "[WEBHOOK EXCEPTION] %s - %s in %s:%d - Trace: %s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($errorMessage);
    
    // HTTP 500 Internal Server Error für unerwartete Fehler
    http_response_code(500);
    
    // Strukturierte Fehler-Response (keine sensitive Informationen preisgeben)
    echo json_encode([
        'status' => 'error',
        'message' => 'Interner Serverfehler beim Webhook Processing',
        'error_type' => get_class($e),
        'timestamp' => date('c'),
        'request_id' => uniqid('err_', true)
        // Bewusst keine Stacktrace oder Dateipfade für Security
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * GitHub Repository Dispatch API Funktion
 * 
 * Diese Funktion ruft die GitHub REST API auf um ein Repository Dispatch Event
 * auszulösen, welches automatisch GitHub Actions Workflows startet.
 * 
 * @param string $action Die gewünschte Aktion (update, backup, maintenance, test)
 * @param string $source Die Quelle des Requests (n8n_scheduler, manual, webhook)
 * @return array Assoziatives Array mit 'success' boolean und 'data'/'error' Details
 * 
 * @link https://docs.github.com/en/rest/repos/repos#create-a-repository-dispatch-event
 */
function triggerGitHubAction(string $action, string $source): array
{
    // ==========================================================================
    // GITHUB TOKEN AUTHENTIFIZIERUNG
    // ==========================================================================
    // Personal Access Token aus Environment Variables laden
    // Priorität: GITHUB_PAT > GITHUB_TOKEN (für verschiedene Deployment-Umgebungen)
    $githubToken = getenv('GITHUB_PAT') ?: getenv('GITHUB_TOKEN');
    
    // Token Validierung - ohne Token können wir nicht mit GitHub API kommunizieren
    if (!$githubToken) {
        error_log("[GITHUB API ERROR] Kein GitHub Token gefunden in Environment Variables");
        return [
            'success' => false,
            'error' => 'GitHub Token nicht konfiguriert - GITHUB_PAT oder GITHUB_TOKEN Environment Variable setzen'
        ];
    }
    
    // Token Format validieren (sollte mit 'ghp_' oder 'github_pat_' beginnen)
    if (!preg_match('/^(ghp_|github_pat_)[a-zA-Z0-9_]+$/', $githubToken)) {
        error_log("[GITHUB API ERROR] GitHub Token hat ungültiges Format");
        return [
            'success' => false,
            'error' => 'GitHub Token hat ungültiges Format'
        ];
    }
    
    // ==========================================================================
    // REPOSITORY UND EVENT TYPE KONFIGURATION
    // ==========================================================================
    // Ziel-Repository für GitHub Actions (anpassen je nach Setup)
    $repo = 'timsteegmueller/craft-test-repo';
    
    // Event Type Mapping: Action → GitHub Actions Workflow Trigger
    $eventTypeMap = [
        'update' => 'run-backup-und-update',    // Standard Update mit Backup
        'backup' => 'run-backup-only',          // Nur Backup ohne Update
        'maintenance' => 'run-maintenance',     // Wartungsarbeiten
        'test' => 'run-test-workflow'           // Test-Läufe
    ];
    
    // Event Type basierend auf Action bestimmen
    $eventType = $eventTypeMap[$action] ?? 'run-backup-und-update';
    
    // ==========================================================================
    // API REQUEST PAYLOAD ZUSAMMENSTELLEN
    // ==========================================================================
    // Repository Dispatch Payload gemäß GitHub API Spezifikation
    $payload = [
        'event_type' => $eventType,              // Workflow Trigger Event Name
        'client_payload' => [                    // Zusätzliche Daten für Workflow
            'source' => $source,                 // Herkunft des Triggers
            'timestamp' => date('c'),            // ISO 8601 Zeitstempel
            'triggered_by' => 'webhook',         // Trigger-Methode
            'action' => $action,                 // Ursprüngliche Action
            'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown', // Server IP
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'PHP-Webhook', // Client Info
            'request_id' => uniqid('dispatch_', true) // Eindeutige Request ID
        ]
    ];
    
    // Payload zu JSON serialisieren
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // ==========================================================================
    // cURL REQUEST KONFIGURATION
    // ==========================================================================
    // cURL Handle initialisieren
    $ch = curl_init();
    
    // cURL Optionen konfigurieren für GitHub API Request
    curl_setopt_array($ch, [
        // === BASIS URL UND METHOD ===
        CURLOPT_URL => "https://api.github.com/repos/{$repo}/dispatches",
        CURLOPT_POST => true,                    // POST Request
        CURLOPT_POSTFIELDS => $jsonPayload,      // JSON Payload als Body
        
        // === RESPONSE HANDLING ===
        CURLOPT_RETURNTRANSFER => true,          // Response als String zurückgeben
        CURLOPT_FOLLOWLOCATION => false,         // Keine Redirects folgen (Security)
        CURLOPT_MAXREDIRS => 0,                  // Keine Redirects erlaubt
        
        // === HTTP HEADERS ===
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',              // GitHub API v3 Format
            'Authorization: Bearer ' . $githubToken,            // Token Authentication
            'User-Agent: Craft-CMS-Webhook/1.0 (PHP/' . PHP_VERSION . ')', // User Agent
            'Content-Type: application/json',                   // JSON Content
            'X-GitHub-Api-Version: 2022-11-28'                  // API Version Lock
        ],
        
        // === TIMEOUTS UND LIMITS ===
        CURLOPT_TIMEOUT => 30,                   // 30 Sekunden Gesamt-Timeout
        CURLOPT_CONNECTTIMEOUT => 10,            // 10 Sekunden Connection-Timeout
        
        // === SSL UND SECURITY ===
        CURLOPT_SSL_VERIFYPEER => true,          // SSL Zertifikat verifizieren
        CURLOPT_SSL_VERIFYHOST => 2,             // Hostname gegen Zertifikat prüfen
        CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Mindestens TLS 1.2
        
        // === DEBUGGING (nur bei Bedarf aktivieren) ===
        // CURLOPT_VERBOSE => true,              // Für Debugging
        // CURLOPT_STDERR => fopen('curl_debug.log', 'a'), // Debug Log
    ]);
    
    // ==========================================================================
    // API REQUEST AUSFÜHREN
    // ==========================================================================
    $startTime = microtime(true); // Performance Messung starten
    
    // cURL Request ausführen
    $response = curl_exec($ch);
    
    // Request Performance messen
    $executionTime = round((microtime(true) - $startTime) * 1000, 2); // in Millisekunden
    
    // Response Informationen sammeln
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $curlError = curl_error($ch);
    
    // cURL Handle schließen
    curl_close($ch);
    
    // ==========================================================================
    // CURL ERROR HANDLING
    // ==========================================================================
    if ($curlError) {
        $errorMsg = "cURL Error beim GitHub API Request: {$curlError}";
        error_log("[GITHUB API ERROR] {$errorMsg}");
        
        return [
            'success' => false,
            'error' => $errorMsg,
            'curl_error_code' => curl_errno($ch),
            'execution_time_ms' => $executionTime
        ];
    }
    
    // ==========================================================================
    // HTTP STATUS CODE AUSWERTUNG
    // ==========================================================================
    // GitHub Repository Dispatch API gibt HTTP 204 (No Content) bei Erfolg zurück
    if ($httpCode === 204) {
        // === ERFOLGREICHES REPOSITORY DISPATCH ===
        $successMsg = "GitHub Repository Dispatch erfolgreich ausgelöst";
        error_log("[GITHUB API SUCCESS] {$successMsg} - Event: {$eventType}, Repository: {$repo}");
        
        return [
            'success' => true,
            'data' => [
                'repository' => $repo,
                'event_type' => $eventType,
                'http_code' => $httpCode,
                'execution_time_ms' => $executionTime,
                'api_response_time' => $totalTime,
                'timestamp' => date('c')
            ]
        ];
    } else {
        // === GITHUB API FEHLER BEHANDLUNG ===
        
        // Response Body dekodieren für detaillierte Fehlermeldung
        $responseData = json_decode($response, true);
        $errorMessage = $responseData['message'] ?? 'Unbekannter GitHub API Fehler';
        
        // Spezifische Fehlerbehandlung basierend auf HTTP Status Code
        switch ($httpCode) {
            case 401:
                $error = "GitHub API Authentifizierung fehlgeschlagen - Token ungültig oder abgelaufen";
                break;
            case 403:
                $error = "GitHub API Zugriff verweigert - Token hat keine ausreichenden Berechtigungen";
                break;
            case 404:
                $error = "GitHub Repository '{$repo}' nicht gefunden oder nicht zugänglich";
                break;
            case 422:
                $error = "GitHub API Validierungsfehler: {$errorMessage}";
                break;
            default:
                $error = "GitHub API Fehler (HTTP {$httpCode}): {$errorMessage}";
        }
        
        // Detailliertes Error Logging
        error_log(sprintf(
            "[GITHUB API ERROR] %s - HTTP %d - Response: %s - Execution Time: %sms",
            $error,
            $httpCode,
            substr($response, 0, 500), // Erste 500 Zeichen der Response
            $executionTime
        ));
        
        return [
            'success' => false,
            'error' => $error,
            'http_code' => $httpCode,
            'github_response' => $responseData,
            'execution_time_ms' => $executionTime
        ];
    }
}