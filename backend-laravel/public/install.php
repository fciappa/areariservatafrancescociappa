<?php
/**
 * One-time Composer installer — DELETE THIS FILE after first use.
 *
 * Usage: visit https://<your-domain>/install.php?token=<INSTALL_TOKEN>
 * Set INSTALL_TOKEN to a random string of your choice before deploying.
 */

const INSTALL_TOKEN = 'Cqt3kXAt5u9n3oIr+pgsJCDvy/Vm5Xwh/uqeiseoFDk=PS';
const APP_ROOT      = __DIR__ . '/..';

// ── Security check ──────────────────────────────────────────────────────────
if (!isset($_GET['token']) || !hash_equals(INSTALL_TOKEN, $_GET['token'])) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

// ── Check exec() availability ────────────────────────────────────────────────
if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
    exit("ERROR: exec() is disabled on this server.\nInstall dependencies manually via SSH or cPanel Terminal:\n  composer install --no-dev --optimize-autoloader\n");
}

echo "=== Composer Install ===\n\n";

// ── Download composer.phar if missing ───────────────────────────────────────
$composerPhar = APP_ROOT . '/composer.phar';

if (!file_exists($composerPhar)) {
    echo "Downloading composer.phar...\n";
    $sig = file_get_contents('https://composer.github.io/installer.sig');
    file_put_contents(APP_ROOT . '/composer-setup.php', file_get_contents('https://getcomposer.org/installer'));

    if (hash_file('sha384', APP_ROOT . '/composer-setup.php') !== trim($sig)) {
        unlink(APP_ROOT . '/composer-setup.php');
        exit("ERROR: Composer installer signature mismatch.\n");
    }

    exec('php ' . escapeshellarg(APP_ROOT . '/composer-setup.php') . ' --install-dir=' . escapeshellarg(APP_ROOT) . ' --filename=composer.phar 2>&1', $out, $code);
    unlink(APP_ROOT . '/composer-setup.php');

    if ($code !== 0) {
        exit("ERROR downloading Composer:\n" . implode("\n", $out) . "\n");
    }
    echo "Composer downloaded.\n\n";
}

// ── Run composer install ─────────────────────────────────────────────────────
echo "Running composer install --no-dev --optimize-autoloader...\n\n";
exec('cd ' . escapeshellarg(APP_ROOT) . ' && php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1', $out, $code);

echo implode("\n", $out) . "\n\n";

if ($code === 0) {
    echo "=== Done. DELETE this file from the server now. ===\n";
} else {
    echo "=== ERROR: exit code $code ===\n";
}
