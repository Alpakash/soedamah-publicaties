<?php
/**
 * Onderhoudsscript voor een geplande taak (Plesk "Scheduled Tasks" / cron).
 *
 * Draai dit één keer per dag, bijvoorbeeld:
 *     php /var/www/vhosts/.../app/cron.php
 *
 * Het doet twee dingen:
 *   1. Verstuurt eenmalig een vriendelijke hulp-/herinneringsmail naar kopers die
 *      nog steeds op betaling wachten (na 1 dag; instelbaar via 'payment_reminder_hours'
 *      in app/config.php — bijv. 48 voor na 2 dagen). Nooit meer dan één mail per bestelling.
 *   2. Zet bestellingen die al langer dan 3 dagen op betaling wachten op 'verlopen',
 *      zodat het bestellingenoverzicht overzichtelijk blijft.
 *
 * Alleen bruikbaar vanaf de opdrachtregel (niet via de browser).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dit script kan alleen via de opdrachtregel (cron) worden uitgevoerd.\n");
}

require __DIR__ . '/bootstrap.php';

// Aantal dagen dat een onbetaalde bestelling blijft staan voordat hij 'verlopen' wordt.
$expireDays = 3;
// Na hoeveel uur de eenmalige herinneringsmail gaat (instelbaar via config;
// 24 = na 1 dag, 48 = na 2 dagen). Blijft altijd binnen de verlooptermijn.
$reminderHours = max(1, (int) config('payment_reminder_hours', 24));
if ($reminderHours >= $expireDays * 24) {
    $reminderHours = $expireDays * 24 - 1;
}

$reminded = orders_send_payment_reminders($reminderHours, $expireDays);
$expired = orders_expire_stale($expireDays);

$summary = sprintf(
    '%s onderhoud: %d herinnering(en) verstuurd, %d bestelling(en) op verlopen gezet.',
    now(),
    $reminded,
    $expired
);
log_msg('Cron: ' . $reminded . ' herinnering(en), ' . $expired . ' verlopen.');
echo $summary . "\n";
