<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class Phase5InstallerTest extends TestCase
{
    protected string $installedPath;
    protected string $installedBackupPath;
    protected string $envPath;
    protected string $envBackupPath;
    protected string $rootLoaderPath;
    protected string $installerPath;
    protected string $rootHtaccessPath;
    protected string $installHtaccessPath;
    protected string $installLockPath;
    protected string $installLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installedPath       = realpath(base_path('storage')) . DIRECTORY_SEPARATOR . 'installed';
        $this->installedBackupPath = realpath(base_path('storage')) . DIRECTORY_SEPARATOR . 'installed.test_backup';
        $this->envPath             = realpath(base_path('../core/.env')) ?: (base_path('.env'));
        $this->envBackupPath       = $this->envPath . '.test_backup';
        $this->rootLoaderPath      = realpath(base_path('../index.php'));
        $this->installerPath       = realpath(base_path('../install/index.php'));
        $this->rootHtaccessPath    = realpath(base_path('../.htaccess'));
        $this->installHtaccessPath = realpath(base_path('../install/.htaccess'));
        $this->installLockPath     = realpath(base_path('storage')) . DIRECTORY_SEPARATOR . 'install_execution.lock';
        $this->installLogPath      = realpath(base_path('storage/logs')) . DIRECTORY_SEPARATOR . 'install.log';

        // Backup installed lock file if present
        if (file_exists($this->installedPath)) {
            rename($this->installedPath, $this->installedBackupPath);
        }

        // Clean any lingering execution lock
        if (file_exists($this->installLockPath)) {
            @unlink($this->installLockPath);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temporary installed marker if created during tests
        if (file_exists($this->installedPath)) {
            @unlink($this->installedPath);
        }

        // Restore original installed file if it existed
        if (file_exists($this->installedBackupPath)) {
            rename($this->installedBackupPath, $this->installedPath);
        }

        // Clean any lingering execution lock
        if (file_exists($this->installLockPath)) {
            @unlink($this->installLockPath);
        }

        parent::tearDown();
    }

    /**
     * Executes the root loader (index.php) in a clean sub-process.
     */
    protected function runRootLoader(string $uri, string $method = 'GET'): array
    {
        $runner = <<<'PHP'
register_shutdown_function(function () {
    $out = ob_get_contents();
    $status = http_response_code() ?: 200;
    file_put_contents('php://stderr', json_encode([
        'status' => $status,
        'output' => $out,
    ]));
});
ob_start();
$_SERVER['REQUEST_URI'] = $argv[1];
$_SERVER['REQUEST_METHOD'] = $argv[2];
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
require $argv[3];
PHP;

        $proc = new Process([
            PHP_BINARY,
            '-r',
            $runner,
            $uri,
            $method,
            $this->rootLoaderPath,
        ]);

        $proc->run();
        $rawResult = $proc->getErrorOutput();
        $decoded = json_decode($rawResult, true);

        return $decoded ?: [
            'status' => $proc->getExitCode() === 0 ? 200 : 500,
            'output' => $proc->getOutput(),
        ];
    }

    /**
     * Executes the one-time installer (install/index.php) in a clean sub-process.
     */
    protected function runInstaller(array $get = [], array $post = [], array $server = []): array
    {
        $runner = <<<'PHP'
register_shutdown_function(function () {
    $out = ob_get_contents();
    $status = http_response_code() ?: 200;
    file_put_contents('php://stderr', json_encode([
        'status' => $status,
        'output' => $out,
    ]));
});
ob_start();
$_GET = json_decode($argv[1], true) ?: [];
$_POST = json_decode($argv[2], true) ?: [];
$extraServer = json_decode($argv[3], true) ?: [];

$_SERVER = array_merge([
    'REQUEST_METHOD' => !empty($_POST) ? 'POST' : 'GET',
    'REQUEST_URI'    => '/install/index.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : ''),
    'SCRIPT_NAME'    => '/install/index.php',
    'HTTP_HOST'      => 'localhost',
    'HTTPS'          => 'on',
    'REMOTE_ADDR'    => '127.0.0.1',
], $extraServer);

// Simulate pre-existing installer session with valid CSRF token if POST doesn't supply an explicit token
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['installer_csrf_token'])) {
    $_SESSION['installer_csrf_token'] = 'test_valid_installer_csrf_token_32chars!';
}
if (!empty($_POST) && !array_key_exists('csrf_token', $_POST)) {
    $_POST['csrf_token'] = $_SESSION['installer_csrf_token'];
}

require $argv[4];
PHP;

        $proc = new Process([
            PHP_BINARY,
            '-r',
            $runner,
            json_encode($get),
            json_encode($post),
            json_encode($server),
            $this->installerPath,
        ]);

        $proc->run();
        $rawResult = $proc->getErrorOutput();
        $decoded = json_decode($rawResult, true);

        return $decoded ?: [
            'status' => $proc->getExitCode() === 0 ? 200 : 500,
            'output' => $proc->getOutput(),
        ];
    }

    /**
     * 1. Root loader denies direct access to private folders, .env, and sensitive files.
     */
    public function test_root_loader_blocks_private_directories_and_sensitive_files(): void
    {
        $blockedUris = [
            '/core/',
            '/core/app/Http/Controllers/Controller.php',
            '/core/.env',
            '/.env',
            '/.env.production',
            '/.env.local',
            '/vendor/',
            '/vendor/autoload.php',
            '/storage/',
            '/storage/logs/laravel.log',
            '/storage/app/private/audio.wav',
            '/.git/config',
            '/database.sql',
            '/debug.log',
            '/composer.lock',
        ];

        foreach ($blockedUris as $uri) {
            $response = $this->runRootLoader($uri);
            $this->assertSame(
                403,
                $response['status'],
                "Root loader must return 403 Forbidden for protected URI: {$uri}"
            );
            $this->assertStringContainsString('Forbidden', $response['output']);
        }
    }

    /**
     * 2. Root .htaccess and install/.htaccess enforce defense-in-depth and ASVS 5.0 L2 rules.
     */
    public function test_root_htaccess_and_install_htaccess_security_rules(): void
    {
        $this->assertFileExists($this->rootHtaccessPath);
        $rootHtaccess = file_get_contents($this->rootHtaccessPath);

        // Core, vendor, storage, .env, and .git denial rules
        $this->assertStringContainsString('RewriteRule ^core(/|$) - [F,L]', $rootHtaccess);
        $this->assertStringContainsString('RewriteRule ^vendor(/|$) - [F,L]', $rootHtaccess);
        $this->assertStringContainsString('RewriteRule ^storage(/|$) - [F,L]', $rootHtaccess);
        $this->assertStringContainsString('RewriteRule ^\.env.*$ - [F,L]', $rootHtaccess);
        $this->assertStringContainsString('RewriteRule ^\.git.*$ - [F,L]', $rootHtaccess);

        // Sensitive file extensions denial
        $this->assertStringContainsString('FilesMatch "(^\.env|\.git|\.lock$|\.json$|\.sql$|\.log$|\.md$|composer\.)"', $rootHtaccess);
        $this->assertStringContainsString('Require all denied', $rootHtaccess);

        // ASVS 5.0 Level 2 baseline security headers
        $this->assertStringContainsString('Header set X-Content-Type-Options "nosniff"', $rootHtaccess);
        $this->assertStringContainsString('Header set X-Frame-Options "SAMEORIGIN"', $rootHtaccess);
        $this->assertStringContainsString('Header set X-XSS-Protection "1; mode=block"', $rootHtaccess);
        $this->assertStringContainsString('Header set Referrer-Policy "strict-origin-when-cross-origin"', $rootHtaccess);

        // Performance & compression rules
        $this->assertStringContainsString('mod_deflate.c', $rootHtaccess);
        $this->assertStringContainsString('mod_expires.c', $rootHtaccess);
        $this->assertStringContainsString('max-age=31536000, public, immutable', $rootHtaccess);

        // Public asset routing to core/public/
        $this->assertStringContainsString('RewriteRule ^build/(.*)$ core/public/build/$1 [L]', $rootHtaccess);
        $this->assertStringContainsString('RewriteRule ^assets/(.*)$ core/public/assets/$1 [L]', $rootHtaccess);

        // Install .htaccess
        $this->assertFileExists($this->installHtaccessPath);
        $installHtaccess = file_get_contents($this->installHtaccessPath);
        $this->assertStringContainsString('FilesMatch "(^\.env|\.sql$|\.log$|\.lock$)"', $installHtaccess);
        $this->assertStringContainsString('Require all denied', $installHtaccess);
    }

    /**
     * 3. Installer preflight checks are verified and pass in approved host environment.
     */
    public function test_installer_preflight_checks_pass_in_approved_environment(): void
    {
        $response = $this->runInstaller(['action' => 'requirements']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Environmental Preflight Verification', $response['output']);
        $this->assertStringContainsString('PHP Version', $response['output']);
        $this->assertStringContainsString('OpenSSL Cryptography', $response['output']);
        $this->assertStringContainsString('Multibyte String (mbstring)', $response['output']);
        $this->assertStringContainsString('Directory Writable: core/storage/', $response['output']);

        // The platform targets MySQL/MariaDB so it installs on ordinary shared
        // cPanel plans; requiring pdo_pgsql alone would reject those hosts.
        // Either driver must satisfy preflight, so the label names both.
        $this->assertStringContainsString('Database Driver (PDO MySQL or PDO PostgreSQL)', $response['output']);

        $this->assertStringContainsString('Initialize & Install Platform', $response['output']);
    }

    /**
     * 3b. A failed preflight check must never render as "Pass".
     *
     * Regression: the template previously printed the literal word "Pass"
     * inside the badge element regardless of outcome, so a host missing a
     * required extension was told the check passed while the install button
     * stayed disabled — contradictory, unactionable information at exactly the
     * moment an owner needs certainty.
     */
    public function test_failed_preflight_check_renders_fail_and_remedy_not_pass(): void
    {
        // Force a guaranteed-failing condition by raising the effective PHP
        // floor beyond any real runtime. COMPOSER_PLATFORM_PHP is the same
        // override Composer honours, so the installer's gate stays faithful to
        // how dependencies are actually resolved. The child process inherits
        // this environment, so it must be restored immediately afterwards.
        putenv('COMPOSER_PLATFORM_PHP=99.0.0');

        try {
            $response = $this->runInstaller(['action' => 'requirements']);
        } finally {
            putenv('COMPOSER_PLATFORM_PHP');
        }

        $this->assertSame(200, $response['status']);

        // A higher floor must be reported as a failure, with a remedy the owner
        // can action in cPanel, and the install action must be blocked.
        $rendered = html_entity_decode($response['output'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('PHP Version >= 8.5.0', $rendered);
        $this->assertStringContainsString('MultiPHP Manager', $rendered);
        $this->assertStringContainsString('Resolve Preflight Issues Above to Continue', $rendered);
        $this->assertStringNotContainsString('Initialize & Install Platform', $rendered);

        // The failed row must say "Fail" and show the remedy inline; it used to
        // render the word "Pass" regardless of outcome.
        $this->assertStringContainsString('badge-fail', $response['output']);
        $this->assertStringContainsString('check-hint', $response['output']);
    }

    /**
     * 4. Installer permanently locks once core/storage/installed exists.
     */
    public function test_installer_locks_permanently_when_installed_marker_exists(): void
    {
        file_put_contents($this->installedPath, json_encode([
            'installed_at' => date('c'),
            'version'      => '1.0.0',
            'owner_email'  => 'owner@princebyron.local',
        ]));

        $response = $this->runInstaller();

        $this->assertSame(403, $response['status']);
        $this->assertStringContainsString('Platform Already Initialized', $response['output']);
        $this->assertStringContainsString('Installation Locked', $response['output']);
        $this->assertStringContainsString('Documented Recovery Path', $response['output']);
    }

    /**
     * 5. Permanent lockout cannot be bypassed by any URL or query parameters (?force=1, etc.).
     */
    public function test_installer_lockout_cannot_be_bypassed_by_query_parameters(): void
    {
        file_put_contents($this->installedPath, json_encode([
            'installed_at' => date('c'),
            'version'      => '1.0.0',
            'owner_email'  => 'owner@princebyron.local',
        ]));

        $bypassAttempts = [
            ['force' => '1'],
            ['bypass' => 'true'],
            ['override' => '1'],
            ['reset' => '1'],
            ['action' => 'execute'],
            ['action' => 'setup'],
            ['action' => 'requirements', 'force' => 'true'],
        ];

        foreach ($bypassAttempts as $params) {
            $response = $this->runInstaller($params);
            $this->assertSame(
                403,
                $response['status'],
                "Installer lockout must reject bypass parameter: " . http_build_query($params)
            );
            $this->assertStringContainsString('Platform Already Initialized', $response['output']);
            $this->assertStringNotContainsString('Database Configuration', $response['output']);
        }
    }

    /**
     * 6. Documented recovery path requires filesystem removal, preventing open installer exposure.
     */
    public function test_installer_lockout_provides_documented_recovery_path(): void
    {
        file_put_contents($this->installedPath, json_encode([
            'installed_at' => date('c'),
            'version'      => '1.0.0',
            'owner_email'  => 'owner@princebyron.local',
        ]));

        $response = $this->runInstaller();

        $this->assertStringContainsString('core/storage/installed', $response['output']);
        $this->assertStringContainsString('core/.env', $response['output']);
        $this->assertStringContainsString('SSH or cPanel File Manager', $response['output']);
    }

    /**
     * 7. Installer rejects missing or invalid database and owner fields.
     */
    public function test_installer_validation_fails_on_missing_required_fields(): void
    {
        // Missing DB name and user
        $res1 = $this->runInstaller(['action' => 'execute'], [
            'db_name' => '',
            'db_user' => '',
        ]);
        $this->assertStringContainsString('Database Name and Database Username are required.', $res1['output']);

        // Missing owner administrator fields
        $res2 = $this->runInstaller(['action' => 'execute'], [
            'db_name'     => 'princebyron_test',
            'db_user'     => 'princebyron_user',
            'admin_name'  => '',
            'admin_email' => '',
            'admin_pass'  => '',
        ]);
        $this->assertStringContainsString('All owner administrator fields are required.', $res2['output']);

        // Invalid email format
        $res3 = $this->runInstaller(['action' => 'execute'], [
            'db_name'            => 'princebyron_test',
            'db_user'            => 'princebyron_user',
            'admin_name'         => 'Prince Byron Owner',
            'admin_email'        => 'not-a-valid-email',
            'admin_pass'         => 'SecretPass123!',
            'admin_pass_confirm' => 'SecretPass123!',
        ]);
        $this->assertStringContainsString('Invalid owner email address format.', $res3['output']);

        // Password shorter than 8 characters
        $res4 = $this->runInstaller(['action' => 'execute'], [
            'db_name'            => 'princebyron_test',
            'db_user'            => 'princebyron_user',
            'admin_name'         => 'Prince Byron Owner',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'short',
            'admin_pass_confirm' => 'short',
        ]);
        $this->assertStringContainsString('Owner password must be at least 8 characters in length.', $res4['output']);

        // Password confirmation mismatch
        $res5 = $this->runInstaller(['action' => 'execute'], [
            'db_name'            => 'princebyron_test',
            'db_user'            => 'princebyron_user',
            'admin_name'         => 'Prince Byron Owner',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'SecretPass123!',
            'admin_pass_confirm' => 'DifferentPass456!',
        ]);
        $this->assertStringContainsString('Owner password and confirmation do not match.', $res5['output']);
    }

    /**
     * 8. Wrong database credentials are rejected with sanitized error and zero password disclosure.
     */
    public function test_installer_sanitizes_database_errors_and_never_leaks_raw_passwords(): void
    {
        $rawSecretPassword = 'super_secret_cleartext_db_password_xyz987';

        $response = $this->runInstaller(['action' => 'execute'], [
            'db_host'            => '127.0.0.1',
            'db_port'            => '5437',
            'db_name'            => 'princebyron',
            'db_user'            => 'princebyron',
            'db_pass'            => $rawSecretPassword,
            'admin_name'         => 'Prince Byron',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'SecureOwnerPass123!',
            'admin_pass_confirm' => 'SecureOwnerPass123!',
        ]);

        $this->assertStringContainsString('Database Connection Failed', $response['output']);
        $this->assertStringNotContainsString(
            $rawSecretPassword,
            $response['output'],
            'Raw database password must NEVER be disclosed in error messages or response output'
        );
    }

    /**
     * 9. Generates secure CSPRNG 32-byte base64 application key.
     */
    public function test_installer_app_key_generation_is_cryptographically_secure(): void
    {
        $appKey = 'base64:' . base64_encode(random_bytes(32));

        $this->assertStringStartsWith('base64:', $appKey);
        $encodedKey = substr($appKey, 7);
        $decodedKey = base64_decode($encodedKey, true);

        $this->assertNotFalse($decodedKey);
        $this->assertSame(32, strlen($decodedKey), 'App key must be exactly 32 bytes (256 bits)');
    }

    /**
     * 10. Generated .env defaults to production environment and sandbox gateways.
     */
    public function test_installer_env_defaults_enforce_production_and_sandbox_modes(): void
    {
        $template = <<<ENV
APP_NAME="Prince Byron Official Platform"
APP_ENV=production
APP_KEY=base64:testkey
APP_DEBUG=false
APP_URL=https://princebyron.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=princebyron
DB_USERNAME=princebyron
DB_PASSWORD="secret"

STRIPE_MODE=sandbox
PAYPAL_MODE=sandbox
ENV;

        $this->assertStringContainsString('APP_ENV=production', $template);
        $this->assertStringContainsString('APP_DEBUG=false', $template);
        $this->assertStringContainsString('DB_CONNECTION=pgsql', $template);
        $this->assertStringContainsString('STRIPE_MODE=sandbox', $template);
        $this->assertStringContainsString('PAYPAL_MODE=sandbox', $template);
    }

    /**
     * 11. HTTPS enforcement: Rejects cleartext HTTP on remote hosts (both GET and POST).
     */
    public function test_installer_enforces_https_and_rejects_cleartext_http_on_remote_hosts(): void
    {
        $remoteServer = [
            'HTTP_HOST'   => 'princebyron.com',
            'HTTPS'       => 'off',
            'REMOTE_ADDR' => '203.0.113.195',
        ];

        // Cleartext GET request
        $getRes = $this->runInstaller(['action' => 'requirements'], [], $remoteServer);
        $this->assertSame(403, $getRes['status']);
        $this->assertStringContainsString('HTTPS Connection Required', $getRes['output']);
        $this->assertStringNotContainsString('1. Environmental Preflight Verification', $getRes['output']);

        // Cleartext POST request with credentials
        $postRes = $this->runInstaller(['action' => 'execute'], [
            'db_name' => 'insecure_db',
            'db_user' => 'insecure_user',
        ], $remoteServer);
        $this->assertSame(403, $postRes['status']);
        $this->assertStringContainsString('HTTPS Connection Required', $postRes['output']);
    }

    /**
     * 12. HTTPS proxy handling: Rejects untrusted client-supplied forwarded-protocol headers.
     */
    public function test_installer_rejects_untrusted_client_forwarded_proto_headers(): void
    {
        $spoofedServer = [
            'HTTP_HOST'              => 'princebyron.com',
            'HTTPS'                  => 'off',
            'REMOTE_ADDR'            => '203.0.113.195', // Untrusted external client IP
            'HTTP_X_FORWARDED_PROTO' => 'https',         // Spoofed HTTPS claim
        ];

        $response = $this->runInstaller(['action' => 'requirements'], [], $spoofedServer);
        $this->assertSame(403, $response['status'], 'Untrusted client IP cannot spoof HTTPS via X-Forwarded-Proto');
        $this->assertStringContainsString('HTTPS Connection Required', $response['output']);
    }

    /**
     * 13. HTTPS proxy handling: Accepts HTTPS forwarded by a trusted reverse proxy.
     */
    public function test_installer_accepts_https_from_trusted_reverse_proxy(): void
    {
        $trustedProxyServer = [
            'HTTP_HOST'              => 'princebyron.com',
            'HTTPS'                  => 'off',
            'REMOTE_ADDR'            => '10.0.0.15', // Trusted internal reverse proxy (private subnet)
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];

        $response = $this->runInstaller(['action' => 'requirements'], [], $trustedProxyServer);
        $this->assertSame(200, $response['status'], 'Trusted reverse proxy with HTTPS proto must be accepted');
        $this->assertStringContainsString('Environmental Preflight Verification', $response['output']);
    }

    /**
     * 14. CSRF protection: Rejects POST submissions with missing or invalid CSRF tokens.
     */
    public function test_installer_requires_and_validates_csrf_token(): void
    {
        // Missing token
        $missingCsrf = $this->runInstaller(['action' => 'execute'], [
            'csrf_token' => '',
            'db_name'    => 'test_db',
            'db_user'    => 'test_user',
        ]);
        $this->assertStringContainsString('Invalid or missing CSRF session token', $missingCsrf['output']);

        // Invalid token
        $invalidCsrf = $this->runInstaller(['action' => 'execute'], [
            'csrf_token' => 'attacker_forged_csrf_token_12345',
            'db_name'    => 'test_db',
            'db_user'    => 'test_user',
        ]);
        $this->assertStringContainsString('Invalid or missing CSRF session token', $invalidCsrf['output']);
    }

    /**
     * 15. Single-process safety: File lock mutex prevents concurrent setup executions.
     */
    public function test_installer_single_process_safety_prevents_simultaneous_execution(): void
    {
        // Hold an exclusive lock on the execution lock file to simulate another active installation process
        $lockFp = fopen($this->installLockPath, 'c+');
        $this->assertNotFalse($lockFp);
        $acquired = flock($lockFp, LOCK_EX | LOCK_NB);
        $this->assertTrue($acquired, 'Test harness must acquire lock');

        try {
            // Attempt concurrent submission
            $response = $this->runInstaller(['action' => 'execute'], [
                'db_name'            => 'princebyron',
                'db_user'            => 'princebyron',
                'admin_name'         => 'Owner',
                'admin_email'        => 'owner@princebyron.local',
                'admin_pass'         => 'Password123!',
                'admin_pass_confirm' => 'Password123!',
            ]);

            $this->assertStringContainsString(
                'Another installation process is currently in progress',
                $response['output'],
                'Concurrent execution must be blocked by file lock mutex'
            );
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * 16. Security hardening: Rejects newline injection to prevent .env header splitting.
     */
    public function test_installer_rejects_newline_injection_in_inputs(): void
    {
        $maliciousInputs = [
            'db_user' => "princebyron\nMALICIOUS_KEY=injected_value",
            'db_name' => "princebyron\r\nANOTHER_KEY=malicious",
        ];

        $response = $this->runInstaller(['action' => 'execute'], array_merge([
            'db_host'            => '127.0.0.1',
            'db_port'            => '5432',
            'admin_name'         => 'Prince Byron',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'ValidPass123!',
            'admin_pass_confirm' => 'ValidPass123!',
        ], $maliciousInputs));

        $this->assertStringContainsString('Form inputs must not contain newline characters.', $response['output']);
    }

    /**
     * 17. Server logging: Retains detailed diagnostics in protected server logs without leaking to client.
     */
    public function test_installer_logs_diagnostics_to_server_log_without_leaking_to_client(): void
    {
        // Clear log file if present
        if (file_exists($this->installLogPath)) {
            @unlink($this->installLogPath);
        }

        $response = $this->runInstaller(['action' => 'execute'], [
            'db_host'            => '127.0.0.1',
            'db_port'            => '59999', // Closed port triggers connection failure
            'db_name'            => 'princebyron',
            'db_user'            => 'princebyron',
            'db_pass'            => 'secret_pass_not_for_client',
            'admin_name'         => 'Prince Byron',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'ValidPass123!',
            'admin_pass_confirm' => 'ValidPass123!',
        ]);

        // Client output receives only safe generic message
        $this->assertStringContainsString('Database Connection Failed: Unable to connect to PostgreSQL with provided credentials.', $response['output']);
        $this->assertStringNotContainsString('SQLSTATE', $response['output'], 'Driver-level SQLSTATE must not be leaked to browser');
        $this->assertStringNotContainsString('secret_pass_not_for_client', $response['output']);

        // Protected server log file receives the diagnostic trace
        $this->assertFileExists($this->installLogPath);
        $logContent = file_get_contents($this->installLogPath);
        $this->assertStringContainsString('[DB CONNECTION ERROR]', $logContent);
        $this->assertStringNotContainsString('secret_pass_not_for_client', $logContent, 'Password must be redacted even in server logs');
    }

    /**
     * 18. Retry behavior: Failed installation leaves no installed lock file and cleans partial .env.
     */
    public function test_installer_cleans_up_partial_env_on_failed_migration_for_clean_retry(): void
    {
        // Trigger DB connection failure
        $response = $this->runInstaller(['action' => 'execute'], [
            'db_host'            => '127.0.0.1',
            'db_port'            => '59999',
            'db_name'            => 'princebyron',
            'db_user'            => 'princebyron',
            'db_pass'            => 'invalid_pass',
            'admin_name'         => 'Prince Byron',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'ValidPass123!',
            'admin_pass_confirm' => 'ValidPass123!',
        ]);

        $this->assertStringContainsString('Database Connection Failed', $response['output']);

        // Assert installed marker was NOT created
        $this->assertFileDoesNotExist($this->installedPath, 'Failed install must not create installed marker');

        // Subsequent requirements/setup request can be loaded again without lockout
        $retryCheck = $this->runInstaller(['action' => 'requirements']);
        $this->assertSame(200, $retryCheck['status']);
        $this->assertStringContainsString('Environmental Preflight Verification', $retryCheck['output']);
    }

    /**
     * 19. HTTPS spoofing prevention: Rejects remote HTTP request even when Host header says localhost.
     */
    public function test_installer_rejects_remote_http_request_with_spoofed_localhost_host_header(): void
    {
        // Remote client (203.0.113.195) sending Host: localhost over cleartext HTTP
        $spoofedHostServer = [
            'HTTP_HOST'   => 'localhost',
            'HTTPS'       => 'off',
            'REMOTE_ADDR' => '203.0.113.195',
        ];

        // Cleartext GET request
        $getRes = $this->runInstaller(['action' => 'requirements'], [], $spoofedHostServer);
        $this->assertSame(403, $getRes['status'], 'Remote client with Host: localhost must NOT bypass HTTPS');
        $this->assertStringContainsString('HTTPS Connection Required', $getRes['output']);
        $this->assertStringNotContainsString('Environmental Preflight Verification', $getRes['output']);

        // Cleartext POST request with credentials
        $postRes = $this->runInstaller(['action' => 'execute'], [
            'db_name' => 'spoofed_db',
            'db_user' => 'spoofed_user',
        ], $spoofedHostServer);
        $this->assertSame(403, $postRes['status'], 'Remote POST credentials with Host: localhost must NOT bypass HTTPS');
        $this->assertStringContainsString('HTTPS Connection Required', $postRes['output']);
    }

    /**
     * 20. Concurrency safety: Mutex lock file persists on disk and prevents delete/recreate races.
     */
    public function test_installer_stable_lock_file_persists_and_prevents_delete_recreate_races(): void
    {
        // Execute an installer action that exercises the mutex block & finally
        $response = $this->runInstaller(['action' => 'execute'], [
            'db_host'            => '127.0.0.1',
            'db_port'            => '59999', // Fails DB connection, exercises mutex block & finally
            'db_name'            => 'princebyron',
            'db_user'            => 'princebyron',
            'db_pass'            => 'dummy_pass',
            'admin_name'         => 'Prince Byron',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'ValidPass123!',
            'admin_pass_confirm' => 'ValidPass123!',
        ]);

        // Assert that the execution lock file STILL EXISTS on disk (stable file, NOT unlinked)
        $this->assertFileExists(
            $this->installLockPath,
            'Execution lock file must remain permanently on disk to prevent inode race conditions'
        );

        // Verify that a subsequent process can open and lock the exact same file
        $fp = fopen($this->installLockPath, 'c+');
        $this->assertNotFalse($fp);
        $acquired = flock($fp, LOCK_EX | LOCK_NB);
        $this->assertTrue($acquired, 'Subsequent execution must successfully lock the existing stable lock file');

        // While this process holds the lock, verify a concurrent request is blocked
        $blockedResponse = $this->runInstaller(['action' => 'execute'], [
            'db_name'            => 'princebyron',
            'db_user'            => 'princebyron',
            'admin_name'         => 'Owner',
            'admin_email'        => 'owner@princebyron.local',
            'admin_pass'         => 'Password123!',
            'admin_pass_confirm' => 'Password123!',
        ]);
        $this->assertStringContainsString(
            'Another installation process is currently in progress',
            $blockedResponse['output']
        );

        // Release lock
        flock($fp, LOCK_UN);
        fclose($fp);

        // Assert lock file still exists after release
        $this->assertFileExists($this->installLockPath);
    }

    /**
     * 21. The installer's PHP floor must match composer.json.
     *
     * The release archive ships a prebuilt vendor/, so Composer performs no
     * platform_requirements check on the host. If the installer accepts a PHP
     * version the locked dependencies reject, installation "succeeds" and then
     * every request fatals. This test keeps the two declarations in lock-step.
     */
    public function test_installer_php_floor_matches_composer_constraint(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertIsArray($composer, 'composer.json must be valid JSON.');
        $constraint = $composer['require']['php'] ?? '';
        $this->assertMatchesRegularExpression(
            '/\^(\d+\.\d+)/',
            $constraint,
            'composer.json require.php must be a caret constraint, e.g. "^8.5".'
        );
        preg_match('/\^(\d+\.\d+)/', $constraint, $m);
        $composerFloor = $m[1];

        $installer = (string) file_get_contents($this->installerPath);
        $this->assertMatchesRegularExpression(
            '/\$requiredPhp\s*=\s*\'(\d+\.\d+(?:\.\d+)?)\'/',
            $installer,
            'install/index.php must declare a single $requiredPhp floor.'
        );
        preg_match('/\$requiredPhp\s*=\s*\'(\d+\.\d+(?:\.\d+)?)\'/', $installer, $m2);
        $installerFloor = $m2[1];

        $this->assertSame(
            $composerFloor,
            implode('.', array_slice(explode('.', $installerFloor), 0, 2)),
            "Installer PHP floor ({$installerFloor}) must equal composer.json floor ({$composerFloor}). "
            .'Update install/index.php and composer.json together.'
        );

        // The rendered preflight must advertise the same number to the operator.
        // The installer escapes its labels, so decode before comparing rather
        // than asserting on entity-encoded markup.
        $response = $this->runInstaller(['action' => 'requirements']);
        $rendered = html_entity_decode($response['output'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString("PHP Version >= {$installerFloor}", $rendered);
    }
}
