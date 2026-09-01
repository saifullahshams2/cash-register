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
        $key = $product->id.'_2350';

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

    public function test_cash_shortage_prevents_checkout(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 5.000)
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
        $key = $product->id.'_2000';

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 2.000)
            ->assertSet("cart.{$key}.quantity", 1)
            ->assertSet("cart.{$key}.subtotal", 2.000)
            ->call('increaseQuantity', $key)
            ->assertSet("cart.{$key}.quantity", 2)
            ->assertSet("cart.{$key}.subtotal", 4.000)
            ->call('decreaseQuantity', $key)
            ->assertSet("cart.{$key}.quantity", 1)
            ->assertSet("cart.{$key}.subtotal", 2.000)
            ->call('decreaseQuantity', $key)
            ->assertCount('cart', 0);
    }

    public function test_remove_from_cart_and_clear_cart(): void
    {
        $products = Product::take(2)->get();
        $key1 = $products[0]->id.'_1000';
        $key2 = $products[1]->id.'_2000';

        Livewire::test(Pos::class)
            ->call('addToCart', $products[0]->id, 1.000)
            ->call('addToCart', $products[1]->id, 2.000)
            ->assertCount('cart', 2)
            ->call('removeFromCart', $key1)
            ->assertCount('cart', 1)
            ->assertSet("cart.{$key2}.subtotal", 2.000)
            ->call('clearCart')
            ->assertCount('cart', 0)
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

        // 2. Add product, hold cart, verify saved in heldCarts
        $test = Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 4.500)
            ->call('holdCart')
            ->assertCount('cart', 0)
            ->assertCount('heldCarts', 1)
            ->assertSet('heldCarts.0.total', 4.500);

        // 3. Restore cart
        $test->call('restoreHeldCart', 0)
            ->assertCount('cart', 1)
            ->assertCount('heldCarts', 0);
    }

    public function test_stock_decrements_properly_on_checkout(): void
    {
        $product = Product::first();
        $initialStock = $product->stock;

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id, 1.000)
            ->call('addToCart', $product->id, 1.000) // increase quantity to 2
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
            ->assertSet('numpadInput', '1.234')
            // Test backspace
            ->call('numpadBackspace')
            ->assertSet('numpadInput', '1.23')
            ->call('numpadBackspace')
            ->assertSet('numpadInput', '1.2')
            ->call('numpadClear')
            ->assertSet('numpadInput', '0.000');
    }

    public function test_knet_auto_updates_exact_amount_on_cart_changes(): void
    {
        $products = Product::take(2)->get();
        $key1 = $products[0]->id.'_2000';
        $key2 = $products[1]->id.'_3500';

        Livewire::test(Pos::class)
            ->call('addToCart', $products[0]->id, 2.000)
            ->call('setPaymentMethod', 'KNET')
            ->assertSet('tenderedInput', '2.000')
            // Adding another item should automatically sync exact amount for KNET
            ->call('addToCart', $products[1]->id, 3.500)
            ->assertSet('tenderedInput', '5.500')
            // Increasing quantity should auto sync
            ->call('increaseQuantity', $key1)
            ->assertSet('tenderedInput', '7.500')
            // Removing item should auto sync
            ->call('removeFromCart', $key2)
            ->assertSet('tenderedInput', '4.000');
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
            ->call('addToCart', $product->id, 2.750)
            ->call('setPaymentMethod', 'KNET')
            ->call('checkout');

        $order = Order::with('items.product')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->items);
        $this->assertEquals($product->id, $order->items->first()->product_id);
        $this->assertEquals($product->name, $order->items->first()->product_name);
        $this->assertEquals(2.750, (float) $order->items->first()->unit_price);
        $this->assertEquals($order->id, $order->items->first()->order->id);
    }
}
