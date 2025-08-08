# 🚀 Craft Updater • Base, Staging, Prod

<div align="center">

![GitHub Actions](https://img.shields.io/badge/GitHub_Actions-2088FF?style=for-the-badge&logo=github-actions&logoColor=white)
![Craft CMS](https://img.shields.io/badge/Craft_CMS-E5422B?style=for-the-badge&logo=craftcms&logoColor=white)
![n8n](https://img.shields.io/badge/n8n-EA4B71?style=for-the-badge&logo=n8n&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)

**🎯 Automatisierte Craft CMS Updates • 📦 DB Backups • 🔄 Multi-Environment Deploys**

*Nie wieder manuelle Handarbeit für deine Craft CMS Deployments*

</div>

---

## ✨ Features

- 🔒 **Token-geschützter Health-Endpoint** für sichere Status-Checks
- 🗄️ **Automatische DB-Backups** als GitHub Artifacts
- 🌍 **Multi-Environment Support** (Base, Staging, Prod)
- 📧 **E-Mail Benachrichtigungen** via n8n mit Changelog
- 🚦 **Health-Monitoring** vor und nach Deployments
- ⚡ **Envoyer Integration** für Zero-Downtime Deploys

---

## 🚀 Quick Start

> **Tipp:** Folge diesen Schritten in der exakten Reihenfolge für ein reibungsloses Setup!

### 1️⃣ Health-Endpoint einrichten

Füge das **Health-Token** zu deiner `.env` hinzu:

```env
HEALTH_TOKEN="b6c8f3a2d91f44d6a4b0f3e79c2a58c4e7b1f0d9a6c3e58b2d7f4a9c1e0b3d6f"
```

Erstelle die Datei `web/cms/health.php`:

<details>
<summary>📄 <strong>health.php Code anzeigen</strong></summary>

```php
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$expected = getenv('HEALTH_TOKEN') ?: '';
$given = $_GET['token'] ?? ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '');
if (!$expected || !hash_equals($expected, (string)$given)) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

$status = ['status' => 'ok', 'checks' => [], 'time' => date('c')];

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
    if ($q !== 'clear') $status['status'] = 'problem'; 
} catch (Throwable $e) { 
    $status['checks']['queue'] = 'unknown'; 
    $status['status'] = 'problem'; 
    $status['error']['queue'] = $e->getMessage(); 
}

$status['craftVersion'] = Craft::$app->getVersion();
http_response_code($status['status'] === 'ok' ? 200 : 503);
echo json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```

</details>

**🧪 Lokaler Test:**
```bash
curl -fsS "http://farbcode-base.test/cms/health.php?token=<dein_token>"
```

### 2️⃣ GitHub Secrets konfigurieren

<details>
<summary>🔐 <strong>Alle benötigten Secrets</strong></summary>

| 🏷️ Secret Name | 📝 Beschreibung | 💡 Beispielwert |
|---|---|---|
| `BASE_SSH_HOST` | Server-IP für Base Environment | `192.0.2.10` |
| `BASE_SSH_USER` | SSH-Benutzername | `deploy` |
| `BASE_SSH_KEY` | SSH Private Key (PEM Format) | `-----BEGIN OPENSSH PRIVATE KEY-----` |
| `BASE_PROJECT_PATH` | Projektpfad auf dem Server | `/var/www/farbcode-base-23` |
| `BASE_HEALTH_URL` | Interne Health-URL | `http://localhost/cms/health.php?token=...` |
| `BASE_ROOT_URL` | Interne Root-URL | `http://localhost/` |
| `BASE_ENVOYER_HOOK_URL` | Envoyer Deploy Hook (optional) | `https://envoyer.io/deploy/abc123` |
| `PROD_HEALTH_URL` | Öffentliche Health-URL | `https://kunde.de/cms/health.php?token=...` |
| `PROD_ROOT_URL` | Öffentliche Root-URL | `https://kunde.de/` |
| `ENVOYER_HOOK_URL` | Prod Envoyer Hook | `https://envoyer.io/deploy/xyz789` |

**🔑 SSH Public Key für `~/.ssh/authorized_keys`:**
```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAICjd8M9m7PBEn2t2gtsO62d0/WvZV7g4nBdOuV1/T07H actions-deploy
```

</details>

### 3️⃣ n8n Workflow importieren

Importiere den [n8n Workflow](docs/n8n-workflow.md) und teste den GitHub Dispatch.

### 4️⃣ Deployment-Reihenfolge

```mermaid
graph LR
    A[🗄️ Base Backup] --> B[🔍 Prod Health Check]
    B --> C{✅ Grün?}
    C -->|Ja| D[🚀 Prod Deploy]
    C -->|Nein| E[❌ Stop]
    D --> F[📧 Success Mail]
```

---

## 🔧 Workflows

### 📦 Base Environment
**Datei:** `.github/workflows/craft-base-db-deploy.yml`

- 🗄️ **DB Backup** via SSH (`php craft db/backup`)
- 📤 **Artifact Upload** der `.sql.gz` Datei
- 🔗 **Optional Envoyer Trigger**
- 🩺 **Health Check** intern über `curl -k`

### 🌐 Production Environment
**Datei:** `.github/workflows/craft-prod-deploy.yml`

- 🔒 **Strenge HTTPS-Prüfungen**
- 🎯 **Check-Only Modus** zum Vortesten
- 🚀 **Zero-Downtime Deploy** via Envoyer
- ⏱️ **Health Loop** mit automatischem Retry

### 🛠️ Debug Helper
**Datei:** `.github/workflows/craft-prod-health-test-insecure.yml`

Für SSL-Debugging mit `-k` Flag. Nur temporär verwenden!

---

## 🤖 n8n Automation

<div align="center">

```mermaid
graph TD
    A[📅 Schedule/Manual] --> B[🚀 GitHub Dispatch]
    B --> C[⏰ Wait 30s]
    C --> D[📥 Fetch Runs]
    D --> E{✅ Completed?}
    E -->|Nein| F[🔁 Wait 15s]
    F --> D
    E -->|Ja| G{🎉 Success?}
    G -->|Ja| H[📧 Success Mail]
    G -->|Nein| I[📧 Error Mail]
```

</div>

Der n8n Workflow überwacht deine GitHub Actions und sendet dir detaillierte E-Mails mit Changelogs bei erfolgreichen Updates oder Fehlermeldungen bei Problemen.

**📋 Detaillierte Dokumentation:** [n8n Workflow Setup](docs/n8n-workflow.md)

---

## 📧 E-Mail Setup

### 🐳 Lokal (Docker)
- **UI:** `http://localhost:8025` (Mailpit)
- **SMTP:** `mailpit:1025` (Container) oder `127.0.0.1:1025` (Host)

### 🚀 Production
Nutze dein eigenes SMTP oder ein separates Mailpit auf dem Server.

---

## 🆘 Troubleshooting

<details>
<summary>❓ <strong>Häufige Probleme & Lösungen</strong></summary>

### 🚫 Health 401 Unauthorized
```bash
# Problem: Token fehlt oder ist falsch
# Lösung: .env prüfen, Token in health.php korrekt?
curl -fsS "http://example.com/cms/health.php?token=DEIN_TOKEN"
```

### 🔴 Base Health scheitert
```bash
# Problem: URL auf Server nicht erreichbar
# Lösung: Host-Header setzen falls nötig
curl -k -H 'Host: farbcode-base.test' http://localhost/cms/health.php?token=...
```

### 🌐 Prod Health rot
- **Zertifikatsprobleme:** `-k` Flag temporär für Debug
- **Redirect-Kette:** `-L` Flag verwenden
- **Alternative:** Teste eine 200er Unterseite

### 🤖 n8n "Invalid URL"
- **Problem:** Expression im URL-Feld
- **Lösung:** Query Parameter nutzen, nicht String-Concatenation

### 📝 Kein PR erstellt
```bash
# Lösung: Dummy-File für Commit erstellen
echo "$(date)" > docs/last-update.txt
```

</details>

---

## 🤝 Mitwirken

Hast du Verbesserungsvorschläge oder gefundene Bugs? 

1. 🍴 **Fork** das Repository
2. 🌟 **Feature Branch** erstellen (`git checkout -b feature/amazing-feature`)
3. 💾 **Commit** deine Änderungen (`git commit -m 'Add amazing feature'`)
4. 📤 **Push** zum Branch (`git push origin feature/amazing-feature`)
5. 🎯 **Pull Request** erstellen

---

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz - siehe [LICENSE](LICENSE) für Details.

---

<div align="center">

**⭐ Gefällt dir das Projekt? Gib uns einen Stern!**

Made with ❤️ and ☕ for the Craft CMS Community

</div>
