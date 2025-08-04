#!/bin/bash

# === Konfiguration ===
# Setze hier den absoluten Pfad zum Wurzelverzeichnis deines Craft-Projekts
PROJECT_ROOT="/var/www/craft"
# === Ende Konfiguration ===

# Stellt sicher, dass das Skript bei einem Fehler sofort abbricht
set -e
# Behandelt nicht gesetzte Variablen als Fehler
set -u

# Definiere den Backup-Ordner und den Pfad zur Craft-Konsole
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
CRAFT_CMD="php $PROJECT_ROOT/craft" # Stellt sicher, dass der 'craft'-Befehl gefunden wird

# Überprüfe, ob das Projektverzeichnis existiert
if [ ! -d "$PROJECT_ROOT" ]; then
  echo "Fehler: Projektverzeichnis '$PROJECT_ROOT' nicht gefunden."
  echo "Bitte passe die Variable 'PROJECT_ROOT' im Skript an."
  exit 1
fi

# Überprüfe, ob das Backup-Verzeichnis existiert
if [ ! -d "$BACKUP_DIR" ]; then
  echo "Fehler: Backup-Verzeichnis '$BACKUP_DIR' nicht gefunden."
  exit 1
fi

echo "Starte den Wiederherstellungsprozess für die Craft-Datenbank..."
echo "Projektverzeichnis: $PROJECT_ROOT"
echo "Backup-Verzeichnis: $BACKUP_DIR"

# 1. Wechsle in das Projektverzeichnis (wichtig für den Craft-Befehl)
cd "$PROJECT_ROOT" || { echo "Fehler: Konnte nicht in das Verzeichnis '$PROJECT_ROOT' wechseln."; exit 1; }
echo "Erfolgreich in das Projektverzeichnis gewechselt."

# 2. Finde die neueste Backup-Datei im .zip Format
echo "Suche nach der neuesten Backup-Datei in $BACKUP_DIR..."
# Verwendet `find` für Robustheit, sortiert nach Änderungszeit (neueste zuerst)
# Passt das Muster an dein Format an: *-YYYY-MM-DD-HHMMSS--*.sql.zip
LATEST_ZIP=$(find "$BACKUP_DIR" -maxdepth 1 -name '*-??????--*.sql.zip' -printf '%T@ %p\n' | sort -nr | head -n 1 | cut -d' ' -f2-)

# Überprüfe, ob eine Datei gefunden wurde
if [ -z "$LATEST_ZIP" ]; then
  echo "Fehler: Keine Backup-Dateien (*.sql.zip) im Verzeichnis '$BACKUP_DIR' gefunden, die dem erwarteten Muster entsprechen."
  exit 1
fi
echo "Neueste Backup-Datei gefunden: $LATEST_ZIP"

# 3. Bestimme den erwarteten Pfad der SQL-Datei nach dem Entpacken
# Extrahiere den Dateinamen ohne Pfad und die .zip Endung
SQL_FILENAME=$(basename "${LATEST_ZIP%.zip}")
SQL_FILEPATH="$BACKUP_DIR/$SQL_FILENAME"

# Bereinige evtl. vorhandene alte SQL-Datei vom letzten Lauf
if [ -f "$SQL_FILEPATH" ]; then
    echo "Entferne bereits existierende entpackte Datei: $SQL_FILEPATH"
    rm "$SQL_FILEPATH"
fi

# 4. Entpacke die neueste Backup-Datei
echo "Entpacke '$LATEST_ZIP'..."
# -o: Überschreibt existierende Dateien ohne Nachfrage
# -d: Entpackt in das angegebene Verzeichnis
unzip -o "$LATEST_ZIP" -d "$BACKUP_DIR"

# Überprüfe, ob die SQL-Datei erfolgreich entpackt wurde
if [ ! -f "$SQL_FILEPATH" ]; then
    echo "Fehler: Die SQL-Datei '$SQL_FILENAME' konnte nicht aus '$LATEST_ZIP' extrahiert werden."
    exit 1
fi
echo "Datei erfolgreich entpackt nach: $SQL_FILEPATH"

# 5. Spiele die Datenbank mithilfe des Craft-Konsolenbefehls ein
echo "Spiele die Datenbank aus '$SQL_FILEPATH' ein..."
# --interactive=0 verhindert Bestätigungsabfragen
if $CRAFT_CMD db/restore "$SQL_FILEPATH" --interactive=0; then
    echo "Datenbank-Wiederherstellung erfolgreich abgeschlossen."
else
    # Der Befehl selbst gibt meist eine Fehlermeldung aus.
    echo "Fehler: Der Befehl 'craft db/restore' ist fehlgeschlagen."
    # Behalte die entpackte SQL-Datei für die Fehlersuche
    exit 1
fi

# 6. Räume die entpackte SQL-Datei auf
echo "Entferne die entpackte SQL-Datei: $SQL_FILEPATH"
rm "$SQL_FILEPATH"

echo "Skript erfolgreich beendet."
exit 0