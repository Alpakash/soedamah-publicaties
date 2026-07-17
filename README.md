# Publicaties van Lachman Soedamah — webshop

Webshop voor digitale publicaties (PDF en EPUB) op **publicaties.soedamah.nl**.
Bezoekers rekenen af via Stripe (iDEAL of creditcard) en ontvangen direct een
beveiligde downloadlink — op de bedanktpagina én per e-mail.

## Waarom deze techniek

De shop is bewust gebouwd in **kaal PHP 8 met SQLite**, zonder frameworks,
Composer of build-stappen:

- Draait direct op de bestaande Plesk-hosting bij Argeweb — uploaden en klaar.
- Geen extra abonnementen (geen Vercel, geen externe database of opslag).
- Vrijwel geen onderhoud: geen dependencies die verouderen.
- Eén map (`data/`) bevat alle uploads en de database — makkelijk te back-uppen.

## Functies

- **Shop**: overzicht en detailpagina per boek, met omslag, beschrijving en prijs.
- **Betalen**: Stripe Checkout met iDEAL en creditcard; bedragen komen binnen op
  de gekoppelde bankrekening via Stripe-uitbetalingen.
- **Downloads**: beveiligde links met een token; standaard 90 dagen geldig en
  maximaal 5 downloads per bestand (instelbaar in `app/config.php`).
  De bestanden zelf staan buiten de webroot en zijn nooit direct te benaderen.
- **Gratis publicaties**: prijs 0 invullen → bezoekers laten hun e-mailadres
  achter en krijgen direct een downloadlink.
- **Beheer** (`/admin/`): boeken toevoegen/bewerken, bestanden uploaden,
  bestellingen inzien, downloadlimiet resetten en mails opnieuw versturen.
  Bij het eerste bezoek stel je het beheerwachtwoord in.
- **E-mail**: koper krijgt de downloadlinks per mail; de beheerder krijgt een
  melding bij elke bestelling (instelbaar via `admin_email`).

## Mappen

```
public/    ← webroot (hier wijst het domein naartoe)
app/       ← applicatiecode + config (niet via de browser bereikbaar)
data/      ← database, uploads en logboek (niet via de browser bereikbaar)
docs/      ← installatie- en beheerhandleidingen
```

## Lokaal draaien

```bash
cp app/config.example.php app/config.php   # vul je Stripe-testsleutel in
php -S localhost:8080 -t public
```

Open daarna http://localhost:8080 en http://localhost:8080/admin/ (bij het
eerste bezoek stel je het beheerwachtwoord in).

## Installatie & configuratie

1. [docs/INSTALLATIE-PLESK.md](docs/INSTALLATIE-PLESK.md) — subdomein aanmaken
   in Plesk (Argeweb), code uploaden, PHP-instellingen, SSL.
2. [docs/STRIPE-SETUP.md](docs/STRIPE-SETUP.md) — Stripe-account, sleutels,
   iDEAL, webhook en testbetaling.
3. [docs/BEHEER-HANDLEIDING.md](docs/BEHEER-HANDLEIDING.md) — korte handleiding
   voor het dagelijkse beheer (boek toevoegen, bestellingen bekijken).

## Goed om te weten

- **BTW**: op digitale boeken geldt in Nederland het verlaagde tarief van 9%.
  De prijzen in de shop zijn inclusief. Overleg met de boekhouder of de
  kleineondernemersregeling (KOR) van toepassing is.
- **Delen tegengaan**: downloads zijn gekoppeld aan een persoonlijk token met
  een limiet en vervaldatum, en elke mail vermeldt dat de uitgave voor
  persoonlijk gebruik is. Honderd procent kopieerbeveiliging bestaat niet —
  ook niet met "alleen online lezen" — dus dit is de gebruikelijke,
  klantvriendelijke aanpak voor e-books.
- **Back-up**: de map `data/` bevat alles (database + boeken). Neem die mee in
  de Plesk-back-ups of download hem af en toe.
