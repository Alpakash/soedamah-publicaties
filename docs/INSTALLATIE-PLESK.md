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
   - `admin_email`: het adres dat een melding krijgt bij elke bestelling.

## 7. Eerste keer openen

1. Ga naar `https://publicaties.soedamah.nl/admin/`.
2. **Doe dit direct na installatie**: stel het beheerwachtwoord in (het eerste
   bezoek aan de beheerpagina bepaalt het wachtwoord).
3. Voeg een boek toe en zet een testbestelling door (zie STRIPE-SETUP.md).

## Problemen oplossen

- **"Configuratie ontbreekt"** → stap 6 nog niet gedaan.
- **Upload mislukt / bestand te groot** → verhoog de limieten uit stap 4.
- **Mails komen niet aan** → controleer of het afzenderadres bestaat in Plesk
  en kijk in de spamfolder; het logboek staat in `data/app.log`.
- **Databasefout** → controleer of de map `data/` bestaat en schrijfbaar is
  voor PHP (in Plesk is dat standaard zo).
