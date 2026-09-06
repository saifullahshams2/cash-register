<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\SalesExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                'host' => '127.0.0.1',
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
                'host' => '127.0.0.1',
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
}
