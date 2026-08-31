<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Pos extends Component
{
    public string $search = '';
    public string $selectedCategory = 'ALL';
    
    /** @var array<string, array{id: int, name: string, code: string, price: float, quantity: int, subtotal: float, image: string, color: string}> */
    public array $cart = [];

    public string $tenderedInput = '0.000';
    public float $discount = 0.000;
    public float $taxRate = 0.000; // 0% tax by default in Kuwait
    public string $paymentMethod = 'CASH'; // CASH, CARD, KNET

    // Held carts management
    public array $heldCarts = [];
    public ?int $selectedHeldCartIndex = null;

    // Completed Order Modal
    public bool $showReceiptModal = false;
    public ?Order $lastOrder = null;
    public array $lastOrderItems = [];

    // Success / Error notification
    public ?string $notificationMessage = null;
    public string $notificationType = 'success';

    public function mount(): void
    {
        $this->tenderedInput = '0.000';
    }

    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        $key = (string) $product->id;

        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;
            $this->cart[$key]['subtotal'] = round($this->cart[$key]['quantity'] * $this->cart[$key]['price'], 3);
        } else {
            $this->cart[$key] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => (float) $product->price,
                'quantity' => 1,
                'subtotal' => (float) $product->price,
                'image' => $product->image ?? '📦',
                'color' => $product->color ?? 'emerald',
            ];
        }

        $this->autoUpdateExactIfMatched();
    }

    public function increaseQuantity(int $productId): void
    {
        $key = (string) $productId;
        if (isset($this->cart[$key])) {
            $this->cart[$key]['quantity']++;
            $this->cart[$key]['subtotal'] = round($this->cart[$key]['quantity'] * $this->cart[$key]['price'], 3);
            $this->autoUpdateExactIfMatched();
        }
    }

    public function decreaseQuantity(int $productId): void
    {
        $key = (string) $productId;
        if (isset($this->cart[$key])) {
            if ($this->cart[$key]['quantity'] > 1) {
                $this->cart[$key]['quantity']--;
                $this->cart[$key]['subtotal'] = round($this->cart[$key]['quantity'] * $this->cart[$key]['price'], 3);
            } else {
                unset($this->cart[$key]);
            }
            $this->autoUpdateExactIfMatched();
        }
    }

    public function removeFromCart(int $productId): void
    {
        $key = (string) $productId;
        if (isset($this->cart[$key])) {
            unset($this->cart[$key]);
            $this->autoUpdateExactIfMatched();
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->tenderedInput = '0.000';
        $this->discount = 0.000;
        $this->notify('Cart cleared', 'info');
    }

    public function setCategory(string $category): void
    {
        $this->selectedCategory = $category;
    }

    // --- Denominations & Numpad ---

    public function setExact(): void
    {
        $total = $this->getTotalProperty();
        $this->tenderedInput = number_format($total, 3, '.', '');
    }

    public function addDenomination(float $amount): void
    {
        $current = (float) $this->tenderedInput;
        $newAmount = round($current + $amount, 3);
        $this->tenderedInput = number_format($newAmount, 3, '.', '');
    }

    public function setDenomination(float $amount): void
    {
        $this->tenderedInput = number_format($amount, 3, '.', '');
    }

    public function numpadInput(string $char): void
    {
        // Clean input
        $val = $this->tenderedInput;

        if ($val === '0.000' || $val === '0' || $val === '0.00' || $val === '0.0') {
            if ($char === '.') {
                $this->tenderedInput = '0.';
            } else {
                $this->tenderedInput = $char;
            }
            return;
        }

        if ($char === '.') {
            if (!str_contains($val, '.')) {
                $this->tenderedInput = $val . '.';
            }
            return;
        }

        // Limit to max 3 decimal places if decimal exists
        if (str_contains($val, '.')) {
            $parts = explode('.', $val);
            if (isset($parts[1]) && strlen($parts[1]) >= 3 && $char !== '') {
                return; // already has 3 decimal digits
            }
        }

        $this->tenderedInput = $val . $char;
    }

    public function numpadBackspace(): void
    {
        $val = $this->tenderedInput;
        if (strlen($val) <= 1 || $val === '0.000') {
            $this->tenderedInput = '0.000';
        } else {
            $this->tenderedInput = substr($val, 0, -1);
            if ($this->tenderedInput === '' || $this->tenderedInput === '0.') {
                $this->tenderedInput = '0.000';
            }
        }
    }

    public function numpadClear(): void
    {
        $this->tenderedInput = '0.000';
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        if ($method === 'CARD' || $method === 'KNET') {
            // For card/knet, tendered is automatically exact
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
        $this->tenderedInput = '0.000';
        $this->notify('Order held successfully (' . count($this->heldCarts) . ' held)', 'info');
    }

    public function restoreHeldCart(int $index): void
    {
        if (isset($this->heldCarts[$index])) {
            $this->cart = $this->heldCarts[$index]['cart'];
            array_splice($this->heldCarts, $index, 1);
            $this->notify('Held order restored', 'success');
        }
    }

    // --- Checkout ---

    public function checkout(): void
    {
        if (empty($this->cart)) {
            $this->notify('Please add items to cart first', 'error');
            return;
        }

        $total = $this->getTotalProperty();
        $tendered = (float) $this->tenderedInput;

        if ($this->paymentMethod === 'CASH' && $tendered < $total) {
            $shortage = number_format($total - $tendered, 3, '.', '');
            $this->notify("Tendered amount is short by {$shortage} KWD", 'error');
            return;
        }

        $subtotal = $this->getSubtotalProperty();
        $change = max(0, round($tendered - $total, 3));

        try {
            DB::beginTransaction();

            $order = Order::create([
                'order_number' => 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                'subtotal' => $subtotal,
                'discount' => $this->discount,
                'tax' => round($subtotal * ($this->taxRate / 100), 3),
                'total' => $total,
                'tendered' => $tendered,
                'change' => $change,
                'payment_method' => $this->paymentMethod,
                'status' => 'COMPLETED',
                'notes' => 'Cash Register POS Checkout',
            ]);

            $orderItems = [];
            foreach ($this->cart as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'product_name' => $item['name'],
                    'product_code' => $item['code'],
                    'unit_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Reduce stock
                Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
                $orderItems[] = $orderItem->toArray();
            }

            DB::commit();

            $this->lastOrder = $order;
            $this->lastOrderItems = $this->cart;
            $this->showReceiptModal = true;

            // Reset cart
            $this->cart = [];
            $this->tenderedInput = '0.000';
            $this->discount = 0.000;
            $this->notify('Transaction completed successfully!', 'success');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->notify('Checkout failed: ' . $e->getMessage(), 'error');
        }
    }

    public function closeReceiptModal(): void
    {
        $this->showReceiptModal = false;
        $this->lastOrder = null;
        $this->lastOrderItems = [];
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

    public function getCategoriesProperty(): array
    {
        return ['ALL', 'Hot Drinks', 'Cold Drinks', 'Bakery', 'Snacks', 'Retail'];
    }

    private function autoUpdateExactIfMatched(): void
    {
        if ($this->paymentMethod !== 'CASH') {
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

        if ($this->selectedCategory !== 'ALL') {
            $productsQuery->where('category', $this->selectedCategory);
        }

        if (!empty($this->search)) {
            $search = trim($this->search);
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $products = $productsQuery->orderBy('category')->orderBy('name')->get();

        return view('livewire.pos', [
            'products' => $products,
            'subtotal' => $this->getSubtotalProperty(),
            'total' => $this->getTotalProperty(),
            'changeDue' => $this->getChangeDueProperty(),
            'categories' => $this->getCategoriesProperty(),
        ])->layout('components.layouts.app');
    }
}
