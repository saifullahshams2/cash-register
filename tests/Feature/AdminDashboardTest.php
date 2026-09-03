<?php

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_dashboard_page(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Analytics');
        $response->assertSee('Products');
        $response->assertSee('Users');
        $response->assertSee('Settings');
    }

    public function test_cashier_is_forbidden_from_admin_dashboard(): void
    {
        $cashier = User::where('role', User::ROLE_CASHIER)->first();

        $response = $this->actingAs($cashier)->get('/admin');
        $response->assertStatus(403);

        $response2 = $this->actingAs($cashier)->get('/admin/dashboard');
        $response2->assertStatus(403);
    }

    public function test_admin_cannot_access_pos_endpoint_and_redirects_to_dashboard(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $response = $this->actingAs($admin)->get('/pos');
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_sales_analytics_computes_daily_weekly_and_monthly_metrics(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        // Create orders today
        Order::create([
            'order_number' => 'TEST-001',
            'subtotal' => 10.000,
            'total' => 10.000,
            'payment_method' => 'CASH',
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'TEST-002',
            'subtotal' => 20.000,
            'total' => 20.000,
            'payment_method' => 'KNET',
            'created_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->assertSee('30.000') // Today's total 10 + 20
            ->assertSee('This Month');
    }

    public function test_calendar_date_picker_filters_sales_by_date_range(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        $targetDate = '2026-05-15';

        $order = Order::create([
            'order_number' => 'INV-20260515-001',
            'subtotal' => 45.500,
            'total' => 45.500,
            'payment_method' => 'KNET',
        ]);
        Order::where('id', $order->id)->update(['created_at' => Carbon::parse($targetDate)->setTime(14, 30)]);

        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('fromDate', '2026-05-14')
            ->set('toDate', '2026-05-16')
            ->assertSee('INV-20260515-001')
            ->assertSee('45.500');
    }

    public function test_admin_can_add_product_with_name_only(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('tab', 'products')
            ->set('newProductName', 'Iced Spanish Latte')
            ->call('addProduct')
            ->assertSet('successMessage', 'Product added successfully!');

        $this->assertDatabaseHas('products', [
            'name' => 'Iced Spanish Latte',
        ]);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        $product = Product::first();
        $this->assertNotNull($product);

        Livewire::test(Dashboard::class)
            ->set('tab', 'products')
            ->call('deleteProduct', $product->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_admin_can_create_another_admin_from_dashboard(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('tab', 'users')
            ->set('newUserName', 'Second Admin')
            ->set('newUserUsername', 'admin_two!⚡')
            ->set('newUserRole', User::ROLE_ADMIN)
            ->set('newUserPassword', 'adminpass123')
            ->set('newUserPasswordConfirmation', 'adminpass123')
            ->call('addUser')
            ->assertSet('successMessage', 'Admin account created successfully!');

        $this->assertDatabaseHas('users', [
            'name' => 'Second Admin',
            'username' => 'admin_two!⚡',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->assertTrue(auth()->attempt(['username' => 'admin_two!⚡', 'password' => 'adminpass123']));
    }

    public function test_admin_cannot_delete_own_account_from_dashboard(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('tab', 'users')
            ->call('deleteUser', $admin->id)
            ->assertSet('errorMessage', 'You cannot delete your own logged-in admin account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_update_website_settings(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $this->actingAs($admin);

        Livewire::test(Dashboard::class)
            ->set('tab', 'settings')
            ->set('siteTitle', 'Coffee House Kuwait')
            ->call('saveSettings')
            ->assertSet('successMessage', 'Website settings updated successfully!');

        $this->assertEquals('Coffee House Kuwait', Setting::get('site_title'));
    }
}
