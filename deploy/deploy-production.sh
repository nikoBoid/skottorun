#!/bin/sh
set -eu

project_dir="/opt/scottorun"
compose_file="$project_dir/compose.production.yml"
env_file="$project_dir/.env.production"
lock_file="$project_dir/data/deploy.lock"

cd "$project_dir"

if [ ! -f "$env_file" ]; then
    echo "Missing production environment: $env_file" >&2
    exit 1
fi

mkdir -p "$project_dir/data"
exec 9>"$lock_file"

if ! flock -n 9; then
    echo "Another Scotto Run deployment is already running." >&2
    exit 1
fi

git fetch --prune origin main
git merge --ff-only origin/main

sudo docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    config --quiet

sudo docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    build app web

sudo docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    up -d --remove-orphans

sudo docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    exec -T app php artisan migrate --force --no-interaction

curl \
    --fail \
    --silent \
    --show-error \
    --retry 10 \
    --retry-all-errors \
    --retry-delay 2 \
    --output /dev/null \
    http://127.0.0.1:8088/login

echo "Scotto Run deployed at commit $(git rev-parse --short HEAD)."
