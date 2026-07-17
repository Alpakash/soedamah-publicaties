# Stripe instellen

Stripe verzorgt de betalingen (iDEAL en creditcard) en betaalt de opbrengst
uit op de gekoppelde bankrekening.

## 1. Account

1. Maak een account op [stripe.com](https://stripe.com) op naam van de
   verkoper (de auteur), met diens e-mailadres.
2. Doorloop de **activatie** (Activate payments): bedrijfs-/persoonsgegevens,
   KvK-nummer indien van toepassing, en de **IBAN voor uitbetalingen**.
   Tot de activatie compleet is kun je alleen testbetalingen doen.

## 2. iDEAL aanzetten

1. Dashboard → **Settings** → **Payments** → **Payment methods**.
2. Zet **iDEAL** aan (naast Cards). De shop toont automatisch alle
   betaalmethoden die hier aanstaan.

## 3. API-sleutels invullen

1. Dashboard → **Developers** → **API keys**.
2. Kopieer de **Secret key** (begint met `sk_test_...` in testmodus).
3. Zet die in `app/config.php` bij `stripe_secret_key`.

## 4. Webhook aanmaken

De webhook zorgt dat een bestelling óók wordt afgerond als de koper zijn
browser sluit direct na het betalen.

1. Dashboard → **Developers** → **Webhooks** → **Add endpoint**.
2. Endpoint-URL: `https://publicaties.soedamah.nl/webhook.php`
3. Events selecteren:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`
4. Kopieer na het aanmaken de **Signing secret** (`whsec_...`) en zet die in
   `app/config.php` bij `stripe_webhook_secret`.

> Let op: testmodus en livemodus hebben **elk hun eigen** sleutels én webhook.
> Bij de overstap naar live moet je stap 3 en 4 dus herhalen met de
> live-gegevens.

## 5. Testbetaling doen

1. Zorg dat `stripe_secret_key` de **test**-sleutel is.
2. Zet in het beheer een boek online met een prijs en een PDF.
3. Klik in de shop op **Nu kopen** en betaal met:
   - Testkaart: `4242 4242 4242 4242`, willekeurige toekomstige vervaldatum,
     willekeurige CVC; of
   - **iDEAL (test)**: kies iDEAL en gebruik de testbank die Stripe toont.
4. Controleer dat je op de bedanktpagina komt, kunt downloaden, en de mail
   ontvangt. De bestelling verschijnt in **Beheer → Bestellingen**.

## 6. Live gaan

1. Rond de accountactivatie af (stap 1).
2. Zet de **live** secret key (`sk_live_...`) in `app/config.php`.
3. Maak in **livemodus** een webhook aan (stap 4) en zet de nieuwe
   `whsec_...` in de config.
4. Doe zelf één echte aankoop van bijv. € 1 ter controle (dat bedrag komt
   minus Stripe-kosten gewoon op de rekening terecht).

## Kosten en uitbetaling

- Stripe rekent per transactie een vaste + procentuele vergoeding
  (iDEAL is goedkoper dan creditcard); er zijn geen maandkosten.
- Uitbetalingen naar de bankrekening gebeuren automatisch (standaard
  dagelijks/wekelijks, instelbaar onder **Settings → Payouts**).
- Zet eventueel **e-mailbonnen** aan onder **Settings → Customer emails**,
  dan stuurt Stripe de koper automatisch een betaalbewijs.
