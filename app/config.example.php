<?php
/**
 * Kopieer dit bestand naar app/config.php en vul de waarden in.
 * app/config.php staat in .gitignore en komt dus nooit in Git terecht.
 */
return [
    // Volledige URL van de shop, zonder slash aan het einde.
    // Leeg laten = automatisch detecteren (handig bij lokaal testen).
    'base_url' => 'https://publicaties.soedamah.nl',

    // Stripe geheime sleutel: Dashboard -> Developers -> API keys.
    // Begin met sk_test_... en vervang door sk_live_... na een geslaagde testbetaling.
    'stripe_secret_key' => '',

    // Stripe webhook signing secret (whsec_...): Dashboard -> Developers -> Webhooks.
    // Endpoint-URL: https://publicaties.soedamah.nl/webhook.php
    'stripe_webhook_secret' => '',

    // Afzender van de e-mails met downloadlinks.
    'mail_from' => 'soedamah@soedamah.nl',
    'mail_from_name' => 'Lachman Soedamah',

    // Dit adres ontvangt een melding bij elke bestelling. Laat je dit leeg, dan
    // gaat de melding automatisch naar soedamah@soedamah.nl.
    'admin_email' => 'soedamah@soedamah.nl',

    // Voor bestellingen met een verzendadres (fysieke boeken) gaat de meldmail naar dit
    // adres i.p.v. naar admin_email, met admin_email in de CC. Leeg laten = gewoon naar
    // admin_email, zoals bij digitale bestellingen.
    'shipping_notify_email' => '',

    // Downloadlimieten per bestelling.
    'download_max'  => 5,   // aantal downloads per bestand (PDF en EPUB apart)
    'download_days' => 90,  // geldigheid van de downloadlink in dagen

    // Na hoeveel uur de eenmalige herinneringsmail gaat naar kopers die nog op
    // betaling wachten. 48 = na 2 dagen, 24 = na 1 dag. (Vereist de geplande taak
    // app/cron.php; zie docs/INSTALLATIE-PLESK.md.)
    'payment_reminder_hours' => 48,
];
