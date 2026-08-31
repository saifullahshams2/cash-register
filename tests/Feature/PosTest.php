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
        $response->assertSee('KWD (3 Decimals)');
    }

    public function test_can_add_product_to_cart_and_calculate_totals(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->assertSet('cart.' . $product->id . '.quantity', 1)
            ->assertSet('cart.' . $product->id . '.subtotal', (float) $product->price)
            ->call('increaseQuantity', $product->id)
            ->assertSet('cart.' . $product->id . '.quantity', 2)
            ->assertSet('cart.' . $product->id . '.subtotal', round((float) $product->price * 2, 3));
    }

    public function test_denominations_and_exact_tender(): void
    {
        $product = Product::where('price', 0.500)->first() ?? Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('setDenomination', 0.250)
            ->assertSet('tenderedInput', '0.250')
            ->call('setDenomination', 0.500)
            ->assertSet('tenderedInput', '0.500')
            ->call('setDenomination', 1.000)
            ->assertSet('tenderedInput', '1.000')
            ->call('setDenomination', 5.000)
            ->assertSet('tenderedInput', '5.000')
            ->call('setDenomination', 10.000)
            ->assertSet('tenderedInput', '10.000')
            ->call('setDenomination', 20.000)
            ->assertSet('tenderedInput', '20.000')
            ->call('setExact')
            ->assertSet('tenderedInput', number_format($product->price, 3, '.', ''));
    }

    public function test_numpad_input_and_clear(): void
    {
        Livewire::test(Pos::class)
            ->call('numpadClear')
            ->assertSet('tenderedInput', '0.000')
            ->call('numpadInput', '5')
            ->assertSet('tenderedInput', '5')
            ->call('numpadInput', '.')
            ->call('numpadInput', '2')
            ->call('numpadInput', '5')
            ->call('numpadInput', '0')
            ->assertSet('tenderedInput', '5.250')
            ->call('numpadBackspace')
            ->assertSet('tenderedInput', '5.25');
    }

    public function test_complete_checkout_saves_order_and_resets_cart_without_popup(): void
    {
        $product = Product::first();

        Livewire::test(Pos::class)
            ->call('addToCart', $product->id)
            ->call('setDenomination', 20.000)
            ->call('checkout')
            ->assertCount('cart', 0)
            ->assertSet('tenderedInput', '0.000');

        $this->assertDatabaseHas('orders', [
            'total' => $product->price,
            'tendered' => 20.000,
            'status' => 'COMPLETED',
        ]);
    }
}
