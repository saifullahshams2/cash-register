<?php

namespace Tests\Feature;

use App\Livewire\Admin\Cashiers;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_to_login_when_visiting_pos(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Sign In to Terminal');
        $response->assertSee('Username');
    }

    public function test_login_autofill_credential_helpers(): void
    {
        Livewire::test(Login::class)
            ->call('fillAdminCredentials')
            ->assertSet('username', 'admin')
            ->assertSet('password', 'password')
            ->call('fillCashierCredentials')
            ->assertSet('username', 'cashier')
            ->assertSet('password', 'password');
    }

    public function test_cashier_can_login_and_access_pos(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'cashier')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
        $this->assertEquals(User::ROLE_CASHIER, auth()->user()->role);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('CASH REGISTER');
        $response->assertSee('Cashier 01');

        $posResponse = $this->get('/pos');
        $posResponse->assertStatus(200);
        $posResponse->assertSee('CASH REGISTER');
        $posResponse->assertSee('Cashier 01');
    }

    public function test_admin_can_login_and_is_redirected_to_admin_dashboard(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'admin')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
        $this->assertEquals(User::ROLE_ADMIN, auth()->user()->role);

        $response = $this->get('/');
        $response->assertRedirect(route('admin.dashboard'));

        $posResponse = $this->get('/pos');
        $posResponse->assertRedirect(route('admin.dashboard'));
    }

    public function test_invalid_login_credentials_fail(): void
    {
        Livewire::test(Login::class)
            ->set('username', 'cashier')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['username']);

        $this->assertGuest();
    }

    public function test_cashier_cannot_access_admin_cashier_management(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $response = $this->actingAs($cashier)->get('/admin/cashiers');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_cashier_management(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $response = $this->actingAs($admin)->get('/admin/dashboard');
        $response->assertStatus(200);
        $response->assertSee('ADMIN DASHBOARD');
    }

    public function test_admin_can_create_new_cashier_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->set('name', 'Fatima Ali')
            ->set('username', 'fatima_pos')
            ->set('role', User::ROLE_CASHIER)
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('createUser')
            ->assertSet('successMessage', 'Cashier account created successfully!')
            ->assertSet('name', '')
            ->assertSet('username', '');

        $this->assertDatabaseHas('users', [
            'name' => 'Fatima Ali',
            'username' => 'fatima_pos',
            'role' => User::ROLE_CASHIER,
        ]);

        // Verify the new cashier can login
        $this->assertTrue(auth()->attempt(['username' => 'fatima_pos', 'password' => 'secret123']));
    }

    public function test_admin_can_create_another_admin_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->set('name', 'Super Admin 2')
            ->set('username', 'admin2')
            ->set('role', User::ROLE_ADMIN)
            ->set('password', 'adminpassword')
            ->set('password_confirmation', 'adminpassword')
            ->call('createUser')
            ->assertSet('successMessage', 'Admin account created successfully!')
            ->assertSet('name', '')
            ->assertSet('username', '');

        $this->assertDatabaseHas('users', [
            'name' => 'Super Admin 2',
            'username' => 'admin2',
            'role' => User::ROLE_ADMIN,
        ]);

        $createdAdmin = User::where('username', 'admin2')->first();
        $this->assertTrue($createdAdmin->isAdmin());
        $this->assertTrue(auth()->attempt(['username' => 'admin2', 'password' => 'adminpassword']));
    }

    public function test_username_supports_any_characters(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->actingAs($admin);

        $specialUsername = 'User #123 @pos-terminal!_⚡';

        Livewire::test(Cashiers::class)
            ->set('name', 'Special Cashier')
            ->set('username', $specialUsername)
            ->set('role', User::ROLE_CASHIER)
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('createUser')
            ->assertSet('successMessage', 'Cashier account created successfully!');

        $this->assertDatabaseHas('users', [
            'name' => 'Special Cashier',
            'username' => $specialUsername,
            'role' => User::ROLE_CASHIER,
        ]);

        $this->assertTrue(auth()->attempt(['username' => $specialUsername, 'password' => 'secret123']));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->call('deleteUser', $admin->id)
            ->assertSet('errorMessage', 'You cannot delete your own logged-in admin account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_cashier_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->call('deleteUser', $cashier->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseMissing('users', ['id' => $cashier->id]);
    }

    public function test_user_can_logout_and_redirect_to_login(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($cashier);
        $this->assertAuthenticated();

        $response = $this->post('/logout');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
