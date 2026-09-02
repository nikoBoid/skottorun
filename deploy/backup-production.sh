#!/bin/sh
set -eu

umask 077

project_dir="/opt/scottorun"
backup_dir="/opt/scottorun-backups"
compose_file="$project_dir/compose.production.yml"
env_file="$project_dir/.env.production"
snapshot_host="$project_dir/data/database/.database.sqlite.snapshot"
snapshot_container="/var/www/html/database-data/.database.sqlite.snapshot"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
final_dir="$backup_dir/$timestamp"
temp_dir=""

cleanup() {
    rm -f "$snapshot_host"

    if [ -n "$temp_dir" ] && [ -d "$temp_dir" ]; then
        rm -rf "$temp_dir"
    fi
}

trap cleanup EXIT INT TERM

mkdir -p "$backup_dir"
chmod 0700 "$backup_dir"
temp_dir="$(mktemp -d "$backup_dir/.tmp.XXXXXX")"

rm -f "$snapshot_host"

docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    exec -T --user www-data app \
    php -r '
        $source = new SQLite3($argv[1], SQLITE3_OPEN_READONLY);
        $destination = new SQLite3($argv[2], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

        if (! $source->backup($destination)) {
            fwrite(STDERR, "SQLite backup failed.\n");
            exit(1);
        }

        $destination->close();
        $source->close();
    ' /var/www/html/database-data/database.sqlite "$snapshot_container"

install -m 0600 "$snapshot_host" "$temp_dir/database.sqlite"
rm -f "$snapshot_host"

tar -C "$project_dir/data/storage" -czf "$temp_dir/uploads.tar.gz" app/public
install -m 0600 "$env_file" "$temp_dir/.env.production"

(
    cd "$temp_dir"
    sha256sum database.sqlite uploads.tar.gz .env.production > SHA256SUMS
)

mv "$temp_dir" "$final_dir"
temp_dir=""

find "$backup_dir" \
    -mindepth 1 \
    -maxdepth 1 \
    -type d \
    -name '20????????T??????Z' \
    -mtime +30 \
    -exec rm -rf -- {} +

echo "Backup completed: $final_dir"
