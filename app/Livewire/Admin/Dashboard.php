<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class Dashboard extends Component
{
    use WithFileUploads;

    #[Url]
    public string $tab = 'analytics';

    #[Url]
    public string $fromDate = '';

    #[Url]
    public string $toDate = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    // --- Product Management State (Name only) ---
    public string $productSearch = '';

    public string $newProductName = '';

    // --- User Management State ---
    public string $newUserName = '';

    public string $newUserUsername = '';

    public string $newUserRole = User::ROLE_CASHIER;

    public string $newUserPassword = '';

    public string $newUserPasswordConfirmation = '';

    // --- Settings State ---
    public string $siteTitle = '';

    public $siteLogo = null;

    public ?string $currentLogo = null;

    public $siteFavicon = null;

    public ?string $currentFavicon = null;

    public function mount(): void
    {
        if (empty($this->fromDate)) {
            $this->fromDate = now()->toDateString();
        }

        if (empty($this->toDate)) {
            $this->toDate = now()->toDateString();
        }

        if (! in_array($this->tab, ['analytics', 'products', 'users', 'settings'], true)) {
            $this->tab = 'analytics';
        }

        $this->siteTitle = Setting::get('site_title', 'CASH REGISTER');
        $this->currentLogo = Setting::get('site_logo');
        $this->currentFavicon = Setting::get('site_favicon');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['analytics', 'products', 'users', 'settings'], true)) {
            $this->tab = $tab;
            $this->clearMessages();
        }
    }

    public function setDatePreset(string $preset): void
    {
        switch ($preset) {
            case 'today':
                $this->fromDate = now()->toDateString();
                $this->toDate = now()->toDateString();
                break;
            case 'yesterday':
                $this->fromDate = now()->subDay()->toDateString();
                $this->toDate = now()->subDay()->toDateString();
                break;
            case 'this_week':
                $this->fromDate = now()->startOfWeek()->toDateString();
                $this->toDate = now()->endOfWeek()->toDateString();
                break;
            case 'this_month':
                $this->fromDate = now()->startOfMonth()->toDateString();
                $this->toDate = now()->endOfMonth()->toDateString();
                break;
        }
    }

    public function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    // --- Product Actions (Name only) ---

    public function addProduct(): void
    {
        $this->validate([
            'newProductName' => 'required|string|max:255',
        ], [], [
            'newProductName' => 'product name',
        ]);

        try {
            Product::create([
                'name' => trim($this->newProductName),
            ]);

            $this->reset(['newProductName']);
            $this->successMessage = 'Product added successfully!';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to add product: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    public function deleteProduct(int $productId): void
    {
        try {
            $product = Product::find($productId);
            if ($product) {
                $name = $product->name;
                $product->delete();
                $this->successMessage = "Product '{$name}' removed successfully.";
                $this->errorMessage = null;
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to remove product: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    // --- User Actions ---

    public function addUser(): void
    {
        $this->validate([
            'newUserName' => 'required|string|max:255',
            'newUserUsername' => 'required|string|max:255|unique:users,username',
            'newUserRole' => 'required|string|in:admin,cashier',
            'newUserPassword' => 'required|string|min:6|same:newUserPasswordConfirmation',
        ], [], [
            'newUserName' => 'name',
            'newUserUsername' => 'username',
            'newUserRole' => 'role',
            'newUserPassword' => 'password',
        ]);

        try {
            $role = in_array($this->newUserRole, [User::ROLE_ADMIN, User::ROLE_CASHIER], true)
                ? $this->newUserRole
                : User::ROLE_CASHIER;

            User::create([
                'name' => trim($this->newUserName),
                'username' => trim($this->newUserUsername),
                'role' => $role,
                'password' => Hash::make($this->newUserPassword),
                'email_verified_at' => now(),
            ]);

            $roleLabel = ucfirst($role);
            $this->reset([
                'newUserName',
                'newUserUsername',
                'newUserPassword',
                'newUserPasswordConfirmation',
            ]);
            $this->newUserRole = User::ROLE_CASHIER;

            $this->successMessage = "{$roleLabel} account created successfully!";
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to create user: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    public function deleteUser(int $userId): void
    {
        if (Auth::id() === $userId) {
            $this->errorMessage = 'You cannot delete your own logged-in admin account.';
            $this->successMessage = null;

            return;
        }

        try {
            $user = User::find($userId);
            if ($user) {
                $userIdentifier = $user->username ?: ($user->email ?: $user->name);
                $user->delete();
                $this->successMessage = "Account {$user->name} ({$userIdentifier}) deleted successfully.";
                $this->errorMessage = null;
            }
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to delete user: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    // --- Settings Actions ---

    public function saveSettings(): void
    {
        $this->validate([
            'siteTitle' => 'required|string|max:100',
            'siteLogo' => 'nullable|image|max:2048',
            'siteFavicon' => 'nullable|image|max:1024',
        ], [], [
            'siteTitle' => 'website title',
            'siteLogo' => 'logo image',
            'siteFavicon' => 'favicon image',
        ]);

        try {
            Setting::set('site_title', trim($this->siteTitle));

            if ($this->siteLogo) {
                $path = $this->siteLogo->store('branding', 'public');
                $url = asset('storage/'.$path);
                Setting::set('site_logo', $url);
                $this->currentLogo = $url;
                $this->siteLogo = null;
            }

            if ($this->siteFavicon) {
                $path = $this->siteFavicon->store('branding', 'public');
                $url = asset('storage/'.$path);
                Setting::set('site_favicon', $url);
                $this->currentFavicon = $url;
                $this->siteFavicon = null;
            }

            $this->successMessage = 'Website settings updated successfully!';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to save settings: '.$e->getMessage();
            $this->successMessage = null;
        }
    }

    public function removeLogo(): void
    {
        Setting::set('site_logo', null);
        $this->currentLogo = null;
        $this->siteLogo = null;
        $this->successMessage = 'Logo removed.';
    }

    public function removeFavicon(): void
    {
        Setting::set('site_favicon', null);
        $this->currentFavicon = null;
        $this->siteFavicon = null;
        $this->successMessage = 'Favicon removed.';
    }

    // --- Computed Analytics Properties ---

    /**
     * Daily sales metrics (Today)
     *
     * @return array{revenue: float, count: int, avg: float}
     */
    public function getDailySalesProperty(): array
    {
        $orders = Order::whereDate('created_at', today())->get();
        $revenue = (float) $orders->sum('total');
        $count = $orders->count();
        $avg = $count > 0 ? round($revenue / $count, 3) : 0.000;

        return [
            'revenue' => $revenue,
            'count' => $count,
            'avg' => $avg,
        ];
    }

    /**
     * Weekly sales metrics (Current Week)
     *
     * @return array{revenue: float, count: int}
     */
    public function getWeeklySalesProperty(): array
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $orders = Order::whereBetween('created_at', [$startOfWeek, $endOfWeek])->get();

        return [
            'revenue' => (float) $orders->sum('total'),
            'count' => $orders->count(),
        ];
    }

    /**
     * Monthly sales metrics (Current Month)
     *
     * @return array{revenue: float, count: int}
     */
    public function getMonthlySalesProperty(): array
    {
        $orders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();

        return [
            'revenue' => (float) $orders->sum('total'),
            'count' => $orders->count(),
        ];
    }

    /**
     * Calendar date range sales metrics and orders
     *
     * @return array{fromDate: string, toDate: string, isSingleDay: bool, revenue: float, count: int, cashRevenue: float, knetRevenue: float, orders: Collection}
     */
    public function getCalendarSalesProperty(): array
    {
        $from = ! empty($this->fromDate) ? $this->fromDate : now()->toDateString();
        $to = ! empty($this->toDate) ? $this->toDate : $from;

        if ($from > $to) {
            $temp = $from;
            $from = $to;
            $to = $temp;
        }

        $orders = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with('items')
            ->orderByDesc('created_at')
            ->get();

        $revenue = (float) $orders->sum('total');
        $cashRevenue = (float) $orders->where('payment_method', 'CASH')->sum('total');
        $knetRevenue = (float) $orders->where('payment_method', 'KNET')->sum('total');

        return [
            'fromDate' => $from,
            'toDate' => $to,
            'isSingleDay' => $from === $to,
            'revenue' => $revenue,
            'count' => $orders->count(),
            'cashRevenue' => $cashRevenue,
            'knetRevenue' => $knetRevenue,
            'orders' => $orders,
        ];
    }

    public function render()
    {
        // Products query (name only)
        $productsQuery = Product::query();
        if (! empty($this->productSearch)) {
            $productsQuery->where('name', 'like', '%'.$this->productSearch.'%');
        }
        $products = $productsQuery->orderBy('name')->get();

        // Users query
        $users = User::orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return view('livewire.admin.dashboard', [
            'products' => $products,
            'users' => $users,
            'dailySales' => $this->dailySales,
            'weeklySales' => $this->weeklySales,
            'monthlySales' => $this->monthlySales,
            'calendarSales' => $this->calendarSales,
        ])->layout('components.layouts.app');
    }
}
