<?php
/**
 * Daily batch sender — run this from a cPanel Cron Job once a day.
 * Sends up to DAILY_LIMIT pending colleges (default 300, matching Brevo's
 * free-plan daily cap) and updates their status, same as the "Send All
 * Pending Emails" button but without needing anyone to click it.
 *
 * cPanel > Cron Jobs > Add New Cron Job:
 *   Command: /usr/local/bin/php /home/USERNAME/public_html/cron_send_daily.php
 *   Schedule: once a day, e.g. 0 6 * * *  (6 AM daily)
 *
 * Run this file itself only from the command line / cron — never expose it
 * over HTTP (it has no login check).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line (cron).');
}

define('DAILY_LIMIT', 300);

// Reuse the same config + functions as the main app without triggering
// its HTTP request-handling block (that block only runs for POST requests).
chdir(__DIR__);
ob_start();
require __DIR__ . '/liberty_email_system.php';
ob_end_clean(); // discard the dashboard HTML that the include prints as a side effect

echo "[" . date('Y-m-d H:i:s') . "] Starting daily batch send (limit " . DAILY_LIMIT . ")...\n";

$result = $conn->query("SELECT id FROM colleges WHERE status = 'pending' LIMIT " . DAILY_LIMIT);

$sent = 0;
$failed = 0;

while ($row = $result->fetch_assoc()) {
    ob_start();
    send_single_email($row['id'], $conn);
    $response = json_decode(ob_get_clean());

    if ($response && $response->status === 'success') {
        $sent++;
        echo "  OK   #{$row['id']}\n";
    } else {
        $failed++;
        $msg = $response->message ?? 'unknown error';
        echo "  FAIL #{$row['id']}: $msg\n";
    }

    sleep(1); // rate limiting, same as the manual "Send All" button
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Sent: $sent, Failed: $failed\n";
