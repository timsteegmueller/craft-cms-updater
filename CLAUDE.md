# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Technology Stack

This is a **Craft CMS 5.7.10** project running on **PHP 8.2** with the folowing architecture:

- **CMS**: Craft CMS 5.7.10 (headless mode enabled)
- **PHP Version**: 8.2
- **Database**: MariaDB 10.5 (MySQL compatible)
- **Frontend**: Basic Twig templates (currently using default welcome template)
- **Containerization**: Docker with docker-compose
- **Package Management**: Composer

## Project Structure

```
craft-test-repo/
├── craft/                    # Main Craft CMS application
│   ├── config/              # Craft configuration files
│   ├── templates/           # Twig templates
│   ├── web/                 # Web root directory
│   ├── storage/             # Storage directory (backups, logs, cache)
│   ├── craft                # Craft console command (executable)
│   └── composer.json        # PHP dependencies
├── docker-compose.yml       # Docker container configuration
├── Dockerfile              # PHP-FPM Docker image definition
└── workflows/              # GitHub Actions workflows
```

## Development Commands

### Local Development with Docker
- **Start development environment**: `docker-compose up -d`
- **Stop environment**: `docker-compose down`
- **Access Craft container**: `docker-compose exec craft bash`

### Craft Console Commands
Execute from the `craft/` directory:
- **Run Craft console**: `./craft` or `php craft`
- **Create database backup**: `php craft db/backup`
- **Restore database backup**: `php craft db/restore <backup-file>`
- **Run updates**: `php craft update/all`
- **Clear caches**: `php craft clear-caches/all`

### Composer Commands
Execute from root or `craft/` directory:
- **Install dependencies**: `composer install`
- **Update dependencies**: `composer update`

### Custom Scripts
- **Copy demo assets**: `./craft/copy-demo-assets.sh` (copies from `assets-demo/` to `web/assets/images/`)
- **Restore latest backup**: `./craft/restore-latest-craft-backup.sh` (automated backup restoration)

## Configuration

### Environment Configuration
- Environment files are in `craft/` directory: `.env`, `.env.example.dev`, etc.
- Database connection configured for Docker: `craftdb:3306`
- Default database: `craft_local` with user `craft`/`farbcode` and password `secret`
- Development mode is enabled (`CRAFT_DEV_MODE=true`)
- Headless mode is enabled in `craft/config/general.php`

### Database Access
- **Container**: `craftdb`
- **Host Port**: 33064 (mapped from container port 3306)
- **Database**: `craft_local`
- **User**: `craft` or `farbcode`
- **Password**: `secret`

## Automation & Integration

### n8n → GitHub Actions Integration
- **n8n Workflow**: "Tim PHP Updater" (`n8nknoten.json`)
  - **Webhook Trigger**: `POST /craft/web/webhook.php` (endpoint: `craft-update`)
  - **Schedule Trigger**: Weekly on Mondays
  - **Action**: Sends GitHub Repository Dispatch to `timsteegmueller/craft-projekte`

### GitHub Actions
- **`.github/workflows/update.yml`**: Tim's Automatischer Craft CMS Update
  - **Triggers**: `repository_dispatch` (type: `run-backup-und-update`) + `workflow_dispatch`
  - **Services**: MariaDB 10.5 for testing
  - **Steps**: 
    1. 📥 Repository checkout
    2. 🛠️ PHP 8.2 setup with extensions
    3. 📦 Composer install with optimization
    4. 🔧 Environment setup (.env generation)
    5. 🏥 Health check via `/craft/web/health.php`
    6. 💾 Database backup to `storage/backups/`
    7. ⬆️ Craft CMS update (non-interactive)
    8. 🧹 Cache clearing
    9. 📤 Auto-commit changes to repository
    10. 📊 Update summary

### API Endpoints
- **`/craft/web/health.php`**: JSON health check endpoint
  - Returns system status, database connection, Craft version, storage status
  - Used by n8n and GitHub Actions for monitoring
- **`/craft/web/webhook.php`**: n8n webhook receiver
  - Accepts POST requests from n8n
  - Triggers GitHub Repository Dispatch
  - Requires `GITHUB_PAT` environment variable

## Key Files

- **`craft/config/general.php`**: Main Craft configuration (headless mode, dev mode enabled)
- **`craft/config/app.php`**: Yii application configuration
- **`craft/bootstrap.php`**: Application bootstrap file
- **`craft/craft`**: Executable console command for Craft operations
- **`craft/templates/index.twig`**: Default welcome template
- **`docker-compose.yml`**: Defines craft app and craftdb (MariaDB) services

## Database Backups

Backups are stored in `craft/storage/backups/` with the naming pattern:
`craft--YYYY-MM-DD-HHMMSS--v5.7.10.sql`

The restoration script automatically finds and restores the latest backup file.