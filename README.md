# Scotto Run

Web app personale per gestire una campagna di D&D durante le sessioni. Il Dungeon Master amministra la compagnia; i player condividono sia lo zaino sia il diario organizzato per argomenti e pagine.

Ogni personaggio dispone inoltre di una scheda PDF compilabile privata: il player può modificarla e salvarla dal browser, mentre il DM può consultarla dal tavolo di gioco.

Le reliquie vengono preparate esclusivamente dal DM, anche senza assegnarle subito. Possono avere un’immagine e contenuti aggiuntivi sigillati: abilità e parti di storia diventano leggibili dal player solo quando il DM le sblocca.

## Stack

- Laravel 13 e PHP 8.4
- Vue 3, TypeScript e Inertia 3
- Tailwind CSS 4
- SQLite
- Laravel Reverb ed Echo per gli aggiornamenti realtime
- Pest/PHPUnit per i test

## Avvio locale

Requisiti: PHP 8.3+, Composer, Node 22+ e pnpm 11.

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
pnpm build
php artisan serve
```

Per provare anche il realtime, avvia in altri due terminali:

```bash
php artisan queue:work
php artisan reverb:start
```

Account demo creati dal seeder:

- DM: `dm`
- Player: `nico`, `giulia`, `marco`
- Password locale: `password`, oppure il valore di `SCOTTORUN_SEED_PASSWORD`

La registrazione pubblica è disabilitata. Il DM crea e gestisce i player dalla pagina **Personaggi**.

## Controlli qualità

```bash
php artisan test
vendor/bin/pint --test
pnpm run types:check
pnpm run lint:check
pnpm run build
```

## Deploy OVH con Docker

Il compose di produzione espone l’app soltanto su `127.0.0.1:8088` e collega il servizio web alla rete Docker esterna `climb_edge`, così può convivere con Climb dietro lo stesso Caddy.

```bash
cp .env.production.example .env.production
mkdir -p data/database data/storage
```

Compila `.env.production` con:

- dominio pubblico in `APP_URL` e `PUBLIC_HOST`;
- una chiave generata con `php artisan key:generate --show`;
- valori casuali e distinti per `REVERB_APP_KEY` e `REVERB_APP_SECRET`.

Poi avvia i quattro servizi: PHP-FPM, queue worker, Reverb e Nginx.

```bash
docker compose --env-file .env.production -f compose.production.yml up -d --build
```

Al primo avvio le migrazioni partono automaticamente. Crea il DM e la campagna iniziale con il comando interattivo:

```bash
docker compose --env-file .env.production -f compose.production.yml exec app php artisan scottorun:bootstrap
```

Per reimpostare in modo interattivo username e password del DM, senza salvare la password nei file o nella cronologia della shell:

```bash
docker compose --env-file .env.production -f compose.production.yml exec app php artisan scottorun:set-dm-credentials --username=dm
```

Per i deploy successivi, esegui commit e push dalla macchina locale, quindi lancia lo script sul VPS. Lo script accetta soltanto aggiornamenti fast-forward, ricompila le immagini, applica le migrazioni e verifica la pagina di login:

```bash
git push origin main
ssh ubuntu@145.239.74.138 /opt/scottorun/deploy/deploy-production.sh
```

Configura Caddy sulla rete `climb_edge`; WebSocket e header proxy vengono gestiti automaticamente:

La configurazione usata sul server è conservata in `deploy/Caddyfile.scottorun`.

I dati persistenti sono in `data/database` e `data/storage`. Lo script `deploy/backup-production.sh` crea una copia consistente di SQLite, archivia gli upload pubblici e le schede PDF private e conserva 30 giorni di backup in `/opt/scottorun-backups`. I file systemd inclusi lo eseguono ogni giorno alle 03:30, ora italiana.

## Realtime

Ogni modifica crea una voce nella cronologia e pubblica un evento privato sul canale della campagna. Il queue worker invia l’evento a Reverb; gli altri browser ricaricano solo zaino, reliquie, diario e attività, senza refresh completo della pagina.
# skottorun
