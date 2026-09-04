<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create([
            'name' => 'Admin User',
            'username' => 'admin',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_db_switch_command_switches_to_sqlite(): void
    {
        $exitCode = Artisan::call('db:switch', [
            'connection' => 'sqlite',
            '--force' => true,
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertFileExists(database_path('database.sqlite'));

        $envContent = file_get_contents(base_path('.env'));
        $this->assertMatchesRegularExpression('/^DB_CONNECTION=sqlite/m', $envContent);
    }

    public function test_db_switch_command_rejects_invalid_driver(): void
    {
        $exitCode = Artisan::call('db:switch', [
            'connection' => 'postgres_custom',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
    }

    public function test_db_switch_command_handles_offline_mysql_safely(): void
    {
        // Port 39999 is intentionally unreachable
        $exitCode = Artisan::call('db:switch', [
            'connection' => 'mysql',
            '--port' => '39999',
            '--host' => '127.0.0.1',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
    }

    public function test_dashboard_can_switch_to_sqlite(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('tab', 'settings')
            ->call('switchDatabase', 'sqlite')
            ->assertSet('currentDbDriver', 'sqlite')
            ->assertSet('successMessage', 'Switched active database to SQLite (database/database.sqlite)!');

        $envContent = file_get_contents(base_path('.env'));
        $this->assertMatchesRegularExpression('/^DB_CONNECTION=sqlite/m', $envContent);
    }
}
