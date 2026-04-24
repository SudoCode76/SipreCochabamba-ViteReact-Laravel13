#!/bin/bash
set -euo pipefail

BACKUP_FILE="/docker-entrypoint-initdb.d/sipre-202604201330_pg_backup.dmp"

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found: $BACKUP_FILE"
  exit 0
fi

echo "Restoring PostgreSQL backup from $BACKUP_FILE"

export PGPASSWORD="${POSTGRES_PASSWORD}"

pg_restore \
  --verbose \
  --clean \
  --if-exists \
  --no-owner \
  --no-privileges \
  --username="${POSTGRES_USER}" \
  --dbname="${POSTGRES_DB}" \
  "$BACKUP_FILE"

echo "PostgreSQL backup restored successfully"
