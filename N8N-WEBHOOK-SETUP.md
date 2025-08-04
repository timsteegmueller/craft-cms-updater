# n8n Webhook Setup - Fix für "waiting for trigger" 

## Problem
n8n zeigt "waiting for you to trigger event from webhook trigger" = Webhook nicht aktiv

## Lösung (Schritt für Schritt)

### 1. n8n Workflow öffnen
- Dein "Tim PHP Updater" Workflow
- Webhook Trigger Node anklicken

### 2. Webhook aktiviren  
- **"Listen for calls" Button drücken** ← WICHTIG!
- n8n zeigt jetzt die echte Webhook URL an
- Etwa so: `https://dein-n8n.com/webhook/craft-update`

### 3. Workflow aktiviren
- Oben rechts Schalter auf "Active" setzen
- Webhook ist jetzt "live"

### 4. Testen
```bash
# Mit der n8n URL (NICHT deine craft domain!)
curl -X POST https://dein-n8n.com/webhook/craft-update \
  -H "Content-Type: application/json" \
  -d '{"action":"update","source":"test"}'
```

## Alternative: Direkt GitHub triggern

Falls n8n zickt, kannst auch direkt GitHub Actions triggern:

```bash
# Direkt zu GitHub (ohne n8n)
curl -H "Authorization: Bearer dein_github_pat" \
     -H "Accept: application/vnd.github+json" \
     -X POST \
     https://api.github.com/repos/timsteegmueller/craft-test-repo/dispatches \
     -d '{"event_type":"run-backup-und-update"}'
```

## Schedule aktiviren

Für wöchentliche Updates:
1. Schedule Trigger Node anklicken
2. Einstellungen prüfen: Montags 6:00 Uhr  
3. Workflow aktiviren

Das wars! 🎉