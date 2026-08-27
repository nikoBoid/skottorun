# Scotto Run

Web app personale per gestire una campagna di D&D durante le sessioni. Il Dungeon Master amministra la compagnia; i player condividono sia lo zaino sia il diario organizzato per argomenti e pagine.

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

- DM: `dm@scottorun.local`
- Player: `nico@scottorun.local`, `giulia@scottorun.local`, `marco@scottorun.local`
- Password locale: `password`, oppure il valore di `SCOTTORUN_SEED_PASSWORD`

La registrazione pubblica è disabilitata. Il DM crea i player dalla dashboard.

## Controlli qualità

```bash
php artisan test
vendor/bin/pint --test
pnpm run types:check
pnpm run lint:check
pnpm run build
```

## Deploy OVH con Docker

Il compose di produzione espone l’app soltanto su `127.0.0.1:8088`, così può convivere con gli altri progetti dietro il reverse proxy del server.

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

Configura il reverse proxy già presente su OVH verso `http://127.0.0.1:8088`. Deve inoltrare anche gli header WebSocket:

```nginx
location / {
    proxy_pass http://127.0.0.1:8088;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
```

I dati persistenti sono in `data/database` e `data/storage`. Esegui un backup giornaliero consistente di SQLite, ad esempio con il comando `.backup` di `sqlite3`, e conserva almeno una copia fuori dal server.

## Realtime

Ogni modifica crea una voce nella cronologia e pubblica un evento privato sul canale della campagna. Il queue worker invia l’evento a Reverb; gli altri browser ricaricano solo zaino, reliquie, diario e attività, senza refresh completo della pagina.
# skottorun
