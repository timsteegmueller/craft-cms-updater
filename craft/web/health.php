<?php
/**
 * Craft CMS Health Check Endpoint - Systemstatus API
 * 
 * Diese Datei stellt einen HTTP-Endpunkt bereit, der den aktuellen Status 
 * der Craft CMS Installation überprüft und als JSON-Response zurückgibt.
 * 
 * Verwendung:
 * - n8n Workflows für Monitoring vor Updates
 * - GitHub Actions für Pre-Update Validierung  
 * - Externe Monitoring-Systeme für Verfügbarkeitsprüfung
 * - Manuelle Systemdiagnose über Browser oder cURL
 * 
 * Response Format: JSON mit status, timestamp und detaillierten checks
 * HTTP Status Codes: 200 (OK/Warning), 503 (Service Unavailable)
 * 
 * @author Tim Steegmüller  
 * @version 1.0
 * @since 2025-08-04
 */
declare(strict_types=1);

// Craft CMS Bootstrap laden - Erforderlich für alle Craft-spezifischen Funktionen
// Diese Datei initialisiert die grundlegenden Konstanten und lädt den Autoloader
require_once __DIR__ . '/../bootstrap.php';

// HTTP Content-Type Header auf JSON setzen für API-konforme Responses
// Ermöglicht automatisches Parsing in Client-Anwendungen wie n8n
header('Content-Type: application/json; charset=utf-8');

// Security Header hinzufügen um XSS-Angriffe zu verhindern
header('X-Content-Type-Options: nosniff');

try {
    // Craft Web Application initialisieren 
    // Dies startet das komplette Craft CMS System für Statusabfragen
    /** @var craft\web\Application $app */
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
    
    // Basis-Struktur für Health Check Response definieren
    // Status wird während der Checks entsprechend angepasst (ok -> warning -> error)
    $health = [
        'status' => 'ok',                    // Gesamt-Status: ok, warning, error
        'timestamp' => date('c'),            // ISO 8601 Zeitstempel für Tracking
        'server_time' => date('Y-m-d H:i:s'), // Lesbare Serverzeit
        'checks' => []                       // Array für individuelle Check-Ergebnisse
    ];

    // ==========================================================================
    // CHECK 1: DATENBANKVERBINDUNG TESTEN
    // ==========================================================================
    // Kritischer Check - Ohne Datenbank kann Craft CMS nicht funktionieren
    try {
        $db = Craft::$app->getDb();        // Database Component von Craft holen
        $db->open();                       // Explizite Verbindung öffnen
        
        // Zusätzlich eine einfache Query ausführen um sicherzustellen dass DB funktioniert
        $tableSchema = $db->getTableSchema('{{%info}}'); // Craft Info Tabelle prüfen
        
        $health['checks']['database'] = [
            'status' => 'ok',
            'message' => 'Datenbankverbindung erfolgreich hergestellt',
            'driver' => $db->driverName,     // MySQL, PostgreSQL, etc.
            'server_version' => $db->serverVersion ?? 'unknown'
        ];
    } catch (Exception $e) {
        // Datenbankfehler sind kritisch - System Status auf error setzen
        $health['checks']['database'] = [
            'status' => 'error',
            'message' => 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage(),
            'error_code' => $e->getCode()
        ];
        $health['status'] = 'error';       // Gesamt-Status degradieren
    }

    // ==========================================================================
    // CHECK 2: CRAFT CMS SYSTEM STATUS
    // ==========================================================================
    // Überprüft ob Craft CMS korrekt initialisiert ist und grundlegende Infos abrufen kann
    try {
        $info = Craft::$app->getInfo();    // System-Informationen abrufen
        $plugins = Craft::$app->getPlugins()->getAllPlugins(); // Installierte Plugins
        
        $health['checks']['craft'] = [
            'status' => 'ok',
            'message' => 'Craft CMS läuft normal',
            'version' => $info->version,      // z.B. "5.7.10"
            'edition' => $info->edition,      // Solo, Pro, etc.
            'schema_version' => $info->schemaVersion, // DB Schema Version
            'plugins_count' => count($plugins), // Anzahl installierter Plugins
            'maintenance_mode' => Craft::$app->getIsInMaintenanceMode()
        ];
        
        // Warning wenn Maintenance Mode aktiv ist
        if (Craft::$app->getIsInMaintenanceMode()) {
            $health['checks']['craft']['status'] = 'warning';
            $health['checks']['craft']['message'] = 'Craft CMS im Wartungsmodus';
            if ($health['status'] === 'ok') {
                $health['status'] = 'warning';
            }
        }
        
    } catch (Exception $e) {
        // Craft System Fehler sind kritisch
        $health['checks']['craft'] = [
            'status' => 'error',
            'message' => 'Craft System Check fehlgeschlagen: ' . $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ];
        $health['status'] = 'error';
    }

    // ==========================================================================
    // CHECK 3: STORAGE VERZEICHNIS BERECHTIGUNGEN
    // ==========================================================================
    // Essentiell für Backups, Logs, Cache und Asset-Uploads
    $storageDir = CRAFT_BASE_PATH . '/storage';
    $backupDir = $storageDir . '/backups';
    $logDir = $storageDir . '/logs';
    
    // Storage Hauptverzeichnis prüfen
    if (is_dir($storageDir) && is_writable($storageDir)) {
        $storageStatus = 'ok';
        $storageMessage = 'Storage-Verzeichnis ist beschreibbar';
        
        // Backup-Verzeichnis spezifisch prüfen (wichtig für automatische Backups)
        if (!is_dir($backupDir)) {
            // Verzeichnis erstellen falls es nicht existiert
            if (mkdir($backupDir, 0755, true)) {
                $backupMessage = 'Backup-Verzeichnis wurde erstellt';
            } else {
                $storageStatus = 'warning';
                $backupMessage = 'Backup-Verzeichnis konnte nicht erstellt werden';
            }
        } else {
            $backupMessage = 'Backup-Verzeichnis existiert und ist beschreibbar';
        }
        
        $health['checks']['storage'] = [
            'status' => $storageStatus,
            'message' => $storageMessage,
            'storage_path' => $storageDir,
            'storage_writable' => is_writable($storageDir),
            'backup_dir_exists' => is_dir($backupDir),
            'backup_dir_writable' => is_dir($backupDir) && is_writable($backupDir),
            'log_dir_exists' => is_dir($logDir),
            'disk_free_space' => disk_free_space($storageDir) ? 
                number_format(disk_free_space($storageDir) / 1024 / 1024, 2) . ' MB' : 'unknown'
        ];
        
    } else {
        // Storage nicht beschreibbar ist ein kritisches Problem
        $health['checks']['storage'] = [
            'status' => 'error',
            'message' => 'Storage-Verzeichnis nicht beschreibbar oder nicht vorhanden',
            'storage_path' => $storageDir,
            'storage_exists' => is_dir($storageDir),
            'storage_writable' => is_writable($storageDir)
        ];
        $health['status'] = 'error';
    }

    // ==========================================================================
    // CHECK 4: ENVIRONMENT & KONFIGURATION
    // ==========================================================================
    // Überprüft wichtige Umgebungsvariablen und Konfigurationseinstellungen
    $generalConfig = Craft::$app->getConfig()->getGeneral();
    
    $health['checks']['environment'] = [
        'status' => 'ok',
        'message' => 'Environment-Konfiguration geladen',
        'environment' => Craft::$app->env('CRAFT_ENVIRONMENT') ?: 'production', // Default fallback
        'dev_mode' => $generalConfig->devMode,      // Development Mode Status
        'debug_mode' => YII_DEBUG,                  // Debug Mode (für detaillierte Fehlermeldungen)
        'headless_mode' => $generalConfig->headlessMode, // API-only Modus
        'php_version' => PHP_VERSION,               // PHP Version für Kompatibilitätsprüfung
        'memory_limit' => ini_get('memory_limit'),  // PHP Memory Limit
        'max_execution_time' => ini_get('max_execution_time'), // Script Timeout
        'security_key_set' => !empty(Craft::$app->getConfig()->getGeneral()->securityKey)
    ];
    
    // Warning wenn wichtige Sicherheitseinstellungen fehlen
    if (empty(Craft::$app->getConfig()->getGeneral()->securityKey)) {
        $health['checks']['environment']['status'] = 'warning';
        $health['checks']['environment']['message'] = 'Security Key nicht gesetzt';
        if ($health['status'] === 'ok') {
            $health['status'] = 'warning';
        }
    }

    // ==========================================================================
    // CHECK 5: ZUSÄTZLICHE SYSTEM-CHECKS
    // ==========================================================================
    // Erweiterte Checks für bessere Diagnostik
    $health['checks']['system'] = [
        'status' => 'ok',
        'message' => 'System-Checks erfolgreich',
        'timezone' => date_default_timezone_get(),   // Server Zeitzone
        'locale' => Craft::$app->locale->id,         // Craft Locale Setting
        'temp_dir_writable' => is_writable(sys_get_temp_dir()), // Temp Verzeichnis
        'session_active' => session_status() === PHP_SESSION_ACTIVE, // Session Status
        'extensions' => [
            'gd' => extension_loaded('gd'),          // Für Bildverarbeitung
            'imagick' => extension_loaded('imagick'), // Alternative Bildverarbeitung
            'zip' => extension_loaded('zip'),        // Für Backups und Plugin-Installation
            'curl' => extension_loaded('curl'),      // Für HTTP Requests
            'mbstring' => extension_loaded('mbstring') // Für Multibyte String Handling
        ]
    ];

    // ==========================================================================
    // HTTP STATUS CODE BASIEREND AUF ERGEBNIS SETZEN
    // ==========================================================================
    // Korrekte HTTP Status Codes ermöglichen automatisiertes Monitoring
    if ($health['status'] === 'error') {
        http_response_code(503);    // Service Unavailable - Kritische Fehler
    } elseif ($health['status'] === 'warning') {
        http_response_code(200);    // OK - System läuft, aber mit Warnungen
    } else {
        http_response_code(200);    // OK - Alles funktioniert einwandfrei
    }

    // JSON Response mit Pretty Print für bessere Lesbarkeit ausgeben
    echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // ==========================================================================
    // GLOBALER EXCEPTION HANDLER
    // ==========================================================================
    // Fängt alle unerwarteten Fehler ab und gibt strukturierte Fehlerantwort zurück
    
    // Fehler in PHP Error Log schreiben für Server-seitige Diagnose
    error_log(sprintf(
        "[CRAFT HEALTH CHECK ERROR] %s - %s in %s:%d",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    
    // HTTP 503 Service Unavailable für kritische Systemfehler
    http_response_code(503);
    
    // Strukturierte Fehlerantwort ausgeben
    echo json_encode([
        'status' => 'error',
        'timestamp' => date('c'),
        'message' => 'Kritischer Systemfehler beim Health Check',
        'error' => $e->getMessage(),
        'error_file' => basename($e->getFile()), // Nur Dateiname aus Sicherheitsgründen
        'error_line' => $e->getLine(),
        'checks' => []  // Leeres Array da keine Checks durchgeführt werden konnten
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}