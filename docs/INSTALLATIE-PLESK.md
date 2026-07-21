# Installatie op Plesk (Argeweb)

Deze handleiding zet de shop live op **publicaties.soedamah.nl**.
Reken op 20–30 minuten.

## 1. Subdomein aanmaken

1. Log in op Plesk via Argeweb.
2. Ga naar **Websites & Domains** → **Add Subdomain**.
3. Subdomeinnaam: `publicaties` (onder `soedamah.nl`).
4. Document root: laat staan wat Plesk voorstelt (bijv.
   `publicaties.soedamah.nl`); die passen we in stap 3 aan.

Omdat het subdomein onder hetzelfde domein valt, regelt Plesk de DNS meestal
automatisch. Beheer je de DNS ergens anders, voeg dan een A-record voor
`publicaties` toe dat naar het IP-adres van de hosting wijst.

## 2. Code uploaden

**Optie A — Git (aanbevolen, makkelijk bijwerken):**

1. In Plesk: **Websites & Domains** → subdomein → **Git** (extensie).
2. Repository-URL: `https://github.com/Alpakash/soedamah-publicaties.git`,
   branch `main`.
3. Deploy naar de map van het subdomein.

**Optie B — ZIP:**

1. Download de repository als ZIP via GitHub.
2. Upload en pak uit via Plesk **File Manager** in de map van het subdomein.

## 3. Document root op `public` zetten

1. **Websites & Domains** → subdomein → **Hosting Settings**.
2. Zet **Document root** op de map `public` binnen de geüploade code
   (bijv. `publicaties.soedamah.nl/public`).
3. Opslaan.

> Lukt dit niet? De meegeleverde `.htaccess` in de hoofdmap stuurt alle
> verkeer automatisch door naar `public/`, dus de site werkt ook zonder deze
> stap. De document root aanpassen blijft netter.

## 4. PHP instellen

Bij het subdomein → **PHP Settings**:

- **PHP-versie**: 8.1 of hoger (8.2/8.3 aanbevolen).
- **upload_max_filesize**: `256M` (boeken kunnen groot zijn)
- **post_max_size**: `256M`
- **memory_limit**: `256M` is ruim voldoende.

De benodigde extensies (pdo_sqlite, curl, mbstring, fileinfo) staan op Plesk
standaard aan.

## 5. SSL-certificaat

1. **Websites & Domains** → subdomein → **SSL/TLS Certificates**.
2. Vraag een gratis **Let's Encrypt**-certificaat aan voor
   `publicaties.soedamah.nl`.
3. Zet **Redirect from HTTP to HTTPS** aan.

## 6. Configuratie aanmaken

1. Kopieer in de File Manager `app/config.example.php` naar `app/config.php`.
2. Vul in:
   - `base_url`: `https://publicaties.soedamah.nl`
   - `stripe_secret_key` en `stripe_webhook_secret`: zie
     [STRIPE-SETUP.md](STRIPE-SETUP.md)
   - `mail_from`: bijv. `publicaties@soedamah.nl` — maak dit adres aan in Plesk
     (**Mail** → **Create Email Address**) zodat mails niet als spam worden
     gezien.
   - `admin_email`: het adres dat een melding krijgt bij elke bestelling. Laat je
     dit leeg, dan gaan de meldingen automatisch naar `soedamah@soedamah.nl`.

## 7. Eerste keer openen

1. Ga naar `https://publicaties.soedamah.nl/admin/`.
2. **Doe dit direct na installatie**: stel het beheerwachtwoord in (het eerste
   bezoek aan de beheerpagina bepaalt het wachtwoord).
3. Voeg een boek toe en zet een testbestelling door (zie STRIPE-SETUP.md).

## 8. Automatische herinneringen & opschonen (geplande taak)

De shop kan dagelijks automatisch:

- **eenmalig** een vriendelijke hulp-/herinneringsmail sturen naar kopers die nog
  op betaling wachten (standaard na 2 dagen; wil je 1 dag, zet dan
  `payment_reminder_hours` op `24` in `app/config.php`) — nooit meer dan één mail
  per bestelling;
- bestellingen die al langer dan **3 dagen** op betaling wachten op **Verlopen**
  zetten, zodat het bestellingenoverzicht overzichtelijk blijft.

Het opschonen gebeurt ook al vanzelf zodra je het bestellingenoverzicht in het
beheer opent. De **herinneringsmail** heeft echter een geplande taak nodig:

1. Ga in Plesk naar **Websites & Domains** → (het subdomein) → **Scheduled Tasks**
   (Geplande taken).
2. Klik **Add Task** en kies als type **Run a PHP script**.
3. Vul bij het scriptpad in: `app/cron.php`
4. Zet de planning op **dagelijks** (bijv. elke dag om 09:00). Eén keer per dag is
   ruim voldoende.
5. Bewaar. Je kunt de taak testen met de knop **Run Now**; er verschijnt dan een
   regel in `data/app.log`.

> Kan Plesk geen PHP-script kiezen? Gebruik dan als commando bijvoorbeeld
> `/opt/plesk/php/8.2/bin/php <volledig pad>/app/cron.php` (pas het PHP-pad aan de
> geïnstalleerde versie aan). Het script weigert draaien via de browser en is dus
> veilig.

## Problemen oplossen

- **"Configuratie ontbreekt"** → stap 6 nog niet gedaan.
- **Upload mislukt / bestand te groot** → verhoog de limieten uit stap 4.
- **Mails komen niet aan** → controleer of het afzenderadres bestaat in Plesk
  en kijk in de spamfolder; het logboek staat in `data/app.log`.
- **Databasefout** → controleer of de map `data/` bestaat en schrijfbaar is
  voor PHP (in Plesk is dat standaard zo).
