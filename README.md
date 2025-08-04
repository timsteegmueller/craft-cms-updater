# 🚀 TEST-SETUP ANLEITUNG – Craft CMS Auto-Update System

## 🎯 Was du testen wirst

Ein **Production-Ready CI/CD-System** für automatische Craft CMS Updates mit:
- ✅ **Intelligente Update-Analyse** (Sicherheitsupdates, PHP-Konflikte)
- ✅ **Automatische Pull Requests** mit Review-Info
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
**Erforderliche PAT-Berechtigungen:** `repo`, `workflow`

#### 📁 Dateien ins Repository pushen
```bash
git add .github/workflows/update.yml
git add craft/web/health.php
git add craft/web/webhook.php
git add craft/web/update-analyzer.php
git commit -m "🤖 Craft CMS Auto-Update System Setup"
git push origin main
```

### 2. n8n Setup

#### 📧 SMTP Konfiguration
```bash
# In n8n → Credentials → Add SMTP
Host: dein-smtp-server.de
Port: 587
User: craft-updates@farbcode.de
Password: dein-smtp-passwort
```

#### 🔄 Workflow importieren
1. n8n öffnen → Import
2. `n8n-erweitert-mit-email.json` importieren
3. Credentials zuweisen:
   - GitHub API (GITHUB_PAT als env)
   - SMTP Account

#### 🌐 Webhook konfigurieren
```
https://deine-craft-domain.com/craft/web/webhook.php
```

### 3. Craft CMS Setup

#### 📝 .env oder .env.claude
```bash
GITHUB_PAT=...
CRAFT_ENVIRONMENT=dev
CRAFT_DEV_MODE=true
CRAFT_WEB_URL=http://localhost:8081
```

#### 🐳 Docker Setup
```bash
docker compose up -d
```

---

## 🧪 SYSTEM TESTEN

### Test 1: Health Check
```bash
curl https://deine-domain.com/craft/web/health.php
```

### Test 2: Update Analyser
```bash
curl https://deine-domain.com/craft/web/update-analyzer.php
```

### Test 3: Webhook Trigger
```bash
curl -X POST https://deine-domain.com/craft/web/webhook.php   -H "Content-Type: application/json"   -d '{"action":"update","source":"manual_test"}'
```

### Test 4: GitHub Actions manuell starten

### Test 5: n8n Workflow (Trigger oder Zeitplan)

---

## 🔧 KONFIGURATION

### Email-Ziele
```html
tim@farbcode.de, team@farbcode.de
```

### Update-Ziele
```bash
TEST_URLS=(
  "https://deine-domain.com/"
  "https://deine-domain.com/health.php"
)
```

---

## 🚨 TROUBLESHOOTING

- PAT prüfen
- Docker PHP-Version prüfen (evtl. 8.3-fpm)
- SMTP-Verbindung testen
- Health Check Rechte & Logs prüfen

---

## 📊 MONITORING

```bash
tail -f craft/storage/logs/web-$(date +%F).log
tail -f /var/log/php/php_errors.log
```

---

## ✅ ERFOLGSKRITERIEN

- Automatische Updates & Pull Requests funktionieren
- Mails mit Ergebnis kommen an
- Health Check und Tests geben Status 200 zurück
- Backups vor jedem Update

**🎯 Ready for Production.**
