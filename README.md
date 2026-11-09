# StallFront

Online shop window for sellers who work at weekly markets. Customers browse
the catalogue, fill a cart and choose the market and the day to collect their
order. There is no online payment: customers pay in person, at pickup or on
home delivery.

Self-hosted, mobile first, in 8 languages.

[Italiano](README.it.md)

> The project is young: it works, but some features are still being finished.
> Feedback and issues are welcome.

## Features

- **Catalogue**: categories, products with photos, free-form variants (size,
  colour, material...), stock, low-stock alerts, seasonal products that can be
  ordered with longer lead times.
- **Markets and pickup**: weekly markets with order deadlines and minimum
  notice, holidays and unplanned closures (affected customers are notified and
  can pick another date).
- **Orders**: checkout with market and date, optional home delivery for the
  customers the seller enables, order board by market or by product, printable
  lists, **counter mode** with QR code scanning to hand over orders at the stall.
- **Customers**: registration with email confirmation, account area, order
  history, reorder, missed-pickup tracking with automatic blocking, data export
  and account deletion.
- **Notifications**: emails and browser push notifications, day-before
  reminders, message texts editable by the seller in every language.
- **Team**: collaborators with fine-grained permissions.
- **Languages**: English, Italiano, Español, Français, Deutsch, Português,
  Nederlands, Polski. Visitors get the language of their browser and can change
  it; registered users choose theirs, used for pages, emails and notifications.
  Products keep the names the seller gives them.
- **Legal pages**: privacy, cookie and terms-of-sale templates for every
  language, filled in with the shop details and editable from the panel.
- **Optional WhatsApp** verification of mobile numbers (WhatsApp Cloud API).

## Quick start with Docker

```bash
mkdir stallfront && cd stallfront
curl -O https://raw.githubusercontent.com/AngelC112/stallfront/main/docker-compose.yml
docker compose up -d
```

Open <http://localhost:8080>: the first page asks you to create the seller
account and the shop name. Then add your markets and products from the panel.

With no database settings the container starts its own MariaDB, generates the
password and all the keys on the first start and stores everything in `./data`:

| Path | Content |
|---|---|
| `data/.env` | generated settings: database password, application key, notification keys |
| `data/mysql/` | embedded database |
| `data/storage/` | product photos, logs, sessions, cache |

Back up the whole `data` folder.

### TrueNAS, Portainer, Unraid, CasaOS

Paste [docker-compose.yml](docker-compose.yml) as a custom app / stack. Map a
persistent dataset or folder to `/data` and the web port to `80`. Put the site
behind your reverse proxy with HTTPS: the camera for QR scanning and browser
notifications need it.

### Recommended settings

```yaml
    environment:
      APP_URL: "https://shop.example.com"   # used in email links and image URLs
      APP_TIMEZONE: "Europe/Rome"
      MAIL_MAILER: "smtp"
      MAIL_HOST: "smtp.example.com"
      MAIL_PORT: "587"
      MAIL_USERNAME: "shop@example.com"
      MAIL_PASSWORD: "change-me"
      MAIL_FROM_ADDRESS: "shop@example.com"
```

Without email settings the messages are only written to the log.

### External database

Set `DB_HOST` and the embedded MariaDB is not started:

```yaml
    environment:
      DB_CONNECTION: "mysql"     # mysql | pgsql
      DB_HOST: "192.0.2.10"
      DB_PORT: "3306"
      DB_DATABASE: "stallfront"
      DB_USERNAME: "stallfront"
      DB_PASSWORD: "change-me"
```

The database must exist and the user must be able to create tables.

### Build the image yourself

```bash
git clone https://github.com/AngelC112/stallfront.git
cd stallfront
docker build -t stallfront .
```

## Shared hosting without Docker

Requirements: PHP 8.3+ with `pdo_mysql` or `pdo_pgsql`, `mbstring`, `intl`,
`gd`, `fileinfo`, `openssl`; a MySQL/MariaDB or PostgreSQL database.

1. Download `stallfront-vX.Y.Z.zip` from the
   [releases](https://github.com/AngelC112/stallfront/releases). It already
   contains the PHP dependencies and the compiled assets.
2. Upload the content of the folder by FTP. Best: point the domain's web root
   to the `public` folder. If the hosting does not allow it, upload everything
   into the web root: the included `.htaccess` sends every request to `public/`
   and keeps the other files unreachable.
3. Create a database from the hosting control panel.
4. Open the site: the installer checks the requirements, asks for the database
   and email details, creates the tables and saves `.env`. Then you create the
   seller account.

Email: choose **Hosting's built-in email** to use the hosting's own mail service,
or **SMTP server**. Use a sender address of your domain, otherwise many
providers reject the messages.

Scheduled tasks (notifications, reminders, clean-up): if the hosting has cron,
add a job that runs every minute:

```bash
cd /path/to/stallfront && php artisan schedule:run
```

Without cron, choose the option in the installer: the tasks then run while
people use the site.

To reinstall, delete `.env`.

## Configuration

Every setting can be an environment variable or a line in `.env` (environment
variables win). Full list in [.env.example](.env.example). Most shop settings
(name, VAT number, delivery, deadlines, message texts, legal pages) are changed
from the seller panel.

## Updates

- **Docker**: `docker compose pull && docker compose up -d`.
- **Shared hosting**: upload the new release over the old files, keeping
  `.env` and `storage/`.

Database changes are applied automatically (Docker) or with
`php artisan migrate --force`.

## Translations

The source texts are in Italian and wrapped in `__()`; translations live in
`lang/<code>.json`, the default legal texts in `resources/legal/<code>/`.
To add a language, add it to `locales` in `config/app.php` and translate those
files.

## Development

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run dev & php artisan serve
php artisan test
```

## License

Copyright (C) Angelo Carfora.

StallFront is free software: you can redistribute it and/or modify it under the
terms of the [GNU Affero General Public License v3.0](LICENSE). If you modify it
and make it available to others, including as an online service, you must
publish your source code under the same license.

To use it without these obligations (closed-source products, hosted services
that do not publish their code) a [commercial license](COMMERCIAL.md) is
available.
