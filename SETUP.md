# Tim's Craft CMS Auto-Update Setup

## Übersicht

Dieses Setup ermöglicht automatische Craft CMS Updates über n8n → GitHub Actions Integration.

## 🔧 Konfiguration

### 1. GitHub Repository Setup

**Repository Secrets (Settings → Secrets and variables → Actions):**
```
GITHUB_PAT = dein_github_personal_access_token
```

**GitHub PAT Permissions benötigt:**
- `repo` (Full control of private repositories)
- `workflow` (Update GitHub Action workflows)

### 2. n8n Konfiguration

**Webhook URL für n8n:**
```
https://deine-craft-domain.com/craft/web/webhook.php
```

**n8n Workflow Settings:**
- Method: `POST`
- Content-Type: `application/json`
- Body:
```json
{
  "action": "update",
  "source": "n8n_scheduler"
}
```

### 3. Environment Variables

**In der Craft .env Datei:**
```env
# GitHub Integration
GITHUB_PAT=dein_github_personal_access_token
GITHUB_TOKEN=dein_github_personal_access_token

# Craft Settings
CRAFT_ENVIRONMENT=dev
CRAFT_DEV_MODE=true
CRAFT_SECURITY_KEY=auto_generated_key
```

## 🚀 Workflow Ablauf

### Automatisch (wöchentlich)
1. **n8n Schedule Trigger** (Montags) → 
2. **n8n Webhook Call** → 
3. **Craft webhook.php** → 
4. **GitHub Repository Dispatch** → 
5. **GitHub Action** führt Update aus

### Manuell
- **n8n**: Workflow manuell triggern
- **GitHub**: Action über "Run workflow" starten
- **Webhook**: Direkt `POST /craft/web/webhook.php` aufrufen

## 📊 Monitoring

### Health Check
```bash
curl https://deine-domain.com/craft/web/health.php
```

**Beispiel Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-08-04T10:00:00+00:00",
  "checks": {
    "database": {"status": "ok", "message": "Database connection successful"},
    "craft": {"status": "ok", "version": "5.7.10", "edition": "Solo"},
    "storage": {"status": "ok", "message": "Storage directory is writable"},
    "environment": {"status": "ok", "environment": "dev", "dev_mode": true}
  }
}
```

### Logs
- **GitHub Actions**: Repository → Actions Tab
- **Craft Logs**: `craft/storage/logs/`
- **Server Logs**: PHP Error Logs für webhook.php

## 🛠️ Troubleshooting

### GitHub Action schlägt fehl
1. Überprüfe `GITHUB_PAT` in Repository Secrets
2. Überprüfe PAT Permissions
3. Checke Logs in Actions Tab

### n8n Webhook funktioniert nicht
1. Teste webhook.php direkt mit curl
2. Überprüfe `GITHUB_PAT` in Craft .env
3. Checke PHP Error Logs

### Craft Update schlägt fehl
1. Führe `php craft update all` manuell aus
2. Überprüfe Composer Abhängigkeiten
3. Checke Dateiberechtigungen für storage/

## 🔄 Testing

### Lokales Testing
```bash
# 1. Health Check testen
curl -X GET http://localhost:8081/health.php

# 2. Webhook testen (setzt GITHUB_PAT voraus)
curl -X POST http://localhost:8081/webhook.php \
  -H "Content-Type: application/json" \
  -d '{"action":"update","source":"test"}'

# 3. Craft Update manuell testen
cd craft
php craft update all --interactive=0
```

### GitHub Action Testing
1. Gehe zu Repository → Actions
2. Wähle "Tim's Automatischer Craft CMS Update" 
3. Klicke "Run workflow"
4. Verfolge Logs in Echtzeit

## 📁 Wichtige Dateien

```
craft-test-repo/
├── .github/workflows/update.yml     # GitHub Action Definition
├── craft/web/health.php             # Health Check Endpoint  
├── craft/web/webhook.php            # n8n Webhook Receiver
├── n8nknoten.json                   # n8n Workflow Export
├── CLAUDE.md                        # Technische Dokumentation
└── SETUP.md                         # Diese Setup-Anleitung
```