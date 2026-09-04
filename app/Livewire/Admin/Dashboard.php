<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
    public string $companyName = '';

    public string $siteTitle = '';

    public $siteLogo = null;

    public ?string $currentLogo = null;

    public $siteFavicon = null;

    public ?string $currentFavicon = null;

    // --- Database Engine State ---
    public string $currentDbDriver = 'sqlite';

    public string $targetDbDriver = 'sqlite';

    public string $mysqlHost = '127.0.0.1';

    public string $mysqlPort = '3306';

    public string $mysqlDatabase = 'cash_register';

    public string $mysqlUsername = 'root';

    public string $mysqlPassword = '';

    public ?string $dbTestMessage = null;

    public ?string $dbTestStatus = null;

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

        $this->companyName = Setting::get('company_name', 'Store POS');
        $this->siteTitle = Setting::get('site_title', 'CASH REGISTER');
        $this->currentLogo = Setting::get('site_logo');
        $this->currentFavicon = Setting::get('site_favicon');

        $this->currentDbDriver = config('database.default', env('DB_CONNECTION', 'sqlite'));
        $this->targetDbDriver = $this->currentDbDriver;
        $this->mysqlHost = env('DB_HOST', '127.0.0.1');
        $this->mysqlPort = (string) env('DB_PORT', '3306');
        $this->mysqlDatabase = env('DB_DATABASE', 'cash_register');
        $this->mysqlUsername = env('DB_USERNAME', 'root');
        $this->mysqlPassword = (string) env('DB_PASSWORD', '');
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
            $this->errorMessage = $this->formatSafeErrorMessage($e, 'Failed to add product');
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
            $this->errorMessage = $this->formatSafeErrorMessage($e, 'Failed to remove product');
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
            $this->errorMessage = $this->formatSafeErrorMessage($e, 'Failed to create user');
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
            $this->errorMessage = $this->formatSafeErrorMessage($e, 'Failed to delete user');
            $this->successMessage = null;
        }
    }

    // --- Settings Actions ---

    public function saveSettings(): void
    {
        $this->validate([
            'companyName' => 'required|string|max:150',
            'siteTitle' => 'required|string|max:100',
            'siteLogo' => 'nullable|mimes:png,jpg,jpeg,webp|max:2048',
            'siteFavicon' => 'nullable|mimes:png,ico,webp|max:1024',
        ], [], [
            'companyName' => 'company name',
            'siteTitle' => 'website title',
            'siteLogo' => 'logo image',
            'siteFavicon' => 'favicon image',
        ]);

        try {
            Setting::set('company_name', trim($this->companyName));
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
            $this->errorMessage = $this->formatSafeErrorMessage($e, 'Failed to save settings');
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

    // --- Database Engine Actions ---

    public function testMysqlConnection(): void
    {
        $this->validate([
            'mysqlHost' => 'required|string',
            'mysqlPort' => 'required|numeric',
            'mysqlDatabase' => 'required|string',
            'mysqlUsername' => 'required|string',
        ]);

        try {
            $dsn = "mysql:host={$this->mysqlHost};port={$this->mysqlPort};charset=utf8mb4";
            $pdo = new \PDO($dsn, $this->mysqlUsername, $this->mysqlPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 3,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->mysqlDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $this->dbTestStatus = 'success';
            $this->dbTestMessage = "Successfully connected to MySQL on {$this->mysqlHost}:{$this->mysqlPort} and verified database '{$this->mysqlDatabase}'!";
        } catch (\Throwable $e) {
            $this->dbTestStatus = 'error';
            $this->dbTestMessage = $this->formatSafeErrorMessage($e, 'MySQL connection failed');
        }
    }

    public function switchDatabase(string $driver): void
    {
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            return;
        }

        if ($driver === 'mysql') {
            $this->validate([
                'mysqlHost' => 'required|string',
                'mysqlPort' => 'required|numeric',
                'mysqlDatabase' => 'required|string',
                'mysqlUsername' => 'required|string',
            ]);

            try {
                $dsn = "mysql:host={$this->mysqlHost};port={$this->mysqlPort};charset=utf8mb4";
                $pdo = new \PDO($dsn, $this->mysqlUsername, $this->mysqlPassword, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 3,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$this->mysqlDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Throwable $e) {
                $this->errorMessage = $this->formatSafeErrorMessage($e, 'Cannot switch to MySQL');
                $this->successMessage = null;

                return;
            }

            $this->updateEnvFile([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $this->mysqlHost,
                'DB_PORT' => $this->mysqlPort,
                'DB_DATABASE' => $this->mysqlDatabase,
                'DB_USERNAME' => $this->mysqlUsername,
                'DB_PASSWORD' => $this->mysqlPassword,
            ]);

            $this->currentDbDriver = 'mysql';
            $this->targetDbDriver = 'mysql';
            $this->successMessage = "Switched active database to MySQL ('{$this->mysqlDatabase}') on {$this->mysqlHost}:{$this->mysqlPort}!";
            $this->errorMessage = null;
        } else {
            $sqlitePath = database_path('database.sqlite');
            if (! file_exists($sqlitePath)) {
                touch($sqlitePath);
            }

            $this->updateEnvFile([
                'DB_CONNECTION' => 'sqlite',
            ]);

            $this->currentDbDriver = 'sqlite';
            $this->targetDbDriver = 'sqlite';
            $this->successMessage = 'Switched active database to SQLite (database/database.sqlite)!';
            $this->errorMessage = null;
        }

        Artisan::call('config:clear');
    }

    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $escapedValue = (preg_match('/\s/', $value) || str_contains($value, '#') || str_contains($value, '"'))
                ? '"'.addcslashes($value, '"').'"'
                : $value;

            $pattern = "/^#?\s*({$key}\s*=.*)$/m";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$escapedValue}", $content);
            } else {
                $content .= "\n{$key}={$escapedValue}";
            }
        }

        file_put_contents($envPath, $content);
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

    protected function formatSafeErrorMessage(\Throwable $e, string $defaultMessage): string
    {
        Log::error($defaultMessage.': '.$e->getMessage());

        return config('app.debug') ? $defaultMessage.': '.$e->getMessage() : $defaultMessage.'. Please try again.';
    }
}
