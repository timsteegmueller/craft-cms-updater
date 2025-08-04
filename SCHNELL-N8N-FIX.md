# n8n "waiting for trigger" Problem - Schnelle Lösung

Das Problem kennst du sicher: n8n hängt bei "waiting for you to trigger event from webhook trigger"

## Fix in 3 Schritten:

1. **Webhook Node anklicken** in deinem Workflow
2. **"Listen for calls" Button drücken** (das ist der Trick!) 
3. **Workflow aktiviren** (Schalter oben rechts auf "Active")

Fertig! n8n zeigt dir dann die echte Webhook URL an.

## Testen:
```bash
# Mit der n8n URL die angezeigt wird
curl -X POST https://dein-n8n.com/webhook/craft-update \
  -d '{"action":"update","source":"test"}'
```

## Falls es immer noch nicht geht:

Bypass n8n komplett und triggere GitHub Actions direkt:

```bash
curl -H "Authorization: Bearer dein_github_pat" \
     -X POST \
     https://api.github.com/repos/timsteegmueller/craft-test-repo/dispatches \
     -d '{"event_type":"run-backup-und-update"}'
```

Das funktioniert immer! 👍

---

*Erstellt 2025 - Tim S.*