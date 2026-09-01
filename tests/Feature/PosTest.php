<?php

namespace Tests\Feature;

use App\Livewire\Pos;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pos_page_renders_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('CASH REGISTER');
        $response->assertSee('1. Total');
        $response->assertSee('2. Cash');
        $response->assertSee('3. Change');
        $response->assertSee('1. CASH');
        $response->assertSee('2. K-NET');
    }

    public function test_can_add_product_with_manual_price_from_numpad(): void
    {
        $product = Product::first();

        // 1. Enter manual price 2.350 on numpad
        // 2. Click product
        $key = $product->id . '_2350';

        Livewire::test(Pos::class)
            ->call('numpadInput', '2')
            ->call('numpadInput', '.')
            ->call('numpadInput', '3')
            ->call('numpadInput', '5')
            ->call('numpadInput', '0')
            ->assertSet('numpadInput', '2.350')
            ->call('addToCart', $product->id)
            ->assertSet("cart.{$key}.price", 2.350)
            ->assertSet("cart.{$key}.quantity", 1)
            ->assertSet("cart.{$key}.subtotal", 2.350)
            ->assertSet('numpadInput', '0.000');
    }

    public function test_cannot_checkout_without_selecting_payment_method(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 1.500)
            ->call('checkout')
            ->assertSet('notificationMessage', 'Please select Cash or K-Net before checkout')
            ->assertCount('cart', 1);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_checkout_flow(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 1.500)
            ->call('setPaymentMethod', 'CASH')
            ->call('setDenomination', 5.000)
            ->assertSet('tenderedInput', '5.000')
            ->call('checkout')
            ->assertCount('cart', 0)
            ->assertSet('paymentMethod', null);

        $this->assertDatabaseHas('orders', [
            'total' => 1.500,
            'tendered' => 5.000,
            'change' => 3.500,
            'payment_method' => 'CASH',
            'status' => 'COMPLETED',
        ]);
    }

    public function test_knet_checkout_flow(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 3.250)
            ->call('setPaymentMethod', 'KNET')
            ->assertSet('tenderedInput', '3.250')
            ->call('checkout')
            ->assertCount('cart', 0)
            ->assertSet('paymentMethod', null);

        $this->assertDatabaseHas('orders', [
            'total' => 3.250,
            'tendered' => 3.250,
            'change' => 0.000,
            'payment_method' => 'KNET',
            'status' => 'COMPLETED',
        ]);
    }

    public function test_has_exactly_six_priceless_products(): void
    {
        $this->assertEquals(6, Product::count());
        $this->assertEquals(0.000, (float) Product::first()->price);
    }

    public function test_tender_cash_multi_selector_and_clear(): void
    {
        Livewire::test(Pos::class)
            ->call('addTender', 20.000)
            ->assertSet('tenderedInput', '20.000')
            ->call('addTender', 10.000)
            ->assertSet('tenderedInput', '30.000')
            ->call('addTender', 0.500)
            ->assertSet('tenderedInput', '30.500')
            ->call('clearTender')
            ->assertSet('tenderedInput', '0.000');
    }
}

