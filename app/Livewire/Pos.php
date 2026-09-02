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

    /** @var array<int|string, array{id: int, name: string, code: string, quantity: int}> */
    public array $cart = [];

    // Numpad input buffer for manual total price
    public string $totalInput = '0.000';

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
        $this->totalInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
    }

    /**
     * Add product to cart (only quantity tracked, no individual price)
     */
    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $id = $product->id;

        if (isset($this->cart[$id])) {
            $this->cart[$id]['quantity']++;
        } else {
            $this->cart[$id] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'quantity' => 1,
            ];
        }
    }

    public function increaseQuantity(int|string $productId): void
    {
        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity']++;
        }
    }

    public function decreaseQuantity(int|string $productId): void
    {
        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] > 1) {
                $this->cart[$productId]['quantity']--;
            } else {
                unset($this->cart[$productId]);
            }
        }
    }

    public function removeFromCart(int|string $productId): void
    {
        if (isset($this->cart[$productId])) {
            unset($this->cart[$productId]);
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->totalInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
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
     * Numpad digit handling directly updates total price with up to 3 decimal digits for Kuwaiti Dinar
     */
    public function numpadInput(string $char): void
    {
        $val = $this->totalInput;

        if ($val === '0.000' || $val === '0' || $val === '0.00' || $val === '0.0') {
            if ($char === '.') {
                $this->totalInput = '0.';
            } elseif ($char === '00' || $char === '0') {
                $this->totalInput = '0';
            } else {
                $this->totalInput = $char;
            }
            $this->autoUpdateExactIfMatched();

            return;
        }

        if ($char === '.') {
            if (! str_contains($val, '.')) {
                $this->totalInput = $val.'.';
            }
            $this->autoUpdateExactIfMatched();

            return;
        }

        // Decimal precision constraint: max 3 decimals for Kuwaiti Dinar (fils)
        if (str_contains($val, '.')) {
            $parts = explode('.', $val);
            if (isset($parts[1]) && strlen($parts[1]) >= 3 && $char !== '') {
                return;
            }
        }

        $this->totalInput = $val.$char;
        $this->autoUpdateExactIfMatched();
    }

    public function numpadBackspace(): void
    {
        $val = $this->totalInput;
        if (strlen($val) <= 1 || $val === '0.000') {
            $this->totalInput = '0.000';
        } else {
            $this->totalInput = substr($val, 0, -1);
            if ($this->totalInput === '' || $this->totalInput === '0.') {
                $this->totalInput = '0.000';
            }
        }
        $this->autoUpdateExactIfMatched();
    }

    public function numpadClear(): void
    {
        $this->totalInput = '0.000';
        $this->autoUpdateExactIfMatched();
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
            'totalInput' => $this->totalInput,
            'total' => $this->getTotalProperty(),
            'item_count' => count($this->cart),
        ];

        $this->cart = [];
        $this->totalInput = '0.000';
        $this->tenderedInput = '0.000';
        $this->paymentMethod = null;
        $this->notify('Order held ('.count($this->heldCarts).' in queue)', 'info');
    }

    public function restoreHeldCart(int $index): void
    {
        if (isset($this->heldCarts[$index])) {
            $this->cart = $this->heldCarts[$index]['cart'];
            $this->totalInput = $this->heldCarts[$index]['totalInput'] ?? '0.000';
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

        $change = max(0, round($tendered - $total, 3));

        try {
            DB::beginTransaction();

            $orderNumber = 'INV-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -4));

            $order = Order::create([
                'order_number' => $orderNumber,
                'subtotal' => $total,
                'discount' => 0.000,
                'tax' => 0.000,
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
                    'unit_price' => 0.000,
                    'quantity' => $item['quantity'],
                    'subtotal' => 0.000,
                ]);

                Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
            }

            DB::commit();

            // Reset cart & inputs immediately
            $this->cart = [];
            $this->totalInput = '0.000';
            $this->tenderedInput = '0.000';
            $this->paymentMethod = null;

            $changeFormatted = number_format($change, 3, '.', '');
            $this->notify("Saved {$orderNumber} | Change: {$changeFormatted} KWD", 'success');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->notify('Checkout error: '.$e->getMessage(), 'error');
        }
    }

    // --- Computed Properties ---

    public function getTotalProperty(): float
    {
        return round((float) $this->totalInput, 3);
    }

    public function getChangeDueProperty(): float
    {
        $tendered = (float) $this->tenderedInput;
        $total = $this->getTotalProperty();

        return round($tendered - $total, 3);
    }

    public function getTotalQuantityProperty(): int
    {
        return (int) array_sum(array_column($this->cart, 'quantity'));
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
            'total' => $this->getTotalProperty(),
            'totalQuantity' => $this->getTotalQuantityProperty(),
            'changeDue' => $this->getChangeDueProperty(),
        ])->layout('components.layouts.app');
    }
}
