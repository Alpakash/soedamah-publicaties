<?php
/**
 * Onderhoudsscript voor een geplande taak (Plesk "Scheduled Tasks" / cron).
 *
 * Draai dit één keer per dag, bijvoorbeeld:
 *     php /var/www/vhosts/.../app/cron.php
 *
 * Het doet twee dingen:
 *   1. Verstuurt eenmalig een vriendelijke hulp-/herinneringsmail naar kopers die
 *      na ~1 dag nog steeds op betaling wachten (nooit meer dan één mail per bestelling).
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

$reminded = orders_send_payment_reminders(24, 3);
$expired = orders_expire_stale(3);

$summary = sprintf(
    '%s onderhoud: %d herinnering(en) verstuurd, %d bestelling(en) op verlopen gezet.',
    now(),
    $reminded,
    $expired
);
log_msg('Cron: ' . $reminded . ' herinnering(en), ' . $expired . ' verlopen.');
echo $summary . "\n";
