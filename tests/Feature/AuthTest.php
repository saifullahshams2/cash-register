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
        $response->assertSee('CASH REGISTER TERMINAL');
        $response->assertSee('Sign In to Terminal');
        $response->assertSee('admin@pos.test');
        $response->assertSee('cashier@pos.test');
    }

    public function test_cashier_can_login_and_access_pos(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'cashier@pos.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
        $this->assertEquals(User::ROLE_CASHIER, auth()->user()->role);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('CASH REGISTER');
        $response->assertSee('Cashier 01');
        $response->assertDontSee('⚙️ Manage Cashiers');
    }

    public function test_admin_can_login_and_access_pos_with_admin_button(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'admin@pos.test')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('pos'));

        $this->assertAuthenticated();
        $this->assertEquals(User::ROLE_ADMIN, auth()->user()->role);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Admin User');
        $response->assertSee('Cashiers');
    }

    public function test_invalid_login_credentials_fail(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'cashier@pos.test')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email']);

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
        $response = $this->actingAs($admin)->get('/admin/cashiers');
        $response->assertStatus(200);
        $response->assertSee('ADMIN PANEL: CASHIER MANAGEMENT');
        $response->assertSee('Create New Cashier');
    }

    public function test_admin_can_create_new_cashier_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->set('name', 'Fatima Ali')
            ->set('email', 'fatima@pos.test')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('createCashier')
            ->assertSet('successMessage', 'Cashier account created successfully!')
            ->assertSet('name', '')
            ->assertSet('email', '');

        $this->assertDatabaseHas('users', [
            'name' => 'Fatima Ali',
            'email' => 'fatima@pos.test',
            'role' => User::ROLE_CASHIER,
        ]);

        // Verify the new cashier can login
        $this->assertTrue(auth()->attempt(['email' => 'fatima@pos.test', 'password' => 'secret123']));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->call('deleteCashier', $admin->id)
            ->assertSet('errorMessage', 'You cannot delete your own logged-in admin account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_cashier_account(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $cashier = User::where('role', User::ROLE_CASHIER)->first();
        $this->actingAs($admin);

        Livewire::test(Cashiers::class)
            ->call('deleteCashier', $cashier->id)
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
