#!/bin/sh
# Makes a backup of a Docker Compose BOBLOX: database, uploads and custom badges.
#   ./deploy/backup.sh            -> backups/boblox-YYYYMMDD-HHMMSS.tar.gz
# Safe to run while the site is up (the database is copied with SQLite's backup API).
set -e
cd "$(dirname "$0")/.."
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p backups

docker compose exec -T boblox php -r '
  $src = new SQLite3("/var/www/html/App_Data/boblox.db", SQLITE3_OPEN_READONLY);
  $dst = new SQLite3("/var/www/html/App_Data/boblox-backup.db");
  $src->backup($dst);
  $dst->close();'

# thumbnails and logins are left out: thumbnails are redrawn when needed
docker compose exec -T boblox sh -c '
  cd /var/www/html/App_Data &&
  tar czf - boblox-backup.db uploads &&
  rm -f boblox-backup.db' > "backups/boblox-$STAMP.tar.gz"

echo "Saved backups/boblox-$STAMP.tar.gz"
