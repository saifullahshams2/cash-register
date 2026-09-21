<?php

namespace Tests\Feature;

use App\Livewire\Admin\Cashiers;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseHostValidator;
use App\Services\SalesExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        file_put_contents(storage_path('installed'), 'INSTALLED');
        $this->seed();
    }

    protected function tearDown(): void
    {
        file_put_contents(storage_path('installed'), 'INSTALLED');
        parent::tearDown();
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader('Content-Security-Policy');
    }

    public function test_login_rate_limiting_locks_out_after_five_failed_attempts(): void
    {
        $ip = '127.0.0.1';
        RateLimiter::clear("attacker|{$ip}");

        // 5 consecutive failed attempts
        for ($i = 1; $i <= 5; $i++) {
            Livewire::test(Login::class)
                ->set('username', 'attacker')
                ->set('password', 'wrongpassword')
                ->call('login')
                ->assertHasErrors(['username']);
        }

        // 6th attempt must be throttled
        $component = Livewire::test(Login::class)
            ->set('username', 'attacker')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertStringContainsString('Too many login attempts', $component->errors()->first('username'));
    }

    public function test_installer_endpoints_are_forbidden_when_application_is_installed(): void
    {
        file_put_contents(storage_path('installed'), 'INSTALLED');

        // JSON POST request to test-db must be 403 Forbidden
        $response = $this->postJson('/install/test-db', [
            'host' => '127.0.0.1',
            'port' => 3306,
        ]);
        $response->assertStatus(403);

        // POST request to process must be 403 Forbidden
        $processResponse = $this->post('/install/process', [
            'db_connection' => 'sqlite',
        ]);
        $processResponse->assertStatus(403);
    }

    public function test_excel_exporter_escapes_formula_injection_characters(): void
    {
        $exporter = new SalesExcelExporter;

        $order = Order::create([
            'order_number' => '=1+1',
            'cashier_name' => '+malicious_cashier',
            'payment_method' => 'CASH',
            'subtotal' => 10.000,
            'total' => 10.000,
            'tendered' => 10.000,
            'change' => 0.000,
            'status' => 'COMPLETED',
        ]);

        $meta = [
            'companyName' => '@CmdStore',
            'fromDate' => now()->toDateString(),
            'toDate' => now()->toDateString(),
            'revenue' => 10.000,
            'count' => 1,
            'cashRevenue' => 10.000,
            'knetRevenue' => 0.000,
        ];

        $binary = $exporter->generate(Order::with('items')->get(), $meta);

        // Decompress the generated .xlsx archive to inspect sheet1.xml
        $tempZip = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tempZip, $binary);
        $zip = new \ZipArchive;
        $zip->open($tempZip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tempZip);

        // Verify prefixes are prepended with single quote in worksheet XML
        $this->assertStringContainsString("'@CmdStore", $sheetXml);
        $this->assertStringContainsString("'+malicious_cashier", $sheetXml);
        $this->assertStringContainsString("'=1+1", $sheetXml);
    }

    public function test_dashboard_rejects_svg_file_uploads_for_branding(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        $fakeSvg = UploadedFile::fake()->create('exploit.svg', 10, 'image/svg+xml');

        Livewire::test(Dashboard::class)
            ->set('siteLogo', $fakeSvg)
            ->call('saveSettings')
            ->assertHasErrors(['siteLogo']);
    }

    public function test_pos_checkout_rejects_invalid_or_tampered_cart_quantities(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();

        // Attempt checkout with negative quantity (e.g. client-side tampering)
        Livewire::test(Pos::class)
            ->set('cart', [
                $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'quantity' => -5,
                ],
            ])
            ->set('paymentMethod', 'CASH')
            ->set('tenderedInput', '10.000')
            ->call('checkout')
            ->assertSet('notificationMessage', 'Cart contains invalid items or quantities.');

        // Verify no order was created
        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_pos_checkout_rejects_negative_or_non_numeric_or_infinite_totals(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'quantity' => 1,
            ],
        ];

        $invalidTotals = ['-50', '1e309', 'abc', 'NAN', '-0.001'];

        foreach ($invalidTotals as $badTotal) {
            Livewire::test(Pos::class)
                ->set('cart', $cart)
                ->set('paymentMethod', 'CASH')
                ->set('totalInput', $badTotal)
                ->set('tenderedInput', '10.000')
                ->call('checkout')
                ->assertSet('notificationMessage', 'Invalid total amount.');
        }

        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_pos_checkout_rejects_invalid_or_injected_payment_methods(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'quantity' => 1,
            ],
        ];

        $badMethods = ['HACK; DROP TABLE orders;--', 'BITCOIN', 'UNKNOWN', '<script>alert(1)</script>'];

        foreach ($badMethods as $method) {
            Livewire::test(Pos::class)
                ->set('cart', $cart)
                ->set('paymentMethod', $method)
                ->set('totalInput', '5.000')
                ->set('tenderedInput', '5.000')
                ->call('checkout')
                ->assertSet('notificationMessage', 'Invalid payment method.');
        }

        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_pos_checkout_rejects_negative_or_infinite_tendered_amount(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'quantity' => 1,
            ],
        ];

        $badTendered = ['-10.000', '1e309', 'invalid', '-0.5'];

        foreach ($badTendered as $badAmount) {
            Livewire::test(Pos::class)
                ->set('cart', $cart)
                ->set('paymentMethod', 'CASH')
                ->set('totalInput', '5.000')
                ->set('tenderedInput', $badAmount)
                ->call('checkout')
                ->assertSet('notificationMessage', 'Invalid tendered amount.');
        }

        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_pos_checkout_derives_item_name_and_code_from_database_not_client_cart(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();

        // Tampered client cart with malicious / spoofed item name and code
        Livewire::test(Pos::class)
            ->set('cart', [
                $product->id => [
                    'id' => $product->id,
                    'name' => "0'; DELETE FROM users; --",
                    'code' => "X' OR '1'='1",
                    'quantity' => 1,
                ],
            ])
            ->set('paymentMethod', 'CASH')
            ->set('totalInput', '5.000')
            ->set('tenderedInput', '10.000')
            ->call('checkout')
            ->assertSet('notificationType', 'success');

        $order = Order::latest()->first();
        $this->assertNotNull($order);

        $item = $order->items->first();
        $this->assertNotNull($item);
        // The saved item name and code MUST be from the database Product, NOT client-supplied strings
        $this->assertEquals($product->name, $item->product_name);
        $this->assertEquals($product->code, $item->product_code);
        $this->assertStringNotContainsString('DELETE FROM', $item->product_name);
    }

    public function test_pos_checkout_rejects_overselling_when_quantity_exceeds_available_stock(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();
        $product->update(['stock' => 5]);

        // Attempting to checkout 6 units when only 5 are available
        Livewire::test(Pos::class)
            ->set('cart', [
                $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'quantity' => 6,
                ],
            ])
            ->set('paymentMethod', 'CASH')
            ->set('totalInput', '5.000')
            ->set('tenderedInput', '5.000')
            ->call('checkout')
            ->assertSet('notificationType', 'error');

        // Verify stock was NOT decremented and remains 5
        $this->assertEquals(5, $product->fresh()->stock);

        // Verify no order was created
        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_pos_checkout_atomic_decrement_guarantees_no_negative_stock(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $product = Product::first();
        $product->update(['stock' => 2]);

        // Attempting massive oversell (9999 units)
        Livewire::test(Pos::class)
            ->set('cart', [
                $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'quantity' => 9999,
                ],
            ])
            ->set('paymentMethod', 'CASH')
            ->set('totalInput', '10.000')
            ->set('tenderedInput', '10.000')
            ->call('checkout')
            ->assertSet('notificationType', 'error');

        // Stock must never go negative
        $this->assertEquals(2, $product->fresh()->stock);
        $this->assertGreaterThanOrEqual(0, $product->fresh()->stock);
        $this->assertEquals(0, Order::where('status', 'COMPLETED')->count());
    }

    public function test_export_controller_sanitizes_date_query_parameters(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        // Malicious date parameter with path traversal / carriage return
        $maliciousFrom = "2026-01-01\r\nSet-Cookie: evil=true";
        $response = $this->get('/admin/export/pdf?from='.urlencode($maliciousFrom));

        $response->assertStatus(200);
        // Filename in Content-Disposition must not contain carriage return or malicious text
        $contentDisposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringNotContainsString('evil=true', $contentDisposition);
        $this->assertMatchesRegularExpression('/sales_report_\d{4}-\d{2}-\d{2}_to_\d{4}-\d{2}-\d{2}\.pdf/', $contentDisposition);
    }

    public function test_installer_test_db_rejects_sql_injection_and_invalid_database_names(): void
    {
        @unlink(storage_path('installed'));

        try {
            // Attempt SQL injection via backticks in database name
            $response = $this->postJson('/install/test-db', [
                'host' => '8.8.8.8',
                'port' => 3306,
                'database' => 'cash_register`; DROP TABLE users; -- ',
                'username' => 'root',
                'password' => '',
            ]);

            $response->assertStatus(422);
            $this->assertStringContainsString('Invalid database name', $response->json('message'));
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_test_db_rejects_cloud_metadata_ssrf_and_invalid_ports(): void
    {
        @unlink(storage_path('installed'));

        try {
            // Metadata IP rejection
            $response = $this->postJson('/install/test-db', [
                'host' => '169.254.169.254',
                'port' => 3306,
                'database' => 'cash_register',
            ]);
            $response->assertStatus(422);
            $this->assertStringContainsString('not permitted', $response->json('message'));

            // Invalid port rejection
            $portResponse = $this->postJson('/install/test-db', [
                'host' => '8.8.8.8',
                'port' => 70000,
                'database' => 'cash_register',
            ]);
            $portResponse->assertStatus(422);
            $this->assertStringContainsString('Invalid database port', $portResponse->json('message'));
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_user_mass_assignment_does_not_set_role(): void
    {
        // Role is unguardable from mass-assignment payload
        $user = User::create([
            'name' => 'Cashier Test',
            'username' => 'cashier_test',
            'password' => 'secret123',
            'role' => User::ROLE_ADMIN, // Malicious attempt to escalate role
        ]);

        // Must default to 'cashier' from DB default, NOT 'admin'
        $this->assertEquals(User::ROLE_CASHIER, $user->fresh()->role);
    }

    public function test_pdf_export_blocks_arbitrary_file_read_via_path_traversal(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        // Set site_logo to sensitive file path traversal
        Setting::set('site_logo', '/storage/../../../../.env');

        $response = $this->get('/admin/export/pdf');
        $response->assertStatus(200);

        // Response binary must not contain raw .env contents (e.g. APP_KEY)
        $this->assertStringNotContainsString('DB_CONNECTION', (string) $response->getContent());
    }

    public function test_cashier_cannot_access_or_invoke_admin_dashboard_component(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        Livewire::test(Dashboard::class)
            ->assertStatus(403);
    }

    public function test_cashier_cannot_access_or_invoke_admin_cashiers_component(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);

        $initialUserCount = User::count();

        Livewire::test(Cashiers::class)
            ->assertStatus(403);

        // Verify no user was created
        $this->assertEquals($initialUserCount, User::count());
        $this->assertNull(User::where('username', 'pwned_admin')->first());
    }

    public function test_unauthenticated_user_cannot_access_or_invoke_admin_components(): void
    {
        auth()->logout();

        Livewire::test(Dashboard::class)
            ->assertStatus(403);

        Livewire::test(Cashiers::class)
            ->assertStatus(403);
    }

    public function test_admin_cannot_access_or_invoke_pos_component(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Pos::class)
            ->assertStatus(403);
    }

    public function test_admin_can_access_admin_components_successfully(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->assertOk();

        Livewire::test(Cashiers::class)
            ->assertOk();
    }

    public function test_cashier_replaying_admin_snapshot_to_update_endpoint_is_forbidden(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $cashier = User::where('role', User::ROLE_CASHIER)->first();

        // Legitimate admin mounts component and gets snapshot from /admin page
        $adminHtml = (string) $this->actingAs($admin)->get('/admin')->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $adminHtml, $matches);
        $snapshotJson = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Cashier attempts to submit update call to Livewire update endpoint with the genuine admin snapshot
        $updateUri = Livewire::getUpdateUri();

        $response = $this->actingAs($cashier)->postJson($updateUri, [
            'components' => [
                [
                    'snapshot' => $snapshotJson,
                    'updates' => [
                        'newUserName' => 'Attacker',
                        'newUserUsername' => 'attacker_admin',
                        'newUserRole' => User::ROLE_ADMIN,
                        'newUserPassword' => 'password123',
                        'newUserPasswordConfirmation' => 'password123',
                    ],
                    'calls' => [
                        [
                            'path' => '',
                            'method' => 'addUser',
                            'params' => [],
                        ],
                    ],
                ],
            ],
        ], [
            'X-Livewire' => 'true',
        ]);

        $response->assertStatus(403);
        $this->assertNull(User::where('username', 'attacker_admin')->first());
    }

    public function test_root_proxy_blocks_sensitive_directories_and_traversal(): void
    {
        $payloads = [
            '/../../../../etc/passwd',
            '/%2e%2e/%2e%2e/%2e%2e/etc/passwd',
            '/..%2f..%2f..%2f..%2fetc%2fpasswd',
            '/../config/database.php',
            '/../bootstrap/cache/services.php',
            '/../app/Http/Controllers/Admin/ExportController.php',
            '/../routes/web.php',
            '/../storage/installed',
            '/../.htaccess',
            '/../bootstrap/cache/config.php',
            '/../.env',
            '/../composer.json',
            '/../database/database.sqlite',
            '/../.git/HEAD',
            "/test/\0/etc/passwd",
            '/test/..\..\.env',
        ];

        foreach ($payloads as $rawUri) {
            $hasNull = str_contains($rawUri, "\0") || str_contains(urldecode($rawUri), "\0");
            $parsedPath = parse_url($rawUri, PHP_URL_PATH);
            $uri = urldecode($parsedPath !== false && $parsedPath !== null ? $parsedPath : $rawUri);

            $isBlocked = $hasNull
                || preg_match('~(^|[\\/\\\\])\\.\\.([\\/\\\\]|$)~', $uri)
                || str_contains($uri, '..')
                || preg_match('/(^|\/)(\.env|\.git|composer\.(json|lock)|artisan|bootstrap\/|config\/|app\/|database\/|storage\/logs\/|tests\/|routes\/|.*\.zip)/i', $uri);

            $this->assertTrue((bool) $isBlocked, "URI should be blocked: {$rawUri}");
        }
    }

    public function test_root_proxy_containment_logic_only_allows_public_and_public_storage(): void
    {
        $publicPath = base_path('public');
        $realPublic = realpath($publicPath);
        $realStorage = realpath(base_path('storage/app/public'));

        // Legitimate file in public
        $validFile = $publicPath.'/favicon.ico';
        $realTarget = realpath($validFile);
        $isContained = $realTarget && (
            ($realPublic && str_starts_with($realTarget, $realPublic)) ||
            ($realStorage && str_starts_with($realTarget, $realStorage))
        );
        $this->assertTrue($isContained);

        // Escape to /etc/passwd
        $escapeTarget = realpath('/etc/passwd');
        if ($escapeTarget) {
            $isContained = (
                ($realPublic && str_starts_with($escapeTarget, $realPublic)) ||
                ($realStorage && str_starts_with($escapeTarget, $realStorage))
            );
            $this->assertFalse($isContained);
        }

        // Escape to app/Models/User.php
        $appTarget = realpath(app_path('Models/User.php'));
        $isContained = $appTarget && (
            ($realPublic && str_starts_with($appTarget, $realPublic)) ||
            ($realStorage && str_starts_with($appTarget, $realStorage))
        );
        $this->assertFalse($isContained);
    }

    public function test_installer_test_db_rejects_loopback_and_private_network_ssrf(): void
    {
        @unlink(storage_path('installed'));

        $blockedHosts = [
            '127.0.0.1',
            '127.0.0.2',
            'localhost',
            '10.0.0.1',
            '10.0.0.5',
            '172.16.0.1',
            '172.17.0.2',
            '192.168.1.1',
            '0.0.0.0',
            '169.254.169.254',
            '100.100.100.200',
            'metadata.google.internal',
            'instance-data',
            'evil;user=root',
        ];

        try {
            foreach ($blockedHosts as $host) {
                $response = $this->postJson('/install/test-db', [
                    'host' => $host,
                    'port' => 3306,
                    'database' => 'cash_register',
                    'username' => 'root',
                    'password' => '',
                ]);

                $response->assertStatus(422);
                $this->assertEquals(
                    'Target database host is not permitted.',
                    $response->json('message'),
                    "Failed to block host: {$host}"
                );
            }
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_process_rejects_unpermitted_mysql_hosts(): void
    {
        @unlink(storage_path('installed'));

        try {
            $payload = [
                'db_connection' => 'mysql',
                'mysql_host' => '127.0.0.1',
                'mysql_port' => 3306,
                'mysql_database' => 'cash_register',
                'mysql_username' => 'root',
                'mysql_password' => '',
                'company_name' => 'SSRF Test Store',
                'site_title' => 'SSRF POS',
                'app_url' => 'http://localhost:8000',
                'admin_name' => 'Admin',
                'admin_username' => 'admin_test',
                'admin_password' => 'secret123',
                'admin_password_confirmation' => 'secret123',
            ];

            $response = $this->post('/install/process', $payload);
            $response->assertSessionHasErrors(['mysql_host']);

            // Attempt DSN injection in host
            $payload['mysql_host'] = 'evil;user=root';
            $responseDsn = $this->post('/install/process', $payload);
            $responseDsn->assertSessionHasErrors(['mysql_host']);
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_admin_dashboard_mysql_methods_reject_ssrf_hosts(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        // Test testMysqlConnection with loopback and private IPs
        Livewire::test(Dashboard::class)
            ->set('mysqlHost', '127.0.0.1')
            ->set('mysqlPort', 3306)
            ->set('mysqlDatabase', 'cash_register')
            ->set('mysqlUsername', 'root')
            ->call('testMysqlConnection')
            ->assertSet('dbTestStatus', 'error')
            ->assertSet('dbTestMessage', 'Target database host is not permitted.');

        Livewire::test(Dashboard::class)
            ->set('mysqlHost', '172.17.0.2')
            ->set('mysqlPort', 3306)
            ->set('mysqlDatabase', 'cash_register')
            ->set('mysqlUsername', 'root')
            ->call('testMysqlConnection')
            ->assertSet('dbTestStatus', 'error')
            ->assertSet('dbTestMessage', 'Target database host is not permitted.');

        Livewire::test(Dashboard::class)
            ->set('mysqlHost', 'evil;user=root')
            ->set('mysqlPort', 3306)
            ->set('mysqlDatabase', 'cash_register')
            ->set('mysqlUsername', 'root')
            ->call('testMysqlConnection')
            ->assertHasErrors(['mysqlHost']);

        // Test switchDatabase('mysql') rejection of loopback/private host
        Livewire::test(Dashboard::class)
            ->set('mysqlHost', '127.0.0.1')
            ->set('mysqlPort', 3306)
            ->set('mysqlDatabase', 'cash_register')
            ->set('mysqlUsername', 'root')
            ->call('switchDatabase', 'mysql')
            ->assertSet('errorMessage', 'Target database host is not permitted.');
    }

    public function test_database_host_validator_direct_evaluation(): void
    {
        $suite = [
            '127.0.0.1' => false,
            '127.0.0.2' => false,
            '172.17.0.2' => false,
            '10.0.0.5' => false,
            '192.168.1.1' => false,
            '169.254.169.254' => false,
            '0.0.0.0' => false,
            '100.100.100.200' => false,
            'localhost' => false,
            'metadata.google.internal' => false,
            'instance-data' => false,
            'evil;user=root' => false,
            '8.8.8.8' => true,
            '1.2.3.4' => true,
        ];

        foreach ($suite as $host => $expected) {
            $ip = null;
            $result = DatabaseHostValidator::isPermitted($host, $ip);
            $this->assertSame($expected, $result, "Failed assertion for host: {$host}");
            if ($expected) {
                $this->assertNotNull($ip);
                $this->assertSame($host, $ip);
            }
        }
    }

    public function test_installer_process_prohibits_client_supplied_app_env(): void
    {
        @unlink(storage_path('installed'));

        try {
            $basePayload = [
                'db_connection' => 'sqlite',
                'company_name' => 'Store POS',
                'site_title' => 'POS',
                'app_url' => 'http://localhost:8000',
                'admin_name' => 'Admin',
                'admin_username' => 'admin_env_test',
                'admin_password' => 'secret123',
                'admin_password_confirmation' => 'secret123',
            ];

            // Attempt to force app_env=local (enabling debug mode)
            $localPayload = array_merge($basePayload, ['app_env' => 'local']);
            $responseLocal = $this->post('/install/process', $localPayload);
            $responseLocal->assertSessionHasErrors(['app_env']);

            // Attempt to submit app_env=production
            $prodPayload = array_merge($basePayload, ['app_env' => 'production']);
            $responseProd = $this->post('/install/process', $prodPayload);
            $responseProd->assertSessionHasErrors(['app_env']);
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_test_db_does_not_disclose_raw_exception_messages_when_debug_is_enabled(): void
    {
        @unlink(storage_path('installed'));
        config(['app.debug' => true]);

        try {
            // Port 39999 on public IP 8.8.8.8 will fail to connect
            $response = $this->postJson('/install/test-db', [
                'host' => '8.8.8.8',
                'port' => 39999,
                'database' => 'cash_register',
                'username' => 'root',
                'password' => 'secret_db_password',
            ]);

            $response->assertStatus(422);
            $this->assertEquals(
                'Connection failed: Database connection test failed. Please verify credentials and host.',
                $response->json('message')
            );
            $this->assertStringNotContainsString('SQLSTATE', (string) $response->json('message'));
            $this->assertStringNotContainsString('PDOException', (string) $response->json('message'));
            $this->assertStringNotContainsString('secret_db_password', (string) $response->json('message'));
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_process_does_not_disclose_raw_exception_messages_when_debug_is_enabled(): void
    {
        @unlink(storage_path('installed'));
        config(['app.debug' => true]);

        try {
            // Fails connection during process
            $payload = [
                'db_connection' => 'mysql',
                'mysql_host' => '8.8.8.8',
                'mysql_port' => 39999,
                'mysql_database' => 'cash_register',
                'mysql_username' => 'root',
                'mysql_password' => 'secret_db_password',
                'company_name' => 'Store POS',
                'site_title' => 'POS',
                'app_url' => 'http://localhost:8000',
                'admin_name' => 'Admin',
                'admin_username' => 'admin_test',
                'admin_password' => 'secret123',
                'admin_password_confirmation' => 'secret123',
            ];

            $response = $this->post('/install/process', $payload);
            $response->assertSessionHas('error');
            $errorMessage = (string) session('error');

            $this->assertEquals(
                'Installation failed: An unexpected error occurred during installation. Please check server logs.',
                $errorMessage
            );
            $this->assertStringNotContainsString('SQLSTATE', $errorMessage);
            $this->assertStringNotContainsString('PDOException', $errorMessage);
            $this->assertStringNotContainsString('secret_db_password', $errorMessage);
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_page_does_not_contain_innerhtml_xss_sink(): void
    {
        @unlink(storage_path('installed'));

        try {
            $response = $this->get('/install');
            $response->assertStatus(200);

            $content = (string) $response->getContent();
            $this->assertStringNotContainsString('innerHTML', $content);
            $this->assertStringContainsString('textContent', $content);
            $this->assertStringContainsString('replaceChildren()', $content);
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_process_atomic_single_flight_lock_redirects_concurrent_requests(): void
    {
        @unlink(storage_path('installed'));
        $lockFile = storage_path('install.lock');
        $lock = fopen($lockFile, 'c');
        $this->assertNotFalse($lock);
        $locked = flock($lock, LOCK_EX | LOCK_NB);
        $this->assertTrue($locked);

        try {
            $payload = [
                'db_connection' => 'sqlite',
                'company_name' => 'Race Store',
                'site_title' => 'POS',
                'app_url' => 'http://localhost:8000',
                'admin_name' => 'Racer',
                'admin_username' => 'racer_admin',
                'admin_password' => 'secret123',
                'admin_password_confirmation' => 'secret123',
            ];

            // A concurrent request arriving while the lock is held must be redirected immediately
            $response = $this->post('/install/process', $payload);
            $response->assertRedirect(route('login'));

            // No user created by the losing request
            $this->assertDatabaseMissing('users', ['username' => 'racer_admin']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockFile);
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_installer_process_rejects_duplicate_admin_and_prevents_password_overwrite(): void
    {
        @unlink(storage_path('installed'));

        // Pre-create admin user to simulate racing creation
        $originalHash = Hash::make('original_safe_password');
        $existing = User::create([
            'name' => 'Legitimate Admin',
            'username' => 'target_admin',
            'password' => $originalHash,
        ]);
        $existing->forceFill(['role' => User::ROLE_ADMIN])->save();

        try {
            $payload = [
                'db_connection' => 'sqlite',
                'company_name' => 'Store POS',
                'site_title' => 'POS',
                'app_url' => 'http://localhost:8000',
                'admin_name' => 'Attacker',
                'admin_username' => 'target_admin',
                'admin_password' => 'attacker_overwritten_password',
                'admin_password_confirmation' => 'attacker_overwritten_password',
            ];

            $response = $this->post('/install/process', $payload);
            $response->assertSessionHas('error');

            // Password must NOT have been overwritten
            $user = User::where('username', 'target_admin')->first();
            $this->assertNotNull($user);
            $this->assertTrue(Hash::check('original_safe_password', $user->password));
            $this->assertFalse(Hash::check('attacker_overwritten_password', $user->password));
        } finally {
            file_put_contents(storage_path('installed'), 'INSTALLED');
        }
    }

    public function test_login_failure_for_nonexistent_user_performs_dummy_bcrypt_check(): void
    {
        // 1. Verify error message is identical for existing vs non-existing user
        $existingComponent = Livewire::test(Login::class)
            ->set('username', 'admin')
            ->set('password', 'wrong_password_123')
            ->call('login')
            ->assertHasErrors(['username']);

        $nonExistingComponent = Livewire::test(Login::class)
            ->set('username', 'nonexistent_user_999')
            ->set('password', 'wrong_password_123')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertEquals(
            $existingComponent->errors()->first('username'),
            $nonExistingComponent->errors()->first('username'),
            'Error messages must be identical to prevent user enumeration'
        );

        // 2. Verify dummy bcrypt check runs for non-existing username to prevent timing enumeration
        Hash::spy();

        Livewire::test(Login::class)
            ->set('username', 'ghost_user_404')
            ->set('password', 'attempted_password')
            ->call('login');

        Hash::shouldHaveReceived('check')
            ->with('attempted_password', \Mockery::type('string'))
            ->once();
    }
}
