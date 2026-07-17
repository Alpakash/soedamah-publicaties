# Beheerhandleiding (voor de dagelijkse praktijk)

Het beheer staat op: **https://publicaties.soedamah.nl/admin/**
Log in met het beheerwachtwoord.

## Een nieuw boek online zetten

1. Klik op **+ Nieuwe publicatie**.
2. Vul de **titel**, eventueel een ondertitel, en de **beschrijving** in.
   Een lege regel in de beschrijving begint een nieuwe alinea.
3. Vul de **prijs** in, bijvoorbeeld `12,50`. Vul `0` in om de publicatie
   gratis aan te bieden.
4. Upload de bestanden:
   - **Omslagafbeelding** (JPG of PNG, staande foto van de kaft);
   - **PDF-bestand** van het boek;
   - eventueel ook een **EPUB-bestand** (fijn voor e-readers).
5. Zet het vinkje **Zichtbaar in de shop** aan en klik op **Opslaan**.
6. Klik op **Bekijk de pagina in de shop** om het resultaat te controleren.

Tip: wil je een boek alvast aankondigen? Zet het online zónder bestanden —
bezoekers zien dan "Verschijnt binnenkort" en kunnen nog niet bestellen.
Upload de bestanden op de verschijningsdatum.

## Een boek aanpassen of offline halen

- **Bewerken** → pas tekst, prijs of bestanden aan → **Opslaan**.
- Offline halen: zet het vinkje **Zichtbaar in de shop** uit.
- **Verwijderen** is definitief en haalt ook de bestanden weg; eerdere kopers
  kunnen dan niet meer downloaden. Meestal is offline halen beter.

## Bestellingen

Onder **Bestellingen** zie je wie wat heeft gekocht, voor welk bedrag, en hoe
vaak er is gedownload.

Een koper mailt dat downloaden niet meer lukt (limiet bereikt of link
verlopen)? Klik bij die bestelling op **Reset & verleng** en daarna op
**Mail opnieuw** — de koper ontvangt dan een verse downloadmail.

## Veelgestelde vragen

**Waar komt het geld binnen?**
Stripe ontvangt de betaling en maakt het saldo automatisch over naar de
gekoppelde bankrekening. Overzicht: [dashboard.stripe.com](https://dashboard.stripe.com).

**Kan iemand het bestand doorsturen?**
Technisch kan dat met elk e-book. De shop beperkt het: de link is persoonlijk,
verloopt na een aantal dagen en werkt maar een beperkt aantal keren. In de
mail staat bovendien dat de uitgave voor persoonlijk gebruik is.

**Het wachtwoord is kwijt.**
Verwijder via Plesk (File Manager) in de database-instellingen de regel met
het wachtwoord — of vraag de beheerder van de site (Akash) om dit te doen.
Bij het volgende bezoek aan /admin/ kan een nieuw wachtwoord worden ingesteld.
