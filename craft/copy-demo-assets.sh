#!/bin/bash

# Quelle und Ziel definieren
SRC_DIR="./assets-demo"
DEST_DIR="./web/assets/images"

# Zielverzeichnis erstellen, falls es nicht existiert
mkdir -p "$DEST_DIR"

# Dateien und Verzeichnisse rekursiv kopieren
cp -r "$SRC_DIR/"* "$DEST_DIR/"

echo "Alle Dateien wurden von $SRC_DIR nach $DEST_DIR kopiert."
