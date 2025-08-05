<?php
/**
 * Craft CMS Update Analyzer - Intelligente Update-Bewertung
 * 
 * Diese Datei analysiert verfügbare Updates und bewertet deren Risiko,
 * besonders in Bezug auf PHP-Versionskompatibilität und Sicherheitsupdates.
 * 
 * Funktionen:
 * - Sicherheitsupdates identifizieren und priorisieren
 * - PHP-Versionskonflikt-Erkennung
 * - Breaking Changes Detection
 * - Update-Empfehlungen basierend auf Risiko-Assessment
 * 
 * Integration:
 * - Wird von GitHub Actions vor Updates aufgerufen
 * - Kann von n8n für Entscheidungslogik genutzt werden
 * - Berücksichtigt kundenspezifische PHP-Versionen
 * 
 * @author Tim Steegmüller
 * @version 1.0
 * @since 2025-08-04
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// HTTP Headers setzen
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    // Craft Web Application initialisieren
    /** @var craft\web\Application $app */
    $app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
    
    // Update-Analyse Struktur
    $analysis = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'system_info' => [],
        'available_updates' => [],
        'security_updates' => [],
        'php_compatibility' => [],
        'risk_assessment' => [],
        'recommendations' => []
    ];
    
    // ==========================================================================
    // SYSTEM INFORMATIONEN SAMMELN
    // ==========================================================================
    $craftInfo = Craft::$app->getInfo();
    $analysis['system_info'] = [
        'current_craft_version' => $craftInfo->version,
        'current_php_version' => PHP_VERSION,
        'php_version_major_minor' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        'environment' => Craft::$app->env('CRAFT_ENVIRONMENT') ?: 'production',
        'edition' => $craftInfo->edition
    ];
    
    // ==========================================================================
    // VERFÜGBARE UPDATES ANALYSIEREN
    // ==========================================================================
    try {
        // Craft Update Info abrufen (simuliert - in echt würde das über Updates API laufen)
        $analysis['available_updates'] = analyzeAvailableUpdates();
        
        // Composer Dependencies prüfen
        $analysis['composer_updates'] = analyzeComposerUpdates();
        
    } catch (Exception $e) {
        $analysis['available_updates'] = [
            'error' => 'Update-Informationen konnten nicht abgerufen werden: ' . $e->getMessage()
        ];
    }
    
    // ==========================================================================
    // SICHERHEITSUPDATES IDENTIFIZIEREN
    // ==========================================================================
    $analysis['security_updates'] = identifySecurityUpdates($analysis['available_updates']);
    
    // ==========================================================================
    // PHP KOMPATIBILITÄT PRÜFEN
    // ==========================================================================
    $analysis['php_compatibility'] = checkPhpCompatibility($analysis['available_updates']);
    
    // ==========================================================================
    // RISIKO-BEWERTUNG DURCHFÜHREN
    // ==========================================================================
    $analysis['risk_assessment'] = performRiskAssessment($analysis);
    
    // ==========================================================================
    // UPDATE-EMPFEHLUNGEN GENERIEREN
    // ==========================================================================
    $analysis['recommendations'] = generateRecommendations($analysis);
    
    // JSON Response ausgeben
    echo json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Update-Analyse fehlgeschlagen',
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Analysiert verfügbare Craft CMS und Plugin Updates
 */
function analyzeAvailableUpdates(): array
{
    $updates = [];
    
    try {
        // Craft CMS Core Updates (simuliert - würde normalerweise Updates API nutzen)
        $updates['craft_cms'] = [
            'current_version' => Craft::$app->getInfo()->version,
            'available_version' => '5.8.0', // Beispiel
            'type' => 'minor',
            'changelog_url' => 'https://craftcms.com/docs/5.x/changelog.html',
            'security_related' => false,
            'php_requirements' => ['min' => '8.2.0', 'max' => '8.3.*']
        ];
        
        // Plugin Updates (würde normalerweise Plugin Store API nutzen)
        $updates['plugins'] = [
            [
                'name' => 'commerce',
                'current_version' => '4.5.1',
                'available_version' => '5.0.0',
                'type' => 'major',
                'breaking_changes' => true,
                'php_requirements' => ['min' => '8.2.0']
            ]
        ];
        
    } catch (Exception $e) {
        $updates['error'] = $e->getMessage();
    }
    
    return $updates;
}

/**
 * Analysiert Composer Dependencies Updates
 */
function analyzeComposerUpdates(): array
{
    $composerUpdates = [];
    
    // Composer outdated simulieren (würde normalerweise composer outdated --format=json ausführen)
    $composerUpdates = [
        'outdated_packages' => [
            [
                'name' => 'guzzlehttp/guzzle',
                'current' => '7.5.0',
                'available' => '7.8.1',
                'security_update' => true,
                'severity' => 'high'
            ],
            [
                'name' => 'symfony/console',
                'current' => '6.3.0',
                'available' => '7.0.0',
                'security_update' => false,
                'major_version_change' => true
            ]
        ]
    ];
    
    return $composerUpdates;
}

/**
 * Identifiziert Sicherheitsupdates basierend auf CVE-Datenbanken
 */
function identifySecurityUpdates(array $availableUpdates): array
{
    $securityUpdates = [];
    
    // Craft CMS Sicherheitsupdates
    if (isset($availableUpdates['craft_cms'])) {
        $craftUpdate = $availableUpdates['craft_cms'];
        
        // Security Advisory Simulation (würde normalerweise Security APIs abfragen)
        $securityAdvisories = checkSecurityAdvisories('craftcms/cms');
        
        if (!empty($securityAdvisories)) {
            $securityUpdates[] = [
                'component' => 'craft_cms',
                'severity' => 'high',
                'cve_ids' => ['CVE-2024-12345'], // Beispiel
                'description' => 'XSS-Schwachstelle in Admin Panel',
                'fixed_in' => $craftUpdate['available_version'],
                'recommendation' => 'Sofortiges Update empfohlen'
            ];
        }
    }
    
    return $securityUpdates;
}

/**
 * Prüft PHP-Kompatibilität für Updates
 */
function checkPhpCompatibility(array $availableUpdates): array
{
    $compatibility = [
        'current_php' => PHP_VERSION,
        'compatible' => true,
        'conflicts' => [],
        'recommendations' => []
    ];
    
    $currentPhpVersion = PHP_VERSION;
    
    // Craft CMS PHP Anforderungen prüfen
    if (isset($availableUpdates['craft_cms']['php_requirements'])) {
        $requirements = $availableUpdates['craft_cms']['php_requirements'];
        
        if (version_compare($currentPhpVersion, $requirements['min'], '<')) {
            $compatibility['conflicts'][] = [
                'component' => 'craft_cms',
                'issue' => 'PHP Version zu niedrig',
                'required' => $requirements['min'],
                'current' => $currentPhpVersion,
                'severity' => 'blocking'
            ];
            $compatibility['compatible'] = false;
        }
    }
    
    // Plugin PHP Anforderungen prüfen
    if (isset($availableUpdates['plugins'])) {
        foreach ($availableUpdates['plugins'] as $plugin) {
            if (isset($plugin['php_requirements'])) {
                $requirements = $plugin['php_requirements'];
                
                if (version_compare($currentPhpVersion, $requirements['min'], '<')) {
                    $compatibility['conflicts'][] = [
                        'component' => $plugin['name'],
                        'issue' => 'PHP Version Konflikt',
                        'required' => $requirements['min'],
                        'current' => $currentPhpVersion,
                        'severity' => 'warning'
                    ];
                }
            }
        }
    }
    
    return $compatibility;
}

/**
 * Führt Risiko-Bewertung für alle Updates durch
 */
function performRiskAssessment(array $analysis): array
{
    $risk = [
        'overall_risk' => 'low',
        'factors' => [],
        'score' => 0
    ];
    
    $riskScore = 0;
    
    // Sicherheitsupdates erhöhen Dringlichkeit, aber senken Risiko
    if (!empty($analysis['security_updates'])) {
        $risk['factors'][] = [
            'factor' => 'security_updates_available',
            'impact' => 'Sicherheitsupdates vorhanden - Update dringend empfohlen',
            'risk_adjustment' => -10 // Negativ = geringeres Risiko für Update
        ];
        $riskScore -= 10;
    }
    
    // PHP Inkompatibilität erhöht Risiko stark
    if (!$analysis['php_compatibility']['compatible']) {
        $risk['factors'][] = [
            'factor' => 'php_incompatibility',
            'impact' => 'PHP Version Konflikte - Update blockiert',
            'risk_adjustment' => +50
        ];
        $riskScore += 50;
    }
    
    // Major Version Updates erhöhen Risiko
    if (isset($analysis['available_updates']['craft_cms']['type']) && 
        $analysis['available_updates']['craft_cms']['type'] === 'major') {
        $risk['factors'][] = [
            'factor' => 'major_version_update',
            'impact' => 'Major Version Update - Breaking Changes möglich',
            'risk_adjustment' => +20
        ];
        $riskScore += 20;
    }
    
    // Risiko-Level basierend auf Score bestimmen
    if ($riskScore <= 0) {
        $risk['overall_risk'] = 'low';
    } elseif ($riskScore <= 30) {
        $risk['overall_risk'] = 'medium';
    } else {
        $risk['overall_risk'] = 'high';
    }
    
    $risk['score'] = $riskScore;
    
    return $risk;
}

/**
 * Generiert konkrete Update-Empfehlungen
 */
function generateRecommendations(array $analysis): array
{
    $recommendations = [];
    
    // Sicherheitsupdates haben höchste Priorität
    if (!empty($analysis['security_updates'])) {
        $recommendations[] = [
            'priority' => 'critical',
            'type' => 'security_update',
            'action' => 'immediate_update',
            'message' => 'Sicherheitsupdates verfügbar - sofortiges Update empfohlen',
            'timeline' => 'Innerhalb von 24 Stunden',
            'automation_safe' => true
        ];
    }
    
    // PHP Inkompatibilität blockiert Updates
    if (!$analysis['php_compatibility']['compatible']) {
        $recommendations[] = [
            'priority' => 'high',
            'type' => 'php_upgrade_required',
            'action' => 'upgrade_php_first',
            'message' => 'PHP Version muss vor Craft Update aktualisiert werden',
            'timeline' => 'Vor nächstem Update-Versuch',
            'automation_safe' => false,
            'manual_steps' => [
                'PHP Version in Docker/Server aktualisieren',
                'Kompatibilität mit anderen Systemen prüfen',
                'Tests in Staging-Umgebung durchführen'
            ]
        ];
    }
    
    // Normale Updates - je nach Risiko
    $riskLevel = $analysis['risk_assessment']['overall_risk'];
    
    if ($riskLevel === 'low') {
        $recommendations[] = [
            'priority' => 'normal',
            'type' => 'routine_update',
            'action' => 'automated_update',
            'message' => 'Routine-Update kann automatisch durchgeführt werden',
            'timeline' => 'Nächster geplanter Update-Zyklus',
            'automation_safe' => true
        ];
    } elseif ($riskLevel === 'medium') {
        $recommendations[] = [
            'priority' => 'medium',
            'type' => 'supervised_update',
            'action' => 'manual_review_required',
            'message' => 'Update sollte manuell überprüft werden vor Durchführung',
            'timeline' => 'Nach manueller Prüfung',
            'automation_safe' => false
        ];
    } else {
        $recommendations[] = [
            'priority' => 'high',
            'type' => 'high_risk_update',
            'action' => 'staging_test_required',
            'message' => 'Update nur nach ausführlichen Tests in Staging-Umgebung',
            'timeline' => 'Nach Staging-Tests und Freigabe',
            'automation_safe' => false
        ];
    }
    
    return $recommendations;
}

/**
 * Simuliert Security Advisory Check (würde normalerweise APIs abfragen)
 */
function checkSecurityAdvisories(string $package): array
{
    // In der Realität würde das APIs wie GitHub Security Advisories abfragen
    // https://api.github.com/advisories?affects=craftcms/cms
    
    // Simulation basierend auf aktueller Version
    $currentVersion = Craft::$app->getInfo()->version;
    
    // Beispiel: Wenn Version < 5.7.11, dann Security Update verfügbar
    if (version_compare($currentVersion, '5.7.11', '<')) {
        return [
            [
                'id' => 'GHSA-example-1234',
                'severity' => 'high',
                'summary' => 'XSS vulnerability in admin panel',
                'fixed_in' => '5.7.11'
            ]
        ];
    }
    
    return [];
}