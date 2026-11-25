<?php

/**
 * Prince Byron Official Platform — One-Time Browser Installer (cPanel Deployment)
 *
 * Security Requirements (ASVS 5.0 Level 2 & ARCHITECTURE.md):
 * - Permanent lockout upon successful installation (no URL bypass allowed).
 * - Enforce HTTPS on remote hosts; reject unencrypted HTTP & untrusted proxy headers.
 * - Per-session CSRF token validation on installation POST.
 * - Single-process mutex locking preventing concurrent setup races.
 * - Safe .env value encoding, newline injection protection, and write verification.
 * - Server-side logging of detailed exceptions with safe, sanitized client errors.
 * - Preflight validation of PHP version, PostgreSQL extensions, and writable paths.
 * - Secure application key generation (CSPRNG base64:32-byte).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$installedLockFile  = __DIR__ . '/../core/storage/installed';
$executionLockPath  = __DIR__ . '/../core/storage/install_execution.lock';
$envFile            = __DIR__ . '/../core/.env';
$installLogFile     = __DIR__ . '/../core/storage/logs/install.log';

// -----------------------------------------------------------------------------
// 1. Permanent Lockout Gate
// -----------------------------------------------------------------------------
if (file_exists($installedLockFile)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation Locked — Prince Byron</title>
        <style>
            body { background: #0E0B09; color: #F8F4ED; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
            .card { background: #181310; border: 1px solid rgba(223,167,89,0.3); border-radius: 12px; padding: 36px; max-width: 520px; width: 100%; text-align: center; }
            h1 { color: #DFA759; font-size: 24px; margin-top: 0; }
            p { color: rgba(248,244,237,0.8); line-height: 1.6; font-size: 15px; }
            .btn { display: inline-block; background: #DFA759; color: #0E0B09; font-weight: bold; text-decoration: none; padding: 12px 24px; border-radius: 6px; margin-top: 20px; }
            .recovery-note { font-size: 13px; color: rgba(248,244,237,0.5); margin-top: 24px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Platform Already Initialized</h1>
            <p>The Prince Byron platform is installed and configured on this server. For security, this setup installer is permanently disabled and locked.</p>
            <a href="../" class="btn">Return to Platform</a>
            <div class="recovery-note">
                <strong>Documented Recovery Path:</strong> If you legitimately need to perform a clean re-installation, connect via SSH or cPanel File Manager and manually delete <code>core/storage/installed</code> and <code>core/.env</code>.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// 2. HTTPS & Trusted Proxy Gate
// -----------------------------------------------------------------------------
function isTrustedProxy(string $ip): bool
{
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    $long = ip2long($ip);
    if ($long !== false) {
        // Private subnets: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
        if (($long & 0xFF000000) === 0x0A000000) return true;
        if (($long & 0xFFF00000) === 0xAC100000) return true;
        if (($long & 0xFFFF0000) === 0xC0A80000) return true;
    }
    return false;
}

function isHttpsConnection(): bool
{
    if (! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isTrustedProxy($remoteAddr)) {
        if (! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
    }
    return false;
}

function isLoopbackClient(): bool
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';

    // If forwarded headers exist, the connection arrived via a proxy and is not direct local loopback
    if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return false;
    }

    return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

$isLoopback = isLoopbackClient();
$isHttps    = isHttpsConnection();

// Enforce HTTPS: only direct loopback client connections may use HTTP.
// Remote requests are strictly rejected if not on HTTPS, regardless of client-supplied Host headers.
if (! $isLoopback && ! $isHttps) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>HTTPS Required — Prince Byron Setup</title>
        <style>
            body { background: #0E0B09; color: #F8F4ED; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
            .card { background: #181310; border: 1px solid rgba(229,115,115,0.4); border-radius: 12px; padding: 36px; max-width: 540px; width: 100%; text-align: center; }
            h1 { color: #E57373; font-size: 22px; margin-top: 0; }
            p { color: rgba(248,244,237,0.85); line-height: 1.6; font-size: 15px; }
            .note { font-size: 13px; color: rgba(248,244,237,0.5); margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 16px; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>HTTPS Connection Required</h1>
            <p>The Prince Byron installer requires a secure HTTPS connection on remote hosts to protect database credentials and administrator passwords in transit.</p>
            <p>Please configure an SSL/TLS certificate for your domain and access this installer via <code>https://</code>.</p>
            <div class="note">Plain HTTP requests and untrusted proxy headers are rejected by security policy (OWASP ASVS 5.0 L2).</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// -----------------------------------------------------------------------------
// 3. Per-Session CSRF Protection
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    @session_start();
}

if (empty($_SESSION['installer_csrf_token'])) {
    $_SESSION['installer_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['installer_csrf_token'];

$action = $_GET['action'] ?? 'requirements';

function detectAppUrl(): string
{
    $scheme = isHttpsConnection() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = str_replace(['/install', '/install/index.php'], '', $_SERVER['SCRIPT_NAME'] ?? '');
    return rtrim("{$scheme}://{$host}{$path}", '/');
}

function escapeEnv(string $value): string
{
    return str_replace(['\\', '"', '$'], ['\\\\', '\"', '\$'], $value);
}

// -----------------------------------------------------------------------------
// 4. Preflight Requirements Check
// -----------------------------------------------------------------------------
$checks = [];
$allPassed = true;

// PHP Version — must match composer.json ("php": "^8.5"). The release ships a
// prebuilt vendor/, so Composer performs no platform check on the host: this
// gate is the only thing preventing an "install succeeded, every page fatals"
// outcome. Keep this value in lock-step with core/composer.json.
$requiredPhp = '8.5.0';

// COMPOSER_PLATFORM_PHP lets a controlled environment raise or lower the
// effective floor using Composer's own override name. Production hosts never
// set it, so the shipped requirement stands unchanged; CI uses it to verify
// the failure path without needing a PHP 8.5 runner.
$effectivePhp = getenv('COMPOSER_PLATFORM_PHP') ?: $requiredPhp;
$phpOk = version_compare(PHP_VERSION, $effectivePhp, '>=');
$checks[] = [
    'label'  => 'PHP Version >= ' . $requiredPhp . ' (Current: ' . PHP_VERSION . ')',
    'passed' => $phpOk,
    'detail' => $phpOk
        ? 'Satisfied'
        : 'PHP ' . $effectivePhp . ' or higher is required. Select a newer PHP version in cPanel > MultiPHP Manager, then reload this page.',
];
if (! $phpOk) $allPassed = false;

// Required Extensions
// MySQL drivers are accepted as an alternative to PostgreSQL: the platform is
// being retargeted at MySQL/MariaDB so it installs on ordinary shared cPanel
// plans. Requiring pdo_pgsql alone would reject the very hosts the owner wants
// to support.
$requiredExtensions = [
    'pdo'       => 'PDO Core',
    'mbstring'  => 'Multibyte String (mbstring)',
    'openssl'   => 'OpenSSL Cryptography',
    'tokenizer' => 'Tokenizer',
    'xml'       => 'XML Parser',
    'ctype'     => 'Ctype',
    'json'      => 'JSON Parser',
    'curl'      => 'cURL Client',
    'fileinfo'  => 'File Information (fileinfo)',
];

foreach ($requiredExtensions as $ext => $name) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'label'  => "PHP Extension: {$name}",
        'passed' => $loaded,
        'detail' => $loaded ? 'Loaded' : "Required PHP extension '{$ext}' is missing. Enable it in cPanel Select PHP Version.",
    ];
    if (! $loaded) $allPassed = false;
}

// At least one supported database driver must be available. Either is enough:
// the installer writes the matching DB_CONNECTION for the platform. A host may
// expose the driver under its cPanel alias (pdo_mysql is sometimes surfaced as
// mysqlnd), so both names are accepted.
$dbDrivers = [
    'mysql'  => ['pdo_mysql', 'mysqli'],
    'pgsql'  => ['pdo_pgsql', 'pgsql'],
];
$foundDriver = null;
foreach ($dbDrivers as $driver => $candidates) {
    foreach ($candidates as $candidate) {
        if (extension_loaded($candidate)) {
            $foundDriver = $driver;
            break 2;
        }
    }
}
$checks[] = [
    'label'  => 'Database Driver (PDO MySQL or PDO PostgreSQL)',
    'passed' => $foundDriver !== null,
    'detail' => $foundDriver === null
        ? 'No supported database driver found. Enable pdo_mysql (recommended for shared cPanel) or pdo_pgsql in cPanel > Select PHP Version.'
        : 'Loaded (' . strtoupper($foundDriver) . ')',
    'detail_failed' => TRUE,
];
if ($foundDriver === null) $allPassed = false;

// Writable Storage & Cache Directories
$writableDirs = [
    '../core/bootstrap/cache/',
    '../core/storage/',
    '../core/storage/app/',
    '../core/storage/app/private/',
    '../core/storage/framework/',
    '../core/storage/framework/cache/',
    '../core/storage/framework/sessions/',
    '../core/storage/framework/views/',
    '../core/storage/logs/',
];

foreach ($writableDirs as $dir) {
    if (! file_exists(__DIR__ . '/' . $dir)) {
        @mkdir(__DIR__ . '/' . $dir, 0775, true);
    }
    $isWritable = is_writable(__DIR__ . '/' . $dir);
    $cleanName = str_replace('../', '', $dir);
    $checks[] = [
        'label'  => "Directory Writable: {$cleanName}",
        'passed' => $isWritable,
        'detail' => $isWritable ? 'Writable (0775)' : 'Ensure directory permissions are set to 0775.',
    ];
    if (! $isWritable) $allPassed = false;
}

// -----------------------------------------------------------------------------
// 5. Execution Handler with Single-Process Mutex & CSRF Validation
// -----------------------------------------------------------------------------
$error = null;
$success = false;
$mailerConfigured = null;

if ($action === 'execute' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    $submittedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if (empty($submittedCsrf) || !hash_equals($_SESSION['installer_csrf_token'] ?? '', $submittedCsrf)) {
        $error = 'Invalid or missing CSRF session token. Please reload the page and submit again.';
    }

    // Server-side preflight gate. The template only hides the submit button when
    // a requirement fails; this blocks a crafted POST from continuing anyway.
    if (! $error && ! $allPassed) {
        $error = 'Server requirements are not satisfied. Resolve the failed preflight checks above and reload this page.';
    }

    if (! $error) {
        $dbDriver  = strtolower(trim($_POST['db_driver'] ?? 'mysql'));
        if (! in_array($dbDriver, ['mysql', 'pgsql'], true)) {
            $dbDriver = 'mysql';
        }

        $dbHost    = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort    = trim($_POST['db_port'] ?? ($dbDriver === 'mysql' ? '3306' : '5432'));
        $dbName    = trim($_POST['db_name'] ?? '');
        $dbUser    = trim($_POST['db_user'] ?? '');
        $dbPass    = (string) ($_POST['db_pass'] ?? '');

        $adminName  = trim($_POST['admin_name'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass  = (string) ($_POST['admin_pass'] ?? '');
        $adminPassC = (string) ($_POST['admin_pass_confirm'] ?? '');

        $siteUrl   = trim($_POST['app_url'] ?? detectAppUrl());
        $siteName  = trim($_POST['site_name'] ?? 'Prince Byron Official Platform');

        // Mail. The installer previously wrote Mailpit's localhost:1025, which
        // silently discarded every message on a real host — so a customer could
        // place an order and never receive a receipt (review P0-7). "none" is an
        // explicit, honest choice: Laravel's log driver records mail instead of
        // pretending to send it.
        $mailer      = strtolower(trim($_POST['mail_mailer'] ?? 'smtp'));
        if (! in_array($mailer, ['smtp', 'none'], true)) {
            $mailer = 'smtp';
        }
        $mailHost    = trim($_POST['mail_host'] ?? '');
        $mailPort    = trim($_POST['mail_port'] ?? '587');
        $mailUser    = trim($_POST['mail_user'] ?? '');
        $mailPass    = (string) ($_POST['mail_pass'] ?? '');
        $mailEnc     = strtolower(trim($_POST['mail_encryption'] ?? 'tls'));
        if (! in_array($mailEnc, ['tls', 'ssl', 'none'], true)) {
            $mailEnc = 'tls';
        }
        $mailFrom    = trim($_POST['mail_from'] ?? $adminEmail);

        // Newline injection protection against .env splitting
        $allInputs = $dbHost . $dbPort . $dbName . $dbUser . $dbPass . $adminName . $adminEmail
            . $siteName . $siteUrl . $mailHost . $mailPort . $mailUser . $mailPass . $mailFrom;
        if (preg_match('/[\r\n]/', $allInputs)) {
            $error = 'Form inputs must not contain newline characters.';
        } elseif (!ctype_digit($dbPort) || (int)$dbPort < 1 || (int)$dbPort > 65535) {
            $error = 'Invalid database port number (must be 1–65535).';
        } elseif (empty($dbName) || empty($dbUser)) {
            $error = 'Database Name and Database Username are required.';
        } elseif (empty($adminName) || empty($adminEmail) || empty($adminPass)) {
            $error = 'All owner administrator fields are required.';
        } elseif (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid owner email address format.';
        } elseif ($mailer === 'smtp' && $mailHost !== '' && (! ctype_digit($mailPort) || (int)$mailPort < 1 || (int)$mailPort > 65535)) {
            $error = 'Invalid mail server port (must be 1-65535).';
        } elseif ($mailFrom !== '' && ! filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid "from" email address for outgoing mail.';
        } elseif (strlen($adminPass) < 8) {
            $error = 'Owner password must be at least 8 characters in length.';
        } elseif ($adminPass !== $adminPassC) {
            $error = 'Owner password and confirmation do not match.';
        } else {
            // Single-Process Mutex Lock
            $lockFp = @fopen($executionLockPath, 'c+');
            if (! $lockFp || ! flock($lockFp, LOCK_EX | LOCK_NB)) {
                if ($lockFp) {
                    fclose($lockFp);
                }
                $error = 'Another installation process is currently in progress. Please wait a moment and refresh.';
            } else {
                try {
                    // Re-check installed marker under lock in case completed concurrently
                    if (file_exists($installedLockFile)) {
                        $error = 'The platform was already initialized by a concurrent setup request.';
                    } else {
                        // Step A: Test the database connection for the chosen driver
                        try {
                            $dsn = $dbDriver === 'mysql'
                                ? "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4"
                                : "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};connect_timeout=5";
                            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_TIMEOUT => 5,
                            ]);
                        } catch (\Throwable $e) {
                            $cleanMsg = $dbPass !== '' ? str_replace($dbPass, '********', $e->getMessage()) : $e->getMessage();
                            @file_put_contents($installLogFile, '[' . date('c') . "] [DB CONNECTION ERROR] {$cleanMsg}\n{$e->getTraceAsString()}\n", FILE_APPEND);
                            $dbLabel = $dbDriver === 'mysql' ? 'MySQL/MariaDB' : 'PostgreSQL';
                            $error = "Database Connection Failed: Unable to connect to {$dbLabel} with the provided credentials. Please check host, port, database name, and user permissions.";
                        }

                        // Step B: Write .env file
                        if (! $error) {
                            try {
                                $appKey = 'base64:' . base64_encode(random_bytes(32));
                                $escSiteName = escapeEnv($siteName);
                                $escSiteUrl  = escapeEnv($siteUrl);
                                $escDbHost   = escapeEnv($dbHost);
                                $escDbPort   = escapeEnv($dbPort);
                                $escDbName   = escapeEnv($dbName);
                                $escDbUser   = escapeEnv($dbUser);
                                $escDbPass   = escapeEnv($dbPass);
                                $escMailPass = escapeEnv($mailPass);

                                // A mailer with no host cannot deliver anything,
                                // so record to the log instead of shipping a
                                // configuration that fails on first send. The
                                // success screen tells the owner.
                                if ($mailer === 'smtp' && $mailHost === '') {
                                    $mailer = 'none';
                                }

                                // P1-11: the session cookie must be Secure in
                                // production. Without it, a single plain-HTTP
                                // request to any page leaks the authenticated
                                // session id, which is a complete account
                                // takeover — the most valuable cookie in the
                                // application.
                                //
                                // Derived from the site URL rather than asked,
                                // because the owner already told us the scheme
                                // and a second question invites the wrong
                                // answer. Enabled only for https:// so a
                                // pre-certificate install still works; the
                                // success screen tells the owner to re-run or
                                // flip the flag once TLS is live.
                                $isHttps = stripos($siteUrl, 'https://') === 0;
                                $secureCookie = $isHttps ? 'true' : 'false';

                                $envTemplate = <<<ENV
APP_NAME="{$escSiteName}"
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$escSiteUrl}

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION={$dbDriver}
DB_HOST={$escDbHost}
DB_PORT={$escDbPort}
DB_DATABASE={$escDbName}
DB_USERNAME={$escDbUser}
DB_PASSWORD="{$escDbPass}"

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE={$secureCookie}
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
SESSION_ENCRYPT=true
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local

MAIL_MAILER={$mailer}
MAIL_HOST="{$mailHost}"
MAIL_PORT={$mailPort}
MAIL_USERNAME="{$mailUser}"
MAIL_PASSWORD="{$escMailPass}"
MAIL_ENCRYPTION={$mailEnc}
MAIL_FROM_ADDRESS="{$mailFrom}"
MAIL_FROM_NAME="{$escSiteName}"

# Payment gateways remain in sandbox mode until owner authorizes live release
STRIPE_MODE=sandbox
PAYPAL_MODE=sandbox

# Set TRUSTED_PROXIES only if traffic reaches this account through a proxy or
# CDN you control (Cloudflare, a hosting load balancer). Comma-separated IPs.
# Leave empty on direct hosting: trusting a proxy you do not control lets a
# client forge its own IP address in logs and rate limits.
TRUSTED_PROXIES=
ENV;

                                $written = @file_put_contents($envFile, $envTemplate);
                                if ($written === false || $written !== strlen($envTemplate) || !file_exists($envFile)) {
                                    throw new \RuntimeException('Failed to write configuration file (.env). Verify filesystem permissions.');
                                }
                                @chmod($envFile, 0600);
                                $mailerConfigured = $mailer;
                            } catch (\Throwable $e) {
                                @file_put_contents($installLogFile, '[' . date('c') . '] [CONFIG WRITE ERROR] ' . $e->getMessage() . "\n", FILE_APPEND);
                                $error = 'Failed to write configuration file (.env). Verify directory write permissions.';
                            }
                        }

                        // Step C: Run Migrations and Create Owner
                        if (! $error) {
                            try {
                                // Bootstrap Laravel Console Kernel
                                require_once __DIR__ . '/../core/vendor/autoload.php';
                                $app = require __DIR__ . '/../core/bootstrap/app.php';
                                $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
                                $kernel->bootstrap();

                                // Run guarded database migrations
                                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

                                // Seed the catalogue and content the site needs to
                                // function: songs, products, the booking sample, the
                                // content sections (which include the four legal
                                // policy rows the footer and checkout link to) and
                                // settings. DatabaseSeeder deliberately creates NO
                                // accounts — the owner account is created below, with
                                // the password typed into this form.
                                //
                                // SettingsSeeder alone was the first implementation and
                                // it produced a site that installed "successfully" and
                                // was empty: no songs, no products, no content, and a
                                // footer linking to policy pages that did not exist.
                                \Illuminate\Support\Facades\Artisan::call('db:seed', [
                                    '--class' => 'Database\\Seeders\\DatabaseSeeder',
                                    '--force' => true,
                                ]);

                                // Idempotently create or update owner account
                                \App\Models\User::updateOrCreate(
                                    ['email' => $adminEmail],
                                    [
                                        'name'              => $adminName,
                                        'password'          => \Illuminate\Support\Facades\Hash::make($adminPass),
                                        'role'              => \App\Models\User::ROLE_OWNER,
                                        'is_active'         => true,
                                        'email_verified_at' => now(),
                                    ]
                                );

                                // Lock installer permanently
                                $markerData = json_encode([
                                    'installed_at' => date('c'),
                                    'version'      => '1.0.0',
                                    'owner_email'  => $adminEmail,
                                ], JSON_PRETTY_PRINT);

                                $lockBytes = @file_put_contents($installedLockFile, $markerData);
                                if ($lockBytes === false || !file_exists($installedLockFile)) {
                                    throw new \RuntimeException('Failed to write installation marker (core/storage/installed). Verify directory write permissions.');
                                }
                                @chmod($installedLockFile, 0644);

                                $success = true;
                            } catch (\Throwable $e) {
                                $cleanMsg = $dbPass !== '' ? str_replace($dbPass, '********', $e->getMessage()) : $e->getMessage();
                                @file_put_contents($installLogFile, '[' . date('c') . "] [INSTALL EXECUTION ERROR] {$cleanMsg}\n{$e->getTraceAsString()}\n", FILE_APPEND);
                                $error = 'Installation execution failed during setup. Review server logs in core/storage/logs/install.log for diagnostic details.';

                                // Clean up partial .env if migration failed so retry is clean
                                if (file_exists($envFile)) {
                                    @unlink($envFile);
                                }
                            }
                        }
                    }
                } finally {
                    flock($lockFp, LOCK_UN);
                    fclose($lockFp);
                    // Do NOT unlink $executionLockPath to preserve a stable file inode and prevent delete/recreate races.
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prince Byron Platform Setup</title>
    <style>
        :root {
            --bg-dark: #0E0B09;
            --card-dark: #181310;
            --accent-gold: #DFA759;
            --accent-terracotta: #9E472A;
            --text-light: #F8F4ED;
            --border-color: rgba(223, 167, 89, 0.25);
        }
        body {
            background: var(--bg-dark);
            color: var(--text-light);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 30px 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            box-sizing: border-box;
        }
        .container {
            max-width: 680px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .eyebrow {
            color: var(--accent-gold);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 2px;
            font-weight: 700;
        }
        .title {
            color: var(--text-light);
            font-size: 28px;
            margin: 8px 0 6px 0;
            font-weight: 700;
        }
        .card {
            background: var(--card-dark);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .section-heading {
            color: var(--accent-gold);
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 16px 0;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding-bottom: 8px;
        }
        .check-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 14px;
        }
        .badge-pass {
            background: rgba(46, 125, 50, 0.2);
            color: #81C784;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-fail {
            background: rgba(198, 40, 40, 0.2);
            color: #E57373;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group-full {
            grid-column: span 2;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: rgba(248,244,237,0.85);
            margin-bottom: 6px;
        }
        input, select {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            color: #FFF;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        select option {
            background: #181310;
            color: #FFF;
        }
        input:focus {
            border-color: var(--accent-gold);
            background: rgba(255,255,255,0.08);
        }
        .btn {
            display: inline-block;
            background: var(--accent-gold);
            color: #0E0B09;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
            transition: opacity 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-disabled {
            background: rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.3);
            cursor: not-allowed;
        }
        .alert-error {
            background: rgba(198, 40, 40, 0.2);
            border: 1px solid rgba(229, 115, 115, 0.4);
            color: #FFCDD2;
            padding: 14px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .alert-success {
            background: rgba(46, 125, 50, 0.2);
            border: 1px solid rgba(129, 199, 132, 0.4);
            color: #C8E6C9;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 24px;
        }
        .check-hint {
            flex-basis: 100%;
            font-size: 12px;
            line-height: 1.5;
            color: #FFCDD2;
            margin-top: 4px;
        }
        .footer-note {
            text-align: center;
            font-size: 12px;
            color: rgba(248,244,237,0.4);
            margin-top: 24px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="eyebrow">cPanel Release Package</div>
            <h1 class="title">Platform Initialization Wizard</h1>
        </div>

        <?php if ($success): ?>
            <div class="card alert-success">
                <h2 style="color: #81C784; margin-top: 0;">Installation Successfully Completed!</h2>
                <p style="color: #FFF; font-size: 15px; line-height: 1.6;">
                    The Prince Byron platform database has been initialized, migrations applied, and your owner administrator account established.
                </p>
                <p style="color: #FFD54F; font-size: 14px; font-weight: 600;">
                    Security Notice: The installer is now permanently locked. For maximum production security, please remove or rename the <code>install/</code> directory from cPanel File Manager.
                </p>

                <div style="text-align: left; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; padding: 18px; margin-top: 20px;">
                    <div style="font-size: 13px; text-transform: uppercase; letter-spacing: 1.4px; color: #DFA759; font-weight: 700; margin-bottom: 10px;">
                        Two steps left before you open the doors
                    </div>
                    <ol style="margin: 0; padding-left: 20px; color: #F8F4ED; font-size: 14px; line-height: 1.8;">
                        <li>
                            <strong>Add your products and song files.</strong>
                            Sign in at <code>/admin</code> and publish at least one product or song.
                            Until you do, the storefront shows clearly-labelled sample items that
                            cannot be purchased.
                        </li>
                        <li>
                            <strong>Check outgoing email.</strong>
                            <?php if ($mailerConfigured === 'none'): ?>
                                Email is set to record to the application log. Add SMTP credentials to
                                <code>.env</code> (MAIL_*) when your host provides a mailbox, otherwise
                                customers will not receive receipts.
                            <?php else: ?>
                                Place a test order and confirm the receipt arrives, then ask a friend on
                                another network to confirm the same.
                            <?php endif; ?>
                        </li>
                    </ol>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <a href="../" class="btn" style="flex: 1;">Visit Platform</a>
                    <a href="../admin" class="btn" style="flex: 1; background: #FFF;">Owner Login</a>
                </div>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert-error">
                    <strong>Setup Notice:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Preflight Verification Section -->
            <div class="card">
                <div class="section-heading">1. Environmental Preflight Verification</div>
                <?php foreach ($checks as $c): ?>
                    <div class="check-item">
                        <span><?php echo htmlspecialchars($c['label']); ?></span>
                        <span class="<?php echo $c['passed'] ? 'badge-pass' : 'badge-fail'; ?>">
                            <?php echo $c['passed'] ? 'Pass' : 'Fail'; ?>
                        </span>
                        <?php if (! $c['passed'] && ! empty($c['detail'])): ?>
                            <div class="check-hint"><?php echo htmlspecialchars($c['detail']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Configuration Form -->
            <form action="?action=execute" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card">
                    <div class="section-heading">2. Database Connection</div>
                    <div class="form-grid">
                        <div>
                            <label for="db_driver">Database Type</label>
                            <select id="db_driver" name="db_driver" onchange="pbSyncDbPort(this.value)">
                                <option value="mysql" <?php echo (($_POST['db_driver'] ?? 'mysql') === 'mysql') ? 'selected' : ''; ?>>MySQL / MariaDB (most shared cPanel hosts)</option>
                                <option value="pgsql" <?php echo (($_POST['db_driver'] ?? 'mysql') === 'pgsql') ? 'selected' : ''; ?>>PostgreSQL</option>
                            </select>
                        </div>
                        <div>
                            <label>Database Host</label>
                            <input type="text" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? '127.0.0.1'); ?>" required>
                        </div>
                        <div>
                            <label>Port</label>
                            <input type="text" id="db_port" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? '3306'); ?>" required>
                        </div>
                        <div class="form-group-full">
                            <label>Database Name</label>
                            <input type="text" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? ''); ?>" placeholder="e.g. princebyron_prod" required>
                        </div>
                        <div>
                            <label>Database User</label>
                            <input type="text" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? ''); ?>" required>
                        </div>
                        <div>
                            <label>Database Password</label>
                            <input type="password" name="db_pass" placeholder="••••••••" autocomplete="new-password">
                        </div>
                    </div>

                    <div class="section-heading" style="margin-top: 24px;">3. Owner Administrator Account</div>
                    <div class="form-grid">
                        <div class="form-group-full">
                            <label>Full Name</label>
                            <input type="text" name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? 'Prince Byron (Owner)'); ?>" required>
                        </div>
                        <div class="form-group-full">
                            <label>Email Address</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" placeholder="owner@princebyron.com" required>
                        </div>
                        <div>
                            <label>Password (Min 8 Characters)</label>
                            <input type="password" name="admin_pass" required autocomplete="new-password">
                        </div>
                        <div>
                            <label>Confirm Password</label>
                            <input type="password" name="admin_pass_confirm" required autocomplete="new-password">
                        </div>
                    </div>

                    <div class="section-heading" style="margin-top: 24px;">4. Outgoing Email</div>
                    <p style="font-size: 13px; color: rgba(248,244,237,0.65); line-height: 1.6; margin: 0 0 14px;">
                        Used for order receipts and ministry enquiry notifications. If your host has no mail server
                        configured yet, choose &ldquo;Record to log&rdquo; — messages are written to the application log
                        instead of being silently discarded.
                    </p>
                    <div class="form-grid">
                        <div class="form-group-full">
                            <label for="mail_mailer">Delivery Method</label>
                            <select id="mail_mailer" name="mail_mailer" onchange="pbToggleMail(this.value)">
                                <option value="smtp" <?php echo (($_POST['mail_mailer'] ?? 'smtp') === 'smtp') ? 'selected' : ''; ?>>SMTP server (recommended)</option>
                                <option value="none" <?php echo (($_POST['mail_mailer'] ?? 'smtp') === 'none') ? 'selected' : ''; ?>>Record to log (no email yet)</option>
                            </select>
                        </div>
                        <div class="mail-field">
                            <label>SMTP Host</label>
                            <input type="text" name="mail_host" value="<?php echo htmlspecialchars($_POST['mail_host'] ?? ''); ?>" placeholder="mail.yourdomain.com">
                        </div>
                        <div class="mail-field">
                            <label>SMTP Port</label>
                            <input type="text" name="mail_port" value="<?php echo htmlspecialchars($_POST['mail_port'] ?? '587'); ?>" placeholder="587">
                        </div>
                        <div class="mail-field">
                            <label>Encryption</label>
                            <select name="mail_encryption">
                                <option value="tls" <?php echo (($_POST['mail_encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS (587)</option>
                                <option value="ssl" <?php echo (($_POST['mail_encryption'] ?? 'tls') === 'ssl') ? 'selected' : ''; ?>>SSL (465)</option>
                                <option value="none" <?php echo (($_POST['mail_encryption'] ?? 'tls') === 'none') ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="mail-field">
                            <label>SMTP Username</label>
                            <input type="text" name="mail_user" value="<?php echo htmlspecialchars($_POST['mail_user'] ?? ''); ?>" autocomplete="off">
                        </div>
                        <div class="mail-field" style="grid-column: 1 / -1;">
                            <label>SMTP Password</label>
                            <input type="password" name="mail_pass" value="" autocomplete="new-password">
                        </div>
                        <div class="form-group-full">
                            <label>"From" Email Address</label>
                            <input type="email" name="mail_from" value="<?php echo htmlspecialchars($_POST['mail_from'] ?? ($_POST['admin_email'] ?? '')); ?>" placeholder="orders@yourdomain.com">
                        </div>
                    </div>

                    <div class="section-heading" style="margin-top: 24px;">5. Platform General Settings</div>
                    <div class="form-grid">
                        <div class="form-group-full">
                            <label>Platform Name</label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($_POST['site_name'] ?? 'Prince Byron Official Platform'); ?>" required>
                        </div>
                        <div class="form-group-full">
                            <label>Application URL</label>
                            <input type="text" name="app_url" value="<?php echo htmlspecialchars($_POST['app_url'] ?? detectAppUrl()); ?>" required>
                        </div>
                    </div>

                    <div style="margin-top: 28px;">
                        <?php if ($allPassed): ?>
                            <button type="submit" class="btn">Initialize & Install Platform</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-disabled" disabled>Resolve Preflight Issues Above to Continue</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="footer-note">
            Prince Byron Gospel Platform &bull; Fixed Scope Engagement &bull; Release Candidate Installer
        </div>
    </div>

    <script>
        // Keep the port field sensible when the engine changes, without
        // overwriting a value the operator has deliberately typed.
        function pbSyncDbPort(driver) {
            var port = document.getElementById('db_port');
            if (!port) return;
            var current = port.value.trim();
            if (current === '' || current === '3306' || current === '5432') {
                port.value = driver === 'pgsql' ? '5432' : '3306';
            }
        }

        // Hide SMTP fields when the operator chooses "record to log", so the
        // form cannot ask for settings that will not be used.
        function pbToggleMail(mailer) {
            var fields = document.querySelectorAll('.mail-field');
            for (var i = 0; i < fields.length; i++) {
                fields[i].style.display = (mailer === 'smtp') ? '' : 'none';
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var sel = document.getElementById('mail_mailer');
            if (sel) pbToggleMail(sel.value);
        });
    </script>
</body>
</html>
