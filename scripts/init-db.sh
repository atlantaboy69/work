#!/bin/bash
set -e

if [ -f /backup.dump ]; then
    echo "Restoring database from /backup.dump..."
    pg_restore --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --no-owner --role="$POSTGRES_USER" /backup.dump || psql --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -f /backup.dump || true
    echo "Database restoration finished."
fi
