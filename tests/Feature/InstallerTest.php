<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $originalEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (file_exists(base_path('.env'))) {
            $this->originalEnv = file_get_contents(base_path('.env'));
        }
        @unlink(storage_path('installed'));
    }

    protected function tearDown(): void
    {
        // Restore installed state and .env so subsequent tests aren't impacted
        @file_put_contents(storage_path('installed'), 'INSTALLED_FOR_TESTS');
        if ($this->originalEnv !== null) {
            file_put_contents(base_path('.env'), $this->originalEnv);
        }
        parent::tearDown();
    }

    public function test_installer_page_renders_with_admin_username_and_no_email(): void
    {
        $response = $this->get('/install');
        $response->assertStatus(200);
        $response->assertSee('Setup &amp; Deployment Wizard', false);
        $response->assertSee('System &amp; Server Requirements', false);
        $response->assertSee('SQLite', false);
        $response->assertSee('MySQL / MariaDB', false);
        $response->assertSee('Admin Username', false);
        $response->assertDontSee('Admin Email', false);
    }

    public function test_installer_process_creates_first_admin_without_default_cashier(): void
    {
        $payload = [
            'db_connection' => 'sqlite',
            'company_name' => 'Metro Tech Store',
            'site_title' => 'TECH STORE POS',
            'app_url' => 'http://localhost:8000',
            'app_env' => 'production',
            'admin_name' => 'Master Admin',
            'admin_username' => 'master_pos',
            'admin_password' => 'admin_pass_123',
            'admin_password_confirmation' => 'admin_pass_123',
        ];

        $response = $this->post('/install/process', $payload);

        $response->assertStatus(200);
        $response->assertSee('Installation Completed!', false);
        $response->assertSee('master_pos', false);
        $response->assertSee('app/Installer', false);

        // Verify First Admin exists in database
        $this->assertDatabaseHas('users', [
            'name' => 'Master Admin',
            'username' => 'master_pos',
            'role' => User::ROLE_ADMIN,
        ]);

        // Verify NO default cashier was created
        $this->assertEquals(0, User::where('role', User::ROLE_CASHIER)->count());

        // Verify Store settings saved
        $this->assertEquals('Metro Tech Store', Setting::get('company_name'));
        $this->assertEquals('TECH STORE POS', Setting::get('site_title'));

        // Verify lockfile created
        $this->assertFileExists(storage_path('installed'));
    }

    public function test_installer_locks_and_redirects_to_login_when_already_installed(): void
    {
        file_put_contents(storage_path('installed'), 'INSTALLED');

        $response = $this->get('/install');
        $response->assertRedirect(route('login'));
    }

    public function test_cli_install_command(): void
    {
        $exitCode = Artisan::call('app:install', [
            '--force' => true,
            '--admin-name' => 'CLI Supervisor',
            '--admin-username' => 'cli_super',
            '--admin-password' => 'secret_cli_999',
            '--company' => 'CLI Enterprises',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertDatabaseHas('users', [
            'name' => 'CLI Supervisor',
            'username' => 'cli_super',
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertFileExists(storage_path('installed'));
    }

    public function test_uninstalled_system_redirects_visitors_to_installer(): void
    {
        @unlink(storage_path('installed'));
        User::query()->delete();

        $response = $this->get('/');
        $response->assertRedirect(route('installer.index'));

        $loginResponse = $this->get('/login');
        $loginResponse->assertRedirect(route('installer.index'));
    }

    public function test_installer_generates_and_persists_app_key_when_missing_in_env(): void
    {
        // Simulate fresh install with empty APP_KEY in .env
        $envPath = base_path('.env');
        $sampleEnv = "APP_NAME=CashRegister\nAPP_ENV=production\nAPP_KEY=\nDB_CONNECTION=sqlite\n";
        file_put_contents($envPath, $sampleEnv);

        $payload = [
            'db_connection' => 'sqlite',
            'company_name' => 'Key Test Store',
            'site_title' => 'KEY POS',
            'app_url' => 'http://localhost:8000',
            'app_env' => 'production',
            'admin_name' => 'Key Admin',
            'admin_username' => 'keyadmin',
            'admin_password' => 'secret123',
            'admin_password_confirmation' => 'secret123',
        ];

        $response = $this->post('/install/process', $payload);
        $response->assertStatus(200);

        // Verify .env now contains a valid base64 key
        $updatedEnv = file_get_contents($envPath);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=/m', $updatedEnv);

        // Verify login page renders cleanly without 500 error
        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(200);
    }
}
