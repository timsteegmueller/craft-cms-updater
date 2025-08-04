# 📚 Vollständige Dokumentation - Tim's Craft CMS Auto-Update System

## 🎯 Systemübersicht

Dieses System ermöglicht die vollautomatische Aktualisierung von Craft CMS über eine n8n → GitHub Actions Integration. Das Setup besteht aus drei Hauptkomponenten:

1. **n8n Workflow** - Scheduler und Webhook-Trigger
2. **PHP Endpoints** - Health Check und Webhook Receiver
3. **GitHub Actions** - Automatisierte Update-Pipeline

## 🏗️ Architektur-Diagramm

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   n8n       │    │   Craft     │    │   GitHub    │    │   Craft     │
│  Scheduler  │───▶│  Webhook    │───▶│   Actions   │───▶│   Update    │
│             │    │  Endpoint   │    │  Pipeline   │    │  Complete   │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
      │                    │                    │                    │
      ▼                    ▼                    ▼                    ▼
   Montags              POST zu               Repository          Backup +
   08:00 Uhr           webhook.php            Dispatch            Update
```

## 🔧 Komponenten-Details

### 1. n8n Workflow ("Tim PHP Updater")

**Zweck:** Startet automatisch oder manuell Craft CMS Updates

**Trigger-Methoden:**
- **Schedule Trigger**: Jeden Montag automatisch
- **Webhook Trigger**: Manueller Aufruf über HTTP POST

**Workflow-Schritte:**
1. Trigger erkennt Auslöser (Zeit oder HTTP)
2. HTTP Request zu GitHub API
3. Repository Dispatch wird ausgelöst
4. GitHub Actions startet automatisch

**Konfiguration:**
```json
{
  "webhook_url": "https://deine-domain.com/craft/web/webhook.php",
  "method": "POST",
  "github_repo": "timsteegmueller/craft-projekte",
  "event_type": "run-backup-und-update"
}
```

### 2. PHP Health Check Endpoint

**Datei:** `craft/web/health.php`
**Zweck:** Systemstatus überwachen und API für Monitoring bereitstellen

**Funktionen:**
- Datenbankverbindung testen
- Craft CMS Status prüfen
- Storage-Berechtigungen überprüfen
- Environment-Konfiguration anzeigen

**Response Format:**
```json
{
  "status": "ok|warning|error",
  "timestamp": "2024-08-04T10:00:00+00:00",
  "checks": {
    "database": {"status": "ok", "message": "..."},
    "craft": {"status": "ok", "version": "5.7.10"},
    "storage": {"status": "ok", "message": "..."},
    "environment": {"status": "ok", "environment": "dev"}
  }
}
```

### 3. PHP Webhook Endpoint

**Datei:** `craft/web/webhook.php`
**Zweck:** n8n Requests empfangen und GitHub Actions auslösen

**Sicherheitsfeatures:**
- CORS-Header für n8n Kompatibilität
- HTTP Method Validation (nur POST)
- Request Logging für Debugging
- Token-basierte GitHub Authentifizierung

**Request Format:**
```json
{
  "action": "update",
  "source": "n8n_scheduler|manual|webhook"
}
```

### 4. GitHub Actions Workflow

**Datei:** `.github/workflows/update.yml`
**Zweck:** Automatisierte Craft CMS Updates mit Backup

**Service Dependencies:**
- MariaDB 10.5 Container für Datenbank-Tests
- PHP 8.2 mit erforderlichen Extensions

**Workflow-Phasen:**

#### Phase 1: Environment Setup
- Repository checkout
- PHP und Composer Installation
- MariaDB Service Start

#### Phase 2: Craft Vorbereitung
- Composer Dependencies Installation
- .env Datei Generierung (falls nicht vorhanden)
- Security Keys automatisch generieren

#### Phase 3: System Checks
- Health Check Endpoint aufrufen
- Datenbankverbindung testen
- Craft System Status prüfen

#### Phase 4: Backup Erstellung
- Backup-Verzeichnis erstellen
- Datenbank-Dump via Craft CLI
- Backup-Datei in storage/backups/ speichern

#### Phase 5: Update Durchführung
- Craft CMS Update ausführen
- Composer Dependencies aktualisieren
- Cache leeren und regenerieren

#### Phase 6: Nachbearbeitung
- Geänderte Dateien automatisch committen
- Update-Summary generieren
- Workflow-Status melden

## 🔒 Sicherheitskonzept

### Authentication & Authorization
- **GitHub PAT**: Personal Access Token für Repository Dispatch
- **HTTPS**: Alle API-Calls über verschlüsselte Verbindungen
- **Request Validation**: Input-Sanitization in PHP Endpoints

### Logging & Monitoring
- **PHP Error Logs**: Webhook und Health Check Aktivitäten
- **GitHub Actions Logs**: Detaillierte Workflow-Protokolle
- **Craft Logs**: System- und Update-Ereignisse

### Backup-Strategie
- **Automatische Backups**: Vor jedem Update
- **Versionierte Backups**: Zeitstempel in Dateinamen
- **Rollback-Fähigkeit**: Manuelle Wiederherstellung möglich

## 📊 Monitoring & Alerting

### Health Check Monitoring
```bash
# Grundlegende Verfügbarkeit
curl -f https://deine-domain.com/craft/web/health.php

# Detaillierte Statusprüfung
curl -s https://deine-domain.com/craft/web/health.php | jq '.status'
```

### GitHub Actions Monitoring
- **Workflow Status**: Repository → Actions Tab
- **Email Notifications**: Bei Fehlschlägen automatisch
- **Badge Integration**: Möglich für README.md

### Log-Dateien Überwachung
```bash
# Craft System Logs
tail -f craft/storage/logs/web-$(date +%Y-%m-%d).log

# PHP Error Logs (Server abhängig)
tail -f /var/log/php/php_errors.log
```

## 🛠️ Wartung & Updates

### Regelmäßige Wartungsaufgaben

**Wöchentlich:**
- Backup-Verzeichnis auf Größe prüfen
- Log-Dateien rotieren/archivieren
- Health Check Response-Zeiten überwachen

**Monatlich:**
- GitHub PAT Ablaufdatum überprüfen
- n8n Workflow-Performance analysieren
- Backup-Restore Prozess testen

**Vierteljährlich:**
- Sicherheits-Audit der API Endpoints
- Dokumentation auf Aktualität prüfen
- Disaster Recovery Plan testen

### Update-Verfahren für das System selbst

1. **PHP Endpoints aktualisieren**:
   - Code-Änderungen testen auf Staging
   - Health Check vor Deployment
   - Rollback-Plan bereithalten

2. **GitHub Actions anpassen**:
   - Workflow in Feature-Branch testen
   - Pull Request für Code Review
   - Staging-Environment für Tests nutzen

3. **n8n Workflow modifizieren**:
   - Backup des bestehenden Workflows
   - Stufenweise Ausrollung der Änderungen
   - Monitoring der ersten Ausführungen

## 🚨 Troubleshooting Guide

### Häufige Probleme und Lösungen

#### Problem: GitHub Action startet nicht
**Symptome:**
- Webhook meldet Erfolg, aber keine Action
- Repository Dispatch API gibt Fehler zurück

**Diagnose:**
```bash
# GitHub PAT Token testen
curl -H "Authorization: Bearer $GITHUB_PAT" \
     https://api.github.com/user

# Repository Dispatch manuell senden
curl -H "Authorization: Bearer $GITHUB_PAT" \
     -H "Accept: application/vnd.github+json" \
     -X POST \
     https://api.github.com/repos/timsteegmueller/craft-projekte/dispatches \
     -d '{"event_type":"run-backup-und-update"}'
```

**Lösungen:**
1. GitHub PAT Token erneuern
2. Repository Permissions überprüfen
3. API Rate Limits prüfen

#### Problem: Craft Update schlägt fehl
**Symptome:**
- GitHub Action läuft durch, aber Update nicht erfolgreich
- Composer Dependency Conflicts

**Diagnose:**
```bash
# Craft Update manuell testen
cd craft
php craft update/info
php craft update all --dry-run

# Composer Probleme analysieren
composer diagnose
composer outdated
```

**Lösungen:**
1. Composer Cache leeren: `composer clear-cache`
2. Dependencies manuell auflösen
3. Craft Dokumentation für Breaking Changes prüfen

#### Problem: Health Check schlägt fehl
**Symptome:**
- HTTP 503 Fehler
- JSON Response mit error status

**Diagnose:**
```bash
# PHP Error Logs prüfen
tail -f /var/log/php/php_errors.log

# Craft Bootstrap testen
cd craft
php -r "require 'bootstrap.php'; echo 'Bootstrap OK';"
```

**Lösungen:**
1. Dateiberechtigungen für storage/ prüfen
2. .env Konfiguration validieren
3. Datenbankverbindung testen

## 📋 Checklisten

### Pre-Deployment Checklist
- [ ] GitHub PAT Token konfiguriert und gültig
- [ ] n8n Workflow importiert und aktiviert
- [ ] Health Check Endpoint erreichbar
- [ ] Webhook Endpoint funktionsfähig
- [ ] Backup-Verzeichnis beschreibbar
- [ ] .env Datei vollständig konfiguriert
- [ ] Datenbankverbindung funktioniert
- [ ] HTTPS/SSL Zertifikat gültig

### Post-Update Checklist
- [ ] Health Check Status OK
- [ ] Craft Admin Panel erreichbar
- [ ] Frontend funktioniert korrekt
- [ ] Backup erfolgreich erstellt
- [ ] Logs auf Fehler überprüft
- [ ] GitHub Action erfolgreich abgeschlossen
- [ ] n8n Workflow ohne Fehler

### Monthly Maintenance Checklist
- [ ] Backup-Verzeichnis Größe überprüfen
- [ ] Alte Backups archivieren/löschen
- [ ] Log-Dateien rotieren
- [ ] GitHub PAT Ablaufdatum prüfen
- [ ] n8n Credentials aktualisieren
- [ ] Health Check Performance messen
- [ ] Dokumentation auf Aktualität prüfen

## 📈 Performance Optimierung

### Backup-Performance
- Große Datenbanken: Incremental Backups erwägen
- Compression: gzip für Backup-Dateien aktivieren
- Parallel Processing: Mehrere Backup-Streams

### GitHub Actions Performance
- Caching: Composer Dependencies cachen
- Parallel Jobs: Unabhängige Steps parallelisieren
- Resource Limits: Angemessene timeout-Werte

### PHP Endpoint Performance
- OPcache: PHP Bytecode Caching aktivieren
- Response Caching: Health Check Results cachen
- Database Connection Pooling: Bei hoher Last

## 🔗 Externe Ressourcen

### Dokumentation Links
- [Craft CMS Dokumentation](https://craftcms.com/docs)
- [GitHub Actions Dokumentation](https://docs.github.com/en/actions)
- [n8n Dokumentation](https://docs.n8n.io/)
- [PHP cURL Dokumentation](https://www.php.net/manual/en/book.curl.php)

### Tools & Utilities
- [GitHub CLI](https://cli.github.com/) - Command Line Interface
- [jq](https://stedolan.github.io/jq/) - JSON Processor für Debugging
- [Postman](https://www.postman.com/) - API Testing
- [ngrok](https://ngrok.com/) - Lokale Entwicklung mit HTTPS

---

*Diese Dokumentation wurde am 04. August 2024 erstellt und sollte regelmäßig aktualisiert werden.*