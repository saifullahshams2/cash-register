<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Pos extends Component
{
    public string $search = '';

    /** @var array<string, array{id: int, name: string, code: string, price: float, quantity: int, subtotal: float}> */
    public array $cart = [];

    // Numpad input buffer (for manual price or cash tender)
    public string $numpadInput = '0.000';

    public string $tenderedInput = '0.000';

    public float $discount = 0.000;

    public float $taxRate = 0.000;

    // Payment method: null until explicitly clicked ('CASH' or 'KNET')
    public ?string $paymentMethod = null;

    // Held carts
    public array $heldCarts = [];

    // Notification toast
    public ?string $notificationMessage = null;

    public string $notificationType = 'success';

    public function mount(): void
    {
        $this->numpadInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
    }

    /**
     * Add product to cart with manual price from numpad if set,
     * or fallback to product's baseline price.
     */
    public function addToCart(int $productId, ?float $customPrice = null): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        // Determine price: custom passed > numpad buffer > product database price > 0.000
        if ($customPrice !== null) {
            $price = round($customPrice, 3);
        } elseif ((float) $this->numpadInput > 0) {
            $price = round((float) $this->numpadInput, 3);
            // Reset numpad buffer for next item
            $this->numpadInput = '0.000';
        } elseif ((float) $product->price > 0) {
            $price = (float) $product->price;
        } else {
            $price = 0.000;
        }

        $fils = (int) round($price * 1000);
        $key = $product->id.'_'.$fils;

        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;
            $this->cart[$key]['subtotal'] = round($this->cart[$key]['quantity'] * $this->cart[$key]['price'], 3);
        } else {
            $this->cart[$key] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $price,
                'quantity' => 1,
                'subtotal' => $price,
            ];
        }

        $this->autoUpdateExactIfMatched();
    }

    public function increaseQuantity(string $itemKey): void
    {
        if (isset($this->cart[$itemKey])) {
            $this->cart[$itemKey]['quantity']++;
            $this->cart[$itemKey]['subtotal'] = round($this->cart[$itemKey]['quantity'] * $this->cart[$itemKey]['price'], 3);
            $this->autoUpdateExactIfMatched();
        }
    }

    public function decreaseQuantity(string $itemKey): void
    {
        if (isset($this->cart[$itemKey])) {
            if ($this->cart[$itemKey]['quantity'] > 1) {
                $this->cart[$itemKey]['quantity']--;
                $this->cart[$itemKey]['subtotal'] = round($this->cart[$itemKey]['quantity'] * $this->cart[$itemKey]['price'], 3);
            } else {
                unset($this->cart[$itemKey]);
            }
            $this->autoUpdateExactIfMatched();
        }
    }

    public function removeFromCart(string $itemKey): void
    {
        if (isset($this->cart[$itemKey])) {
            unset($this->cart[$itemKey]);
            $this->autoUpdateExactIfMatched();
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->numpadInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
        $this->discount = 0.000;
        $this->notify('Cart cleared', 'info');
    }

    // --- Tender Cash Multi-Selector & Actions ---

    public function setExact(): void
    {
        $total = $this->getTotalProperty();
        $this->tenderedInput = number_format($total, 3, '.', '');
        if ($this->paymentMethod === null) {
            $this->paymentMethod = 'CASH';
        }
    }

    public function addTender(float $amount): void
    {
        $current = (float) $this->tenderedInput;
        $newAmount = round($current + $amount, 3);
        $this->tenderedInput = number_format($newAmount, 3, '.', '');
        $this->paymentMethod = 'CASH';
    }

    public function clearTender(): void
    {
        $this->tenderedInput = '0.000';
    }

    public function setDenomination(float $amount): void
    {
        $this->addTender($amount);
    }

    public function addDenomination(float $amount): void
    {
        $this->addTender($amount);
    }

    /**
     * Numpad digit handling starting from most left with up to 3 decimal digits for Kuwaiti Dinar
     */
    public function numpadInput(string $char): void
    {
        $val = $this->numpadInput;

        if ($val === '0.000' || $val === '0' || $val === '0.00' || $val === '0.0') {
            if ($char === '.') {
                $this->numpadInput = '0.';
            } elseif ($char === '00' || $char === '0') {
                $this->numpadInput = '0';
            } else {
                $this->numpadInput = $char;
            }

            return;
        }

        if ($char === '.') {
            if (! str_contains($val, '.')) {
                $this->numpadInput = $val.'.';
            }

            return;
        }

        // Decimal precision constraint: max 3 decimals for Kuwaiti Dinar (fils)
        if (str_contains($val, '.')) {
            $parts = explode('.', $val);
            if (isset($parts[1]) && strlen($parts[1]) >= 3 && $char !== '') {
                return;
            }
        }

        $this->numpadInput = $val.$char;
    }

    public function numpadBackspace(): void
    {
        $val = $this->numpadInput;
        if (strlen($val) <= 1 || $val === '0.000') {
            $this->numpadInput = '0.000';
        } else {
            $this->numpadInput = substr($val, 0, -1);
            if ($this->numpadInput === '' || $this->numpadInput === '0.') {
                $this->numpadInput = '0.000';
            }
        }
    }

    public function numpadClear(): void
    {
        $this->numpadInput = '0.000';
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;

        if ($method === 'KNET') {
            $this->setExact();
        }
    }

    // --- Hold / Resume Cart ---

    public function holdCart(): void
    {
        if (empty($this->cart)) {
            $this->notify('Cart is empty', 'error');

            return;
        }

        $this->heldCarts[] = [
            'id' => uniqid('HOLD-'),
            'time' => now()->format('h:i A'),
            'cart' => $this->cart,
            'total' => $this->getTotalProperty(),
            'item_count' => count($this->cart),
        ];

        $this->cart = [];
        $this->numpadInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
        $this->notify('Order held ('.count($this->heldCarts).' in queue)', 'info');
    }

    public function restoreHeldCart(int $index): void
    {
        if (isset($this->heldCarts[$index])) {
            $this->cart = $this->heldCarts[$index]['cart'];
            array_splice($this->heldCarts, $index, 1);
            $this->paymentMethod = null;
            $this->notify('Held order restored', 'success');
        }
    }

    // --- Checkout with Payment Guard ---

    public function checkout(): void
    {
        if (empty($this->cart)) {
            $this->notify('Please add items to cart first', 'error');

            return;
        }

        if (empty($this->paymentMethod)) {
            $this->notify('Please select Cash or K-Net before checkout', 'error');

            return;
        }

        $total = $this->getTotalProperty();
        $tendered = (float) $this->tenderedInput;

        if ($this->paymentMethod === 'CASH' && $tendered < $total) {
            $shortage = number_format($total - $tendered, 3, '.', '');
            $this->notify("Cash is short by {$shortage} KWD", 'error');

            return;
        }

        $subtotal = $this->getSubtotalProperty();
        $change = max(0, round($tendered - $total, 3));

        try {
            DB::beginTransaction();

            $orderNumber = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $order = Order::create([
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'discount' => $this->discount,
                'tax' => round($subtotal * ($this->taxRate / 100), 3),
                'total' => $total,
                'tendered' => $tendered,
                'change' => $change,
                'payment_method' => $this->paymentMethod,
                'status' => 'COMPLETED',
                'notes' => 'Cash Register Direct Checkout',
            ]);

            foreach ($this->cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'product_code' => $item['code'],
                    'unit_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
            }

            DB::commit();

            // Reset cart & inputs immediately
            $this->cart = [];
            $this->numpadInput = '0.000';
            $this->tenderedInput = '0.000';
            $this->paymentMethod = null;
            $this->discount = 0.000;

            $changeFormatted = number_format($change, 3, '.', '');
            $this->notify("Saved {$orderNumber} | Change: {$changeFormatted} KWD", 'success');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->notify('Checkout error: '.$e->getMessage(), 'error');
        }
    }

    // --- Computed Properties ---

    public function getSubtotalProperty(): float
    {
        return round(array_sum(array_column($this->cart, 'subtotal')), 3);
    }

    public function getTotalProperty(): float
    {
        $sub = $this->getSubtotalProperty();
        $afterDiscount = max(0, $sub - $this->discount);
        $tax = round($afterDiscount * ($this->taxRate / 100), 3);

        return round($afterDiscount + $tax, 3);
    }

    public function getChangeDueProperty(): float
    {
        $tendered = (float) $this->tenderedInput;
        $total = $this->getTotalProperty();

        return round($tendered - $total, 3);
    }

    private function autoUpdateExactIfMatched(): void
    {
        if ($this->paymentMethod === 'KNET') {
            $this->setExact();
        }
    }

    private function notify(string $message, string $type = 'success'): void
    {
        $this->notificationMessage = $message;
        $this->notificationType = $type;
    }

    public function render()
    {
        $productsQuery = Product::query()->where('is_active', true);

        if (! empty($this->search)) {
            $search = trim($this->search);
            $productsQuery->where('name', 'like', "%{$search}%");
        }

        $products = $productsQuery->orderBy('name')->get();

        return view('livewire.pos', [
            'products' => $products,
            'subtotal' => $this->getSubtotalProperty(),
            'total' => $this->getTotalProperty(),
            'changeDue' => $this->getChangeDueProperty(),
        ])->layout('components.layouts.app');
    }
}
