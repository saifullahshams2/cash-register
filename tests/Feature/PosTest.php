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

    public function test_can_input_total_price_from_numpad(): void
    {
        Livewire::test(Pos::class)
            ->call('numpadInput', '2')
            ->call('numpadInput', '.')
            ->call('numpadInput', '3')
            ->call('numpadInput', '5')
            ->call('numpadInput', '0')
            ->assertSet('totalInput', '2.350')
            ->assertSee('2.350');
    }

    public function test_can_add_product_to_cart_with_quantity_only(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->assertSet("cart.{$product->id}.id", $product->id)
            ->assertSet("cart.{$product->id}.quantity", 1)
            ->assertSet("cart.{$product->id}.name", $product->name);
    }

    public function test_cannot_checkout_without_selecting_payment_method(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '1')
            ->call('numpadInput', '.')
            ->call('numpadInput', '5')
            ->call('checkout')
            ->assertSet('notificationMessage', 'Please select Cash or K-Net before checkout')
            ->assertCount('cart', 1);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_checkout_flow(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '1')
            ->call('numpadInput', '.')
            ->call('numpadInput', '5')
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
            ->call('addToCart', $product->id)
            ->call('numpadInput', '3')
            ->call('numpadInput', '.')
            ->call('numpadInput', '2')
            ->call('numpadInput', '5')
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

    public function test_cash_shortage_prevents_checkout(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '5')
            ->call('setPaymentMethod', 'CASH')
            ->call('setDenomination', 3.000)
            ->call('checkout')
            ->assertSet('notificationMessage', 'Cash is short by 2.000 KWD')
            ->assertSet('notificationType', 'error')
            ->assertCount('cart', 1);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cart_quantity_modifications_and_removal(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->assertSet("cart.{$product->id}.quantity", 1)
            ->call('increaseQuantity', $product->id)
            ->assertSet("cart.{$product->id}.quantity", 2)
            ->call('decreaseQuantity', $product->id)
            ->assertSet("cart.{$product->id}.quantity", 1)
            ->call('decreaseQuantity', $product->id)
            ->assertCount('cart', 0);
    }

    public function test_remove_from_cart_and_clear_cart(): void
    {
        $products = Product::take(2)->get();

        Livewire::test(Pos::class)
            ->call('addToCart', $products[0]->id)
            ->call('addToCart', $products[1]->id)
            ->assertCount('cart', 2)
            ->call('removeFromCart', $products[0]->id)
            ->assertCount('cart', 1)
            ->call('clearCart')
            ->assertCount('cart', 0)
            ->assertSet('totalInput', '0.000')
            ->assertSet('tenderedInput', '0.000')
            ->assertSet('paymentMethod', null);
    }

    public function test_hold_and_restore_cart_flow(): void
    {
        $product = Product::first();

        // 1. Holding empty cart should fail
        Livewire::test(Pos::class)
            ->call('holdCart')
            ->assertSet('notificationMessage', 'Cart is empty')
            ->assertSet('notificationType', 'error');

        // 2. Add product, enter total, hold cart, verify saved in heldCarts
        $test = Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '4')
            ->call('numpadInput', '.')
            ->call('numpadInput', '5')
            ->call('holdCart')
            ->assertCount('cart', 0)
            ->assertCount('heldCarts', 1)
            ->assertSet('heldCarts.0.total', 4.500);

        // 3. Restore cart
        $test->call('restoreHeldCart', 0)
            ->assertCount('cart', 1)
            ->assertSet('totalInput', '4.5')
            ->assertCount('heldCarts', 0);
    }

    public function test_stock_decrements_properly_on_checkout(): void
    {
        $product = Product::first();
        $initialStock = $product->stock;

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('addToCart', $product->id) // increase quantity to 2
            ->call('numpadInput', '2')
            ->call('setPaymentMethod', 'CASH')
            ->call('setExact')
            ->call('checkout');

        $this->assertEquals($initialStock - 2, $product->fresh()->stock);
    }

    public function test_numpad_precision_and_backspace_handling(): void
    {
        Livewire::test(Pos::class)
            // Test 3 decimal limit for KWD fils
            ->call('numpadInput', '1')
            ->call('numpadInput', '.')
            ->call('numpadInput', '2')
            ->call('numpadInput', '3')
            ->call('numpadInput', '4')
            ->call('numpadInput', '5') // Exceeds 3 decimals, should be ignored
            ->assertSet('totalInput', '1.234')
            // Test backspace
            ->call('numpadBackspace')
            ->assertSet('totalInput', '1.23')
            ->call('numpadBackspace')
            ->assertSet('totalInput', '1.2')
            ->call('numpadClear')
            ->assertSet('totalInput', '0.000');
    }

    public function test_knet_auto_updates_exact_amount_on_total_input_changes(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '2')
            ->call('setPaymentMethod', 'KNET')
            ->assertSet('tenderedInput', '2.000')
            ->call('numpadInput', '.')
            ->call('numpadInput', '5')
            ->assertSet('tenderedInput', '2.500')
            ->call('numpadBackspace')
            ->assertSet('tenderedInput', '2.000');
    }

    public function test_product_search_filtering(): void
    {
        Livewire::test(Pos::class)
            ->set('search', 'Coffee')
            ->assertSee('Coffee')
            ->assertDontSee('Sandwiches & Snacks')
            ->set('search', 'NonExistentProduct')
            ->assertSee('No products found');
    }

    public function test_order_model_relationships(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('numpadInput', '2')
            ->call('numpadInput', '.')
            ->call('numpadInput', '7')
            ->call('numpadInput', '5')
            ->call('setPaymentMethod', 'KNET')
            ->call('checkout');

        $order = Order::with('items.product')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        $this->assertEquals($product->id, $order->items->first()->product_id);
        $this->assertEquals($product->name, $order->items->first()->product_name);
        $this->assertEquals(2.750, (float) $order->total);
        $this->assertEquals($order->id, $order->items->first()->order->id);
    }
}
