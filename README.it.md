# StallFront

Vetrina online per chi vende nei mercati settimanali. I clienti sfogliano il
catalogo, riempiono il carrello e scelgono il mercato e il giorno in cui
ritirare l'ordine. Nessun pagamento online: si paga di persona, al ritiro o alla
consegna a domicilio.

Da installare sul proprio server, pensata per il telefono, in 8 lingue.

[English](README.md)

> Il progetto è giovane: funziona, ma alcune funzioni sono ancora in via di
> completamento. Segnalazioni e suggerimenti sono benvenuti.

## Funzionalità

- **Catalogo**: categorie, prodotti con foto, varianti libere (taglia, colore,
  materiale...), giacenza, avvisi di scorta bassa, prodotti stagionali
  ordinabili con tempi più lunghi.
- **Mercati e ritiro**: mercati settimanali con orario limite e anticipo minimo,
  ferie e chiusure straordinarie (i clienti coinvolti vengono avvisati e possono
  scegliere un'altra data).
- **Ordini**: checkout con mercato e data, consegna a domicilio facoltativa per
  i clienti abilitati, ordini per mercato o per prodotto, elenchi stampabili,
  **modalità banco** con scansione del QR per consegnare gli ordini al banco.
- **Clienti**: registrazione con conferma email, area personale, storico ordini,
  riordino, conteggio dei mancati ritiri con blocco automatico, esportazione
  dati ed eliminazione dell'account.
- **Notifiche**: email e notifiche del browser, promemoria del giorno prima,
  testi dei messaggi modificabili dal venditore in ogni lingua.
- **Collaboratori** con permessi separati.
- **Lingue**: English, Italiano, Español, Français, Deutsch, Português,
  Nederlands, Polski. Chi visita vede la lingua del browser e può cambiarla;
  chi si registra sceglie la sua, usata per pagine, email e notifiche. I
  prodotti restano con i nomi scelti dal venditore.
- **Pagine legali**: modelli di privacy, cookie e termini di vendita per ogni
  lingua, compilati con i dati del negozio e modificabili dal pannello.
- **Verifica del cellulare con WhatsApp** facoltativa (WhatsApp Cloud API).

## Avvio rapido con Docker

```bash
mkdir stallfront && cd stallfront
curl -O https://raw.githubusercontent.com/AngelC112/stallfront/main/docker-compose.yml
docker compose up -d
```

Apri <http://localhost:8080>: la prima pagina chiede di creare l'account del
venditore e il nome del negozio. Poi aggiungi mercati e prodotti dal pannello.

Senza impostazioni del database il container avvia un suo MariaDB, genera
password e chiavi al primo avvio e salva tutto in `./data`:

| Percorso | Contenuto |
|---|---|
| `data/.env` | impostazioni generate: password del database, chiave dell'app, chiavi delle notifiche |
| `data/mysql/` | database interno |
| `data/storage/` | foto dei prodotti, log, sessioni, cache |

Fai il backup dell'intera cartella `data`.

### TrueNAS, Portainer, Unraid, CasaOS

Incolla [docker-compose.yml](docker-compose.yml) come app personalizzata / stack.
Collega un dataset o una cartella persistente a `/data` e la porta web a `80`.
Metti il sito dietro il tuo reverse proxy con HTTPS: servono alla fotocamera per
il QR e alle notifiche del browser.

### Impostazioni consigliate

```yaml
    environment:
      APP_URL: "https://negozio.example.com"   # usato nei link delle email e nelle immagini
      APP_TIMEZONE: "Europe/Rome"
      APP_LOCALE: "it"
      MAIL_MAILER: "smtp"
      MAIL_HOST: "smtp.example.com"
      MAIL_PORT: "587"
      MAIL_USERNAME: "negozio@example.com"
      MAIL_PASSWORD: "cambiami"
      MAIL_FROM_ADDRESS: "negozio@example.com"
```

Senza impostazioni email i messaggi vengono solo scritti nel log.

### Database esterno

Imposta `DB_HOST` e il MariaDB interno non viene avviato:

```yaml
    environment:
      DB_CONNECTION: "mysql"     # mysql | pgsql
      DB_HOST: "192.0.2.10"
      DB_PORT: "3306"
      DB_DATABASE: "stallfront"
      DB_USERNAME: "stallfront"
      DB_PASSWORD: "cambiami"
```

Il database deve esistere e l'utente deve poter creare tabelle.

## Hosting condiviso senza Docker (es. Aruba)

Requisiti: PHP 8.3+ con `pdo_mysql` o `pdo_pgsql`, `mbstring`, `intl`, `gd`,
`fileinfo`, `openssl`; un database MySQL/MariaDB o PostgreSQL.

1. Scarica `stallfront-vX.Y.Z.zip` dalle
   [release](https://github.com/AngelC112/stallfront/releases). Contiene già le
   dipendenze PHP e i file grafici compilati.
2. Carica il contenuto della cartella via FTP. L'ideale è far puntare il dominio
   alla cartella `public`. Se l'hosting non lo permette, carica tutto nella root
   del sito: il `.htaccess` incluso manda ogni richiesta in `public/` e rende
   irraggiungibili gli altri file.
3. Crea un database dal pannello dell'hosting.
4. Apri il sito: l'installer controlla i requisiti, chiede i dati del database e
   dell'email, crea le tabelle e salva `.env`. Poi crei l'account del venditore.

Email: scegli **Email integrata dell'hosting** per usare la posta dell'hosting,
oppure **Server SMTP**. Usa un mittente del tuo dominio, altrimenti molti
provider rifiutano i messaggi.

Attività programmate (notifiche, promemoria, pulizia): se l'hosting ha il cron,
aggiungi un'operazione eseguita ogni minuto:

```bash
cd /percorso/di/stallfront && php artisan schedule:run
```

Senza cron, scegli l'opzione nell'installer: le attività partono mentre le
persone usano il sito.

Per reinstallare elimina `.env`.

## Configurazione

Ogni impostazione può essere una variabile d'ambiente o una riga di `.env`
(vincono le variabili d'ambiente). Elenco completo in [.env.example](.env.example).
Gran parte delle impostazioni del negozio (nome, partita IVA, consegna, orari,
testi dei messaggi, pagine legali) si cambia dal pannello del venditore.

## Aggiornamenti

- **Docker**: `docker compose pull && docker compose up -d`.
- **Hosting condiviso**: carica la nuova release sopra i vecchi file, mantenendo
  `.env` e `storage/`.

Le modifiche al database si applicano da sole (Docker) oppure con
`php artisan migrate --force`.

## Licenza

Copyright (C) Angelo Carfora.

StallFront è software libero, distribuito con la licenza
[GNU Affero General Public License v3.0](LICENSE). Puoi usarlo gratis per
qualsiasi scopo. Se lo modifichi e lo rendi disponibile ad altri, anche come
servizio online, devi pubblicare il codice con la stessa licenza.

Per usarlo senza questi obblighi (prodotti a codice chiuso, servizi online che
non pubblicano il codice) è disponibile una [licenza commerciale](COMMERCIAL.md).
