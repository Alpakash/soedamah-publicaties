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

    // Dit adres ontvangt een melding bij elke bestelling (leeg = geen meldingen).
    'admin_email' => '',

    // Downloadlimieten per bestelling.
    'download_max'  => 5,   // aantal downloads per bestand (PDF en EPUB apart)
    'download_days' => 90,  // geldigheid van de downloadlink in dagen
];
