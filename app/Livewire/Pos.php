<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Pos extends Component
{
    public string $search = '';

    /** @var array<int|string, array{id: int, name: string, code: string, quantity: int}> */
    public array $cart = [];

    public string $currency = 'KWD';

    public int $currencyDecimals = 3;

    /**
     * Keep the client-writable precision inside the range the application
     * supports. Every consumer of this value (number_format(), pow()) treats it
     * as unbounded, so it must be normalised before it is used.
     */
    public function updatedCurrencyDecimals(): void
    {
        $this->currencyDecimals = max(0, min(4, (int) $this->currencyDecimals));
    }

    // Numpad input buffer for manual total price
    public string $totalDigits = '';

    public string $totalInput = '0.000';

    public string $tenderedInput = '0.000';

    public float $discount = 0.000;

    public float $taxRate = 0.000;

    // Payment method: null until explicitly clicked ('CASH', 'CARD', or 'KNET')
    public ?string $paymentMethod = null;

    // Held carts
    public array $heldCarts = [];

    // Notification toast
    public ?string $notificationMessage = null;

    public string $notificationType = 'success';

    public bool $isProcessing = false;

    /**
     * Server-issued identifier for the current checkout submission. It travels
     * inside the signed snapshot and is marked #[Locked] so the client cannot
     * choose it: re-submitting or replaying a payload reuses the same token,
     * which the unique index on orders.checkout_token rejects.
     */
    #[Locked]
    public string $checkoutNonce = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->isCashier(), 403, 'Unauthorized. Cashier access required.');
    }

    public function mount()
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $this->currency = Setting::getCurrency();
        $this->currencyDecimals = Setting::getCurrencyDecimals();

        $this->totalDigits = '';
        $this->totalInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->tenderedInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->paymentMethod = null;
        $this->checkoutNonce = (string) Str::uuid();
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
        $this->totalDigits = '';
        $this->totalInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->tenderedInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->paymentMethod = null;
        $this->notify('Cart cleared', 'info');
    }

    // --- Tender Cash Multi-Selector & Actions ---

    public function setExact(): void
    {
        $total = $this->getTotalProperty();
        $this->tenderedInput = number_format($total, $this->currencyDecimals, '.', '');
        if ($this->paymentMethod === null) {
            $this->paymentMethod = 'CASH';
        }
    }

    public function addTender(float $amount): void
    {
        $current = (float) $this->tenderedInput;
        $newAmount = round($current + $amount, $this->currencyDecimals);
        $this->tenderedInput = number_format($newAmount, $this->currencyDecimals, '.', '');
        $this->paymentMethod = 'CASH';
    }

    public function clearTender(): void
    {
        $this->tenderedInput = number_format(0, $this->currencyDecimals, '.', '');
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
     * ATM-style right-to-left digit shifting with fixed 3 decimals
     * Example: 1 -> 0.001, 238 -> 0.238, 11234 -> 11.234
     */
    public function numpadInput(string $char): void
    {
        if ($char === '.') {
            return;
        }

        if (! in_array($char, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '00'], true)) {
            return;
        }

        // Ignore leading zeros if buffer is empty
        if ($this->totalDigits === '' && ($char === '0' || $char === '00')) {
            return;
        }

        // Prevent buffer overflow (up to 9 digits: 999,999.999 KWD)
        if (strlen($this->totalDigits) + strlen($char) > 9) {
            return;
        }

        $this->totalDigits .= $char;
        $this->updateTotalInputFromDigits();
    }

    public function numpadBackspace(): void
    {
        if (strlen($this->totalDigits) > 0) {
            $this->totalDigits = substr($this->totalDigits, 0, -1);
        }

        $this->updateTotalInputFromDigits();
    }

    public function numpadClear(): void
    {
        $this->totalDigits = '';
        $this->updateTotalInputFromDigits();
    }

    private function updateTotalInputFromDigits(): void
    {
        if ($this->totalDigits === '' || (int) $this->totalDigits === 0) {
            $this->totalDigits = '';
            $this->totalInput = number_format(0, $this->currencyDecimals, '.', '');
        } else {
            $units = (int) $this->totalDigits;
            $decimals = max(0, min(4, $this->currencyDecimals));
            $divisor = 10 ** $decimals;
            $this->totalInput = number_format($units / $divisor, $decimals, '.', '');
        }

        $this->autoUpdateExactIfMatched();
    }

    public function setPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;

        if (in_array($method, ['CARD', 'KNET'], true)) {
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
            'totalDigits' => $this->totalDigits,
            'totalInput' => $this->totalInput,
            'total' => $this->getTotalProperty(),
            'item_count' => count($this->cart),
        ];

        $this->cart = [];
        $this->totalDigits = '';
        $this->totalInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->tenderedInput = number_format(0, $this->currencyDecimals, '.', '');
        $this->paymentMethod = null;
        $this->notify('Order held ('.count($this->heldCarts).' in queue)', 'info');
    }

    public function restoreHeldCart(int $index): void
    {
        if (isset($this->heldCarts[$index])) {
            $this->cart = $this->heldCarts[$index]['cart'];
            $this->totalDigits = $this->heldCarts[$index]['totalDigits'] ?? '';
            $this->totalInput = $this->heldCarts[$index]['totalInput'] ?? number_format(0, $this->currencyDecimals, '.', '');
            array_splice($this->heldCarts, $index, 1);
            $this->paymentMethod = null;
            $this->notify('Held order restored', 'success');
        }
    }

    // --- Checkout with Payment Guard ---

    public function checkout(): void
    {
        // One submission must produce at most one sale. `isProcessing` below is
        // ordinary public state that the client re-hydrates to false on every
        // request, so it cannot detect a double click or a replayed payload.
        // Consume the snapshot's submission token instead, and rotate it so the
        // next (legitimate) sale gets a fresh one.
        $submission = $this->checkoutNonce;
        $this->checkoutNonce = (string) Str::uuid();

        if ($submission === '') {
            $this->notify('Invalid checkout submission. Please try again.', 'error');

            return;
        }
        if ($this->isProcessing) {
            return;
        }

        $this->isProcessing = true;

        if (empty($this->cart)) {
            $this->isProcessing = false;
            $this->notify('Please add items to cart first', 'error');

            return;
        }

        if (empty($this->paymentMethod)) {
            $this->isProcessing = false;
            $this->notify('Please select Cash or Card before checkout', 'error');

            return;
        }

        // Validate cart items to prevent client-side tampering (e.g. negative quantities or forged IDs)
        $productIds = array_column($this->cart, 'id');
        $products = Product::whereIn('id', $productIds)->where('is_active', true)->get()->keyBy('id');
        $validProductIds = $products->keys()->all();

        foreach ($this->cart as $item) {
            $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
            $pid = isset($item['id']) ? (int) $item['id'] : 0;

            if ($qty < 1 || $qty > 9999 || ! in_array($pid, $validProductIds, true)) {
                $this->isProcessing = false;
                $this->notify('Cart contains invalid items or quantities.', 'error');

                return;
            }

            $product = $products[$pid];
            if ($product->stock < $qty) {
                $this->isProcessing = false;
                $this->notify("Insufficient stock for {$product->name} (available: {$product->stock}).", 'error');

                return;
            }
        }

        if (! in_array($this->paymentMethod, ['CASH', 'CARD', 'KNET'], true)) {
            $this->isProcessing = false;
            $this->notify('Invalid payment method.', 'error');

            return;
        }

        if (! is_numeric($this->totalInput) || ! is_finite((float) $this->totalInput) || (float) $this->totalInput < 0 || (float) $this->totalInput > 999999999.999) {
            $this->isProcessing = false;
            $this->notify('Invalid total amount.', 'error');

            return;
        }

        $total = $this->getTotalProperty();

        if (! is_numeric($this->tenderedInput) || ! is_finite((float) $this->tenderedInput) || (float) $this->tenderedInput < 0 || (float) $this->tenderedInput > 999999999.999) {
            $this->isProcessing = false;
            $this->notify('Invalid tendered amount.', 'error');

            return;
        }

        $tendered = (float) $this->tenderedInput;

        if ($this->paymentMethod === 'CASH') {
            if ($tendered < $total) {
                $shortage = number_format($total - $tendered, $this->currencyDecimals, '.', '');
                $this->isProcessing = false;
                $this->notify("Cash is short by {$shortage} {$this->currency}", 'error');

                return;
            }
            $change = max(0, round($tendered - $total, $this->currencyDecimals));
        } else {
            $tendered = $total;
            $change = 0.000;
        }

        try {
            DB::beginTransaction();

            // uniqid()'s low 16 bits repeat every ~65ms, so the old 4-hex suffix
            // collided between simultaneous tills and the UNIQUE index then threw
            // the whole sale away. Use a random identifier instead.
            $orderNumber = 'INV-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(5)));

            $order = Order::create([
                'user_id' => Auth::id(),
                'cashier_name' => Auth::user()?->name ?? 'Cashier',
                'order_number' => $orderNumber,
                'checkout_token' => $submission,
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
                $productId = (int) $item['id'];
                $product = $products[$productId];
                $quantity = (int) $item['quantity'];

                $stockUpdated = Product::where('id', $productId)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);

                if ($stockUpdated === 0) {
                    throw new \RuntimeException("Insufficient stock for {$product->name}.");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'product_name' => (string) $product->name,
                    'product_code' => (string) $product->code,
                    'unit_price' => 0.000,
                    'quantity' => $quantity,
                    'subtotal' => 0.000,
                ]);
            }

            DB::commit();

            // Reset cart & inputs immediately
            $this->cart = [];
            $this->totalDigits = '';
            $this->totalInput = number_format(0, $this->currencyDecimals, '.', '');
            $this->tenderedInput = number_format(0, $this->currencyDecimals, '.', '');
            $this->paymentMethod = null;

            $changeFormatted = number_format($change, $this->currencyDecimals, '.', '');
            $this->notify("Saved {$orderNumber} | Change: {$changeFormatted} {$this->currency}", 'success');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('POS Checkout failed: '.$e->getMessage());
            $errorMsg = ($e instanceof \RuntimeException && ! $e instanceof \PDOException || config('app.debug'))
                ? $e->getMessage()
                : 'An error occurred while processing checkout. Please try again.';
            $this->notify('Checkout error: '.$errorMsg, 'error');
        } finally {
            $this->isProcessing = false;
        }
    }

    // --- Computed Properties ---

    public function getTotalProperty(): float
    {
        return round((float) $this->totalInput, $this->currencyDecimals);
    }

    public function getChangeDueProperty(): float
    {
        $tendered = (float) $this->tenderedInput;
        $total = $this->getTotalProperty();

        return round($tendered - $total, $this->currencyDecimals);
    }

    public function getTotalQuantityProperty(): int
    {
        return (int) array_sum(array_column($this->cart, 'quantity'));
    }

    /**
     * @return array<int, array{amount: float, label: string}>
     */
    public function getQuickDenominationsProperty(): array
    {
        if ($this->currencyDecimals === 0) {
            return [
                ['amount' => 100.0, 'label' => '+100'],
                ['amount' => 50.0, 'label' => '+50'],
                ['amount' => 20.0, 'label' => '+20'],
                ['amount' => 10.0, 'label' => '+10'],
                ['amount' => 5.0, 'label' => '+5'],
                ['amount' => 1.0, 'label' => '+1'],
            ];
        }

        if ($this->currencyDecimals === 1) {
            return [
                ['amount' => 20.0, 'label' => '+20'],
                ['amount' => 10.0, 'label' => '+10'],
                ['amount' => 5.0, 'label' => '+5'],
                ['amount' => 1.0, 'label' => '+1'],
                ['amount' => 0.5, 'label' => '+0.5'],
                ['amount' => 0.1, 'label' => '+0.1'],
            ];
        }

        if ($this->currencyDecimals === 2) {
            return [
                ['amount' => 20.0, 'label' => '+20'],
                ['amount' => 10.0, 'label' => '+10'],
                ['amount' => 5.0, 'label' => '+5'],
                ['amount' => 1.0, 'label' => '+1'],
                ['amount' => 0.50, 'label' => '+0.50'],
                ['amount' => 0.25, 'label' => '+0.25'],
            ];
        }

        return [
            ['amount' => 20.0, 'label' => '+20'],
            ['amount' => 10.0, 'label' => '+10'],
            ['amount' => 5.0, 'label' => '+5'],
            ['amount' => 1.0, 'label' => '+1'],
            ['amount' => 0.500, 'label' => '+0.500'],
            ['amount' => 0.250, 'label' => '+0.250'],
        ];
    }

    private function autoUpdateExactIfMatched(): void
    {
        if (in_array($this->paymentMethod, ['CARD', 'KNET'], true)) {
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
            'changeDue' => $this->getChangeDueProperty(),
            'currency' => $this->currency,
            'currencyDecimals' => $this->currencyDecimals,
            'quickDenominations' => $this->quickDenominations,
        ])->layout('components.layouts.app');
    }
}
