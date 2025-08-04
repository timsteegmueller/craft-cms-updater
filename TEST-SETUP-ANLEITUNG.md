# 🚀 TEST-SETUP ANLEITUNG - Craft CMS Auto-Update System

## 🎯 Was du testen wirst

Ein **Production-Ready CI/CD-System** für automatishe Craft CMS Updates mit:
- ✅ **Intelligente Update-Analyse** (Sicherheitsupdates, PHP-Konflikte)
- ✅ **Automatische Pull Requests** mit detaillierter Review-Info
- ✅ **E-Mail Benachrichtigungen** mit Update-Zusammenfassung  
- ✅ **Sichere Backups** vor jedem Update
- ✅ **Website-Tests** nach Updates
- ✅ **Modulare Konfiguration** für verschiedene Projekte

---

## 📋 SETUP CHECKLISTE

### 1. GitHub Repository Setup

**Repository:** `https://github.com/timsteegmueller/craft-test-repo/`

#### 🔑 GitHub Secrets konfigurieren
```bash
# In GitHub Repository → Settings → Secrets and Variables → Actions
GITHUB_PAT = dein_github_personal_access_token
```

**GitHub PAT Berechtigung benötigt:**
- `repo` (Full control of repositories)
- `workflow` (Update GitHub Action workflows)

#### 📁 Dateien ins Repository pushen
```bash
# Von craft-test-repo in dein GitHub Repo
git add .github/workflows/update.yml
git add craft/web/health.php
git add craft/web/webhook.php
git add craft/web/update-analyzer.php
git commit -m "🤖 Craft CMS Auto-Update System Setup"
git push origin main
```

### 2. n8n Setup

#### 📧 SMTP Konfiguration (für E-Mails)
```bash
# In n8n → Credentials → Add SMTP
Host: dein-smtp-server.de
Port: 587
User: craft-updates@farbcode.de
Password: dein-smtp-passwort
```

#### 🔄 Workflow importieren
1. n8n öffnen → Import
2. Datei: `n8n-erweitert-mit-email.json` importieren
3. Credentials zuweisen:
   - GitHub API (GITHUB_PAT)
   - SMTP Account

#### 🌐 Webhook URL konfigurieren
```
https://deine-craft-domain.com/craft/web/webhook.php
```

### 3. Craft CMS Setup

#### 📝 Environment Variables setzen
```bash
# In .env Datei
GITHUB_PAT=dein_github_personal_access_token
GITHUB_TOKEN=dein_github_personal_access_token
CRAFT_ENVIRONMENT=dev
CRAFT_DEV_MODE=true
```

#### 🐳 Docker Environment (falls verwendet)
```bash
# Sicherstellen dass Container läuft
docker-compose up -d craftdb

# Oder für Test-Setup
docker-compose -f craft-test-repo/docker-compose.yml up -d
```

---

## 🧪 SYSTEM TESTEN

### Test 1: Health Check Endpoint
```bash
# Basis-Test
curl https://deine-domain.com/craft/web/health.php

# Erwarteter Response: JSON mit status: "ok"
```

### Test 2: Update Analyser
```bash
# Intelligente Update-Analyse testen
curl https://deine-domain.com/craft/web/update-analyzer.php

# Sollte Risiko-Bewertung und PHP-Kompatibilität zeign
```

### Test 3: Webhook Trigger (manuell)
```bash
# n8n Webhook manuell auslösen
curl -X POST https://deine-domain.com/craft/web/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"action":"update","source":"manual_test"}'

# Sollte GitHub Actions Workflow starten
```

### Test 4: GitHub Actions (manuell)
1. GitHub Repository → Actions
2. "Tim's Automatischer Craft CMS Update" auswählen  
3. "Run workflow" klicken
4. Logs in Echtzeit verfolgen

### Test 5: n8n Workflow (komplett)
1. n8n → Workflows → "Tim PHP Updater mit E-Mail"
2. Manuell triggern oder Schedule aktivieren
3. E-Mail Empfang prüfen

---

## 🔧 KONFIGURATION ANPASSEN

### Sicherheitsupdates früher triggern
```javascript
// In n8n Schedule Trigger ändern
{
  "rule": {
    "interval": [
      {
        "field": "days",      // Täglich statt wöchentlich
        "triggerAtHour": 6,   // Um 6:00 Uhr
        "triggerAtMinute": 0
      }
    ]
  }
}
```

### E-Mail Empfänger anpassen
```html
<!-- In n8n E-Mail Node -->
<toEmail>tim@farbcode.de,team@farbcode.de</toEmail>
```

### Update-URLs konfigurieren
```bash
# In GitHub Workflow (update.yml) TEST_URLS anpassen:
TEST_URLS=(
  "https://deine-live-domain.com/"
  "https://deine-live-domain.com/health.php"
  "https://deine-live-domain.com/admin"  # Falls öffentlich
)
```

---

## 🚨 TROUBLESHOOTING

### Problem: GitHub Action startet nicht
```bash
# 1. GitHub PAT prüfen
curl -H "Authorization: Bearer $GITHUB_PAT" https://api.github.com/user

# 2. Repository Dispatch manuell testen  
curl -H "Authorization: Bearer $GITHUB_PAT" \
     -H "Accept: application/vnd.github+json" \
     -X POST \
     https://api.github.com/repos/timsteegmueller/craft-test-repo/dispatches \
     -d '{"event_type":"run-backup-und-update"}'
```

### Problem: PHP-Versionskonflikt
```bash
# System wird automatisch erkennen und Update stoppen
# Lösung: PHP Version in Docker/Server aktualisieren

# Docker: In Dockerfile PHP Version ändern
FROM php:8.3-fpm  # statt 8.2

# Dann Container neu bauen
docker-compose build --no-cache
```

### Problem: E-Mails kommen nicht an
```bash
# 1. SMTP Credentials in n8n prüfen
# 2. Test-E-Mail senden
# 3. Spam-Ordner prüfen
# 4. SMTP Server Logs checken
```

### Problem: Health Check fehlschlägt
```bash
# 1. Craft Bootstrap prüfen
cd craft && php -r "require 'bootstrap.php'; echo 'OK';"

# 2. Dateiberechtigungen prüfen
chmod -R 755 craft/storage/
chown -R www-data:www-data craft/storage/

# 3. Datenbank-Verbindung testen
cd craft && php craft db/backup test-backup.sql
```

---

## 📊 MONITORING & WARTUNG

### Wöchentliche Checks
- [ ] Backup-Verzeichnis Größe prüfen (`craft/storage/backups/`)
- [ ] GitHub Actions Logs durchsehen
- [ ] E-Mail Delivery Status prüfen
- [ ] Health Check Response-Zeiten messen

### Monatliche Wartung
- [ ] GitHub PAT Ablaufdatum prüfen (GitHub → Settings → Developer Settings)
- [ ] n8n Workflow Performance analysieren
- [ ] SMTP Credentials erneuern falls nötig
- [ ] Update-Logs archivieren

### Logs überwachen
```bash
# GitHub Actions Logs
# → GitHub Repository → Actions → Workflow Run

# Craft System Logs  
tail -f craft/storage/logs/web-$(date +%Y-%m-%d).log

# Server PHP Error Logs (Server-abhängig)
tail -f /var/log/php/php_errors.log
```

---

## 🎉 ERWEITERTE FEATURES

### ChatGPT API Integration (Zukunft)
```javascript
// n8n Node für AI-Zusammenfassung
{
  "name": "🤖 AI Update Summary",
  "type": "n8n-nodes-base.httpRequest",
  "parameters": {
    "url": "https://api.openai.com/v1/chat/completions",
    "headers": {
      "Authorization": "Bearer {{ $env.OPENAI_API_KEY }}"
    },
    "body": {
      "model": "gpt-4",
      "messages": [
        {
          "role": "system", 
          "content": "Fasse Craft CMS Update-Ergebnisse zusammen"
        },
        {
          "role": "user",
          "content": "{{ $json.workflow_results }}"
        }
      ]
    }
  }
}
```

### Multi-Projekt Support
```yaml
# In GitHub Workflow - Matrix Strategy
strategy:
  matrix:
    craft_instance: 
      - { name: "client-a", path: "craft-client-a", url: "https://client-a.com" }
      - { name: "client-b", path: "craft-client-b", url: "https://client-b.com" }
      - { name: "internal", path: "craft", url: "https://internal.farbcode.de" }
```

---

## ✅ SUCCESS CRITERIA

Das System funktioniert erfolgreich wenn:

1. **🔄 Automatische Updates laufen** - Wöchentlich ohne Eingriff
2. **📧 E-Mails werden versendet** - Mit detaillierter Zusammenfassung  
3. **📤 PRs werden erstellt** - Mit Review-Checkliste
4. **🛡️ Sicherheitsupdates werden priorisiert** - Früher als normal
5. **⚠️ PHP-Konflikte werden erkannt** - Update wird gestoppt
6. **💾 Backups werden erstellt** - Vor jedem Update
7. **🌐 Website-Tests funktionieren** - HTTP 200 Checks
8. **📊 Monitoring läuft** - Health Checks und Logs

---

**🎯 Ready for Production!** 

Das System ist jetzt auf Enterprise-Level und kann sicher für Kundenprojekte eingesezt werden.

Bei Fragen oder Problemn: Logs checken und die entsprechenden Troubleshooting-Schritte befolgen!