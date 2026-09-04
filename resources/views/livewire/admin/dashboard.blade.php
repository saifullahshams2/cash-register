<div class="flex flex-col min-h-dvh w-full bg-slate-100 font-sans text-slate-900 overflow-y-auto">
    <!-- Header with Responsive Menu -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 sm:py-0 sm:h-16 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0 shadow-2xs sticky top-0 z-30">
        <!-- Top Bar on Mobile / Left on Desktop: Branding & Right Action on Mobile -->
        <div class="flex items-center justify-between sm:justify-start gap-3 sm:w-1/4">
            <div class="flex items-center gap-2.5">
                @if($currentLogo)
                    <img src="{{ $currentLogo }}" alt="Logo" class="w-8 h-8 sm:w-9 sm:h-9 object-contain rounded-xl border border-slate-200 p-0.5 bg-white shadow-2xs shrink-0">
                @else
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-xs sm:text-sm tracking-wider shadow-xs shrink-0">
                        KW
                    </div>
                @endif
                <div class="min-w-0">
                    <h1 class="font-extrabold text-xs sm:text-sm tracking-tight text-slate-900 uppercase truncate max-w-[140px] sm:max-w-[180px]">
                        {{ $siteTitle ?: 'ADMIN DASHBOARD' }}
                    </h1>
                    <p class="text-[9px] sm:text-[10px] text-slate-500 font-semibold tracking-wider uppercase">ADMIN DASHBOARD</p>
                </div>
            </div>

            <!-- Mobile Only Profile & Logout -->
            <div class="flex sm:hidden items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full {{ Auth::user()->isAdmin() ? 'bg-purple-600' : 'bg-emerald-500' }} shrink-0" title="{{ Auth::user()->isAdmin() ? 'Admin' : 'Cashier' }}"></span>
                    <span class="font-bold text-slate-800 text-xs truncate max-w-[100px]">{{ Auth::user()->name }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button 
                        type="submit" 
                        class="px-2.5 py-1 text-xs font-bold bg-white hover:bg-rose-50 text-rose-700 border border-rose-200 rounded-lg transition cursor-pointer shadow-2xs"
                    >
                        Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Middle: Navigation Menu (Scrollable on mobile, Centered on desktop) -->
        <div class="flex items-center justify-start sm:justify-center overflow-x-auto no-scrollbar py-0.5 sm:py-0 sm:flex-1">
            <nav class="flex items-center gap-1 sm:gap-1.5 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-bold shrink-0">
                <button 
                    wire:click="setTab('analytics')" 
                    class="px-2.5 sm:px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1 sm:gap-1.5 shrink-0 {{ $tab === 'analytics' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <span>📊</span>
                    <span>Analytics</span>
                </button>
                <button 
                    wire:click="setTab('products')" 
                    class="px-2.5 sm:px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1 sm:gap-1.5 shrink-0 {{ $tab === 'products' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <span>📦</span>
                    <span>Products</span>
                </button>
                <button 
                    wire:click="setTab('users')" 
                    class="px-2.5 sm:px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1 sm:gap-1.5 shrink-0 {{ $tab === 'users' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <span>👥</span>
                    <span>Users</span>
                </button>
                <button 
                    wire:click="setTab('settings')" 
                    class="px-2.5 sm:px-3.5 py-1.5 rounded-lg transition cursor-pointer flex items-center gap-1 sm:gap-1.5 shrink-0 {{ $tab === 'settings' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                >
                    <span>⚙️</span>
                    <span>Settings</span>
                </button>
            </nav>
        </div>

        <!-- Desktop Right: Admin Profile & Logout -->
        <div class="hidden sm:flex items-center justify-end gap-3 sm:w-1/4">
            <div class="flex items-center gap-2 text-xs bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl">
                <span class="w-2.5 h-2.5 rounded-full {{ Auth::user()->isAdmin() ? 'bg-purple-600' : 'bg-emerald-500' }} shrink-0" title="{{ Auth::user()->isAdmin() ? 'Admin' : 'Cashier' }}"></span>
                <span class="font-bold text-slate-800">{{ Auth::user()->name }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button 
                    type="submit" 
                    class="px-3 py-1.5 text-xs font-bold bg-white hover:bg-rose-50 text-rose-700 border border-rose-200 rounded-xl transition cursor-pointer shadow-2xs"
                >
                    Logout
                </button>
            </form>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 p-3.5 sm:p-6 max-w-7xl mx-auto w-full space-y-6">
        
        <!-- Alerts Banner -->
        @if ($successMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                x-init="setTimeout(() => show = false, 5000)"
                class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 font-semibold text-xs flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2">
                    <span class="text-base">✓</span>
                    <span>{{ $successMessage }}</span>
                </div>
                <button @click="show = false" class="text-emerald-700 hover:text-emerald-900 font-bold text-sm">✕</button>
            </div>
        @endif

        @if ($errorMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 font-semibold text-xs flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2">
                    <span class="text-base">⚠️</span>
                    <span>{{ $errorMessage }}</span>
                </div>
                <button @click="show = false" class="text-rose-700 hover:text-rose-900 font-bold text-sm">✕</button>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 1: ANALYTICS (30% Left | 70% Right | 100% Order Receipts)    -->
        <!-- ================================================================= -->
        @if ($tab === 'analytics')
            <div class="space-y-6">
                <!-- TOP ROW: 30% Left (Sales Summary with centered content) | 70% Right (Calendar Date Sales) -->
                <div class="grid grid-cols-1 lg:grid-cols-10 gap-6 items-stretch">
                    
                    <!-- 30% LEFT: Sales Summary with content centered in the middle -->
                    <div class="lg:col-span-3 bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
                        <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100 text-center">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center justify-center gap-1.5">
                                <span>⚡</span> Sales Summary
                            </h2>
                        </div>

                        <div class="flex-1 flex flex-col justify-around p-5 space-y-4 text-center">
                            <!-- Today's Sales -->
                            <div class="space-y-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">Today's Sales</span>
                                <div class="text-2xl font-extrabold font-mono text-slate-900">
                                    {{ number_format($dailySales['revenue'], 3, '.', '') }} <span class="text-xs font-sans text-slate-500">KWD</span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-medium block">
                                    {{ $dailySales['count'] }} orders (Avg: {{ number_format($dailySales['avg'], 3, '.', '') }})
                                </span>
                            </div>

                            <div class="border-t border-slate-100 w-3/4 mx-auto"></div>

                            <!-- This Week's Sales -->
                            <div class="space-y-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">This Week</span>
                                <div class="text-2xl font-extrabold font-mono text-slate-900">
                                    {{ number_format($weeklySales['revenue'], 3, '.', '') }} <span class="text-xs font-sans text-slate-500">KWD</span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-medium block">
                                    {{ $weeklySales['count'] }} orders this week
                                </span>
                            </div>

                            <div class="border-t border-slate-100 w-3/4 mx-auto"></div>

                            <!-- This Month's Sales -->
                            <div class="space-y-1">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">This Month</span>
                                <div class="text-2xl font-extrabold font-mono text-slate-900">
                                    {{ number_format($monthlySales['revenue'], 3, '.', '') }} <span class="text-xs font-sans text-slate-500">KWD</span>
                                </div>
                                <span class="text-[10px] text-slate-500 font-medium block">
                                    {{ $monthlySales['count'] }} orders in {{ now()->format('M Y') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 70% RIGHT: Calendar Date Sales (From / To Date Picker & Period Summary Badges) -->
                    <div class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-5 shadow-xs flex flex-col justify-between space-y-4">
                        <div class="border-b border-slate-100 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                                    <span>🗓️</span> Calendar Date Sales
                                </h3>
                                <p class="text-[11px] text-slate-500">Filter by single day or custom date range</p>
                            </div>

                            <!-- Quick Preset Buttons -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button 
                                    wire:click="setDatePreset('today')" 
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                                >
                                    Today
                                </button>
                                <button 
                                    wire:click="setDatePreset('yesterday')" 
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                                >
                                    Yesterday
                                </button>
                                <button 
                                    wire:click="setDatePreset('this_week')" 
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                                >
                                    This Week
                                </button>
                                <button 
                                    wire:click="setDatePreset('this_month')" 
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-lg border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer"
                                >
                                    This Month
                                </button>
                            </div>
                        </div>

                        <!-- From Date and To Date Inputs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="fromDate" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    From Date
                                </label>
                                <input 
                                    wire:model.live="fromDate" 
                                    type="date" 
                                    id="fromDate"
                                    class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-mono font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 cursor-pointer shadow-2xs"
                                >
                            </div>
                            <div>
                                <label for="toDate" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                    To Date
                                </label>
                                <input 
                                    wire:model.live="toDate" 
                                    type="date" 
                                    id="toDate"
                                    class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-xs font-mono font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900 cursor-pointer shadow-2xs"
                                >
                            </div>
                        </div>

                        <!-- Period Summary Cards in a 4-column row -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                            <div class="p-3.5 rounded-xl bg-slate-900 text-white flex flex-col justify-between">
                                <span class="text-[10px] uppercase font-bold text-slate-300 block">Total Revenue</span>
                                <div class="text-xl font-extrabold font-mono text-white mt-1">
                                    {{ number_format($calendarSales['revenue'], 3, '.', '') }} <span class="text-xs font-sans text-slate-400">KWD</span>
                                </div>
                                <span class="text-[10px] text-slate-400 mt-1 block">
                                    {{ Carbon\Carbon::parse($calendarSales['fromDate'])->format('d M') }} — {{ Carbon\Carbon::parse($calendarSales['toDate'])->format('d M') }}
                                </span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                                <span class="text-[10px] uppercase font-bold text-slate-600 block">Total Orders</span>
                                <div class="text-xl font-extrabold font-mono text-slate-900 mt-1">
                                    {{ $calendarSales['count'] }}
                                </div>
                                <span class="text-[10px] text-slate-500 mt-1 block">Orders recorded</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 flex flex-col justify-between">
                                <span class="text-[10px] uppercase font-bold text-emerald-800 block">CASH</span>
                                <div class="text-xl font-extrabold font-mono text-emerald-900 mt-1">
                                    {{ number_format($calendarSales['cashRevenue'], 3, '.', '') }}
                                </div>
                                <span class="text-[10px] text-emerald-700 mt-1 block">Cash total KWD</span>
                            </div>

                            <div class="p-3.5 rounded-xl bg-sky-50 border border-sky-200 flex flex-col justify-between">
                                <span class="text-[10px] uppercase font-bold text-sky-800 block">K-NET</span>
                                <div class="text-xl font-extrabold font-mono text-sky-900 mt-1">
                                    {{ number_format($calendarSales['knetRevenue'], 3, '.', '') }}
                                </div>
                                <span class="text-[10px] text-sky-700 mt-1 block">Card total KWD</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM ROW: Order Receipts 100% full width (same like before) -->
                <div class="w-full bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 tracking-tight">Period Order Receipts</h3>
                            <p class="text-[11px] text-slate-500 font-mono">
                                @if($calendarSales['isSingleDay'])
                                    {{ Carbon\Carbon::parse($calendarSales['fromDate'])->format('d M Y') }}
                                @else
                                    {{ Carbon\Carbon::parse($calendarSales['fromDate'])->format('d M Y') }} — {{ Carbon\Carbon::parse($calendarSales['toDate'])->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-mono font-bold bg-slate-200 text-slate-800">
                            {{ $calendarSales['count'] }} Orders
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[600px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th class="py-3 px-4">Order #</th>
                                    <th class="py-3 px-4">Date / Time</th>
                                    <th class="py-3 px-4">Payment</th>
                                    <th class="py-3 px-4">Items Summary</th>
                                    <th class="py-3 px-4 text-right">Total (KWD)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse($calendarSales['orders'] as $order)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                            {{ $order->order_number }}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                            <div>{{ $order->created_at->format('d M Y') }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $order->created_at->format('h:i:s A') }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($order->payment_method === 'CASH')
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                    CASH
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-300">
                                                    K-NET
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">
                                            @if($order->items && count($order->items) > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($order->items as $item)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-[10px] font-medium text-slate-700">
                                                            {{ $item->quantity }}× {{ $item->product_name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-slate-400 italic text-[11px]">Direct checkout</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-right font-mono font-extrabold text-slate-900">
                                            {{ number_format($order->total, 3, '.', '') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-16 text-center text-slate-400 text-xs">
                                            <div class="text-3xl mb-2">🧾</div>
                                            No orders recorded in this date range.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 2: PRODUCTS (Simplified: Name & Remove only)                 -->
        <!-- ================================================================= -->
        @if ($tab === 'products')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 1. Add Product Form (Name only) -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <span>➕</span> Add Product
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Add an item name for the cashier terminal</p>
                    </div>

                    <form wire:submit="addProduct" class="space-y-4">
                        <!-- Product Name -->
                        <div>
                            <label for="newProductName" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Product Name
                            </label>
                            <input 
                                wire:model="newProductName" 
                                type="text" 
                                id="newProductName" 
                                placeholder="e.g. Karak Chai, Latte, Croissant"
                                required
                                autofocus
                                class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                            >
                            @error('newProductName')
                                <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span wire:loading.remove>Add Product</span>
                            <span wire:loading class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Adding...
                            </span>
                        </button>
                    </form>
                </div>

                <!-- 2. Products Table (Simplified: Name, Added Date, Remove) -->
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 tracking-tight">Active Products</h2>
                            <p class="text-[11px] text-slate-500">Items available to cashiers in the POS register</p>
                        </div>

                        <div class="w-full sm:w-64">
                            <input 
                                wire:model.live.debounce.250ms="productSearch" 
                                type="text" 
                                placeholder="Search product name..."
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-900 transition"
                            >
                        </div>
                    </div>

                    <div class="flex-1 overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[450px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th class="py-3 px-4 w-12 text-slate-400">#</th>
                                    <th class="py-3 px-4">Product Name</th>
                                    <th class="py-3 px-4">Added</th>
                                    <th class="py-3 px-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse($products as $index => $product)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-4 font-mono text-[11px] text-slate-400">
                                            {{ $index + 1 }}
                                        </td>
                                        <td class="py-3 px-4 font-bold text-slate-900 text-sm">
                                            {{ $product->name }}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                            {{ $product->created_at?->format('d M Y') ?? 'N/A' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <button 
                                                wire:click="deleteProduct({{ $product->id }})"
                                                wire:confirm="Are you sure you want to remove '{{ $product->name }}'?"
                                                class="px-2.5 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer"
                                                title="Remove Product"
                                            >
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-12 text-center text-slate-400 text-xs">
                                            No products found. Add your first product above.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 3: USERS (Cashiers & Admins)                                 -->
        <!-- ================================================================= -->
        @if ($tab === 'users')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- 1. Create User Form -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-4">
                    <div class="border-b border-slate-100 pb-3">
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            <span>➕</span> Create New User
                        </h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Add a new cashier or administrator account</p>
                    </div>

                    <form wire:submit="addUser" class="space-y-3.5">
                        <!-- Role Selection -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Account Role
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-semibold transition {{ $newUserRole === 'cashier' ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-500' : 'border-slate-200 bg-slate-50/50 text-slate-700 hover:bg-slate-50' }}">
                                    <input 
                                        wire:model.live="newUserRole" 
                                        type="radio" 
                                        value="cashier" 
                                        name="newUserRole"
                                        class="sr-only"
                                    >
                                    <span class="w-2.5 h-2.5 rounded-full {{ $newUserRole === 'cashier' ? 'bg-emerald-600' : 'bg-slate-300' }}"></span>
                                    <span>Cashier</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-semibold transition {{ $newUserRole === 'admin' ? 'border-purple-500 bg-purple-50 text-purple-900 ring-1 ring-purple-500' : 'border-slate-200 bg-slate-50/50 text-slate-700 hover:bg-slate-50' }}">
                                    <input 
                                        wire:model.live="newUserRole" 
                                        type="radio" 
                                        value="admin" 
                                        name="newUserRole"
                                        class="sr-only"
                                    >
                                    <span class="w-2.5 h-2.5 rounded-full {{ $newUserRole === 'admin' ? 'bg-purple-600' : 'bg-slate-300' }}"></span>
                                    <span>Admin</span>
                                </label>
                            </div>
                        </div>

                        <!-- Full Name -->
                        <div>
                            <label for="newUserName" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Full Name
                            </label>
                            <input 
                                wire:model="newUserName" 
                                type="text" 
                                id="newUserName" 
                                placeholder="e.g. Sarah Ahmad"
                                required
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                            >
                            @error('newUserName')
                                <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Username -->
                        <div>
                            <label for="newUserUsername" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Username
                            </label>
                            <input 
                                wire:model="newUserUsername" 
                                type="text" 
                                id="newUserUsername" 
                                placeholder="e.g. sarah, cashier_1, or any character"
                                required
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                            >
                            <p class="text-[10px] text-slate-400 mt-1">Username can contain any characters.</p>
                            @error('newUserUsername')
                                <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="newUserPassword" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Password (Min 6 chars)
                            </label>
                            <input 
                                wire:model="newUserPassword" 
                                type="password" 
                                id="newUserPassword" 
                                placeholder="••••••••"
                                required
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                            >
                            @error('newUserPassword')
                                <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="newUserPasswordConfirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                                Confirm Password
                            </label>
                            <input 
                                wire:model="newUserPasswordConfirmation" 
                                type="password" 
                                id="newUserPasswordConfirmation" 
                                placeholder="••••••••"
                                required
                                class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                            >
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition active:scale-98 flex items-center justify-center gap-2 cursor-pointer mt-2"
                        >
                            <span wire:loading.remove>Create {{ ucfirst($newUserRole) }} Account</span>
                            <span wire:loading class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Creating...
                            </span>
                        </button>
                    </form>
                </div>

                <!-- 2. Users Table -->
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden flex flex-col">
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 tracking-tight">Active User Accounts</h2>
                            <p class="text-[11px] text-slate-500">All registered terminal staff and administrators</p>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-200 text-slate-800">
                            {{ count($users) }} Total
                        </span>
                    </div>

                    <div class="flex-1 overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[450px]">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                                    <th class="py-3 px-4">User</th>
                                    <th class="py-3 px-4">Role</th>
                                    <th class="py-3 px-4">Created</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs">
                                @forelse($users as $user)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-3 px-4">
                                            <div class="font-bold text-slate-900">{{ $user->name }}</div>
                                            <div class="font-mono text-[11px] text-slate-500">{{ $user->username ?: $user->email }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if($user->isAdmin())
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300">
                                                    ADMIN
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                    CASHIER
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 font-mono text-[11px] text-slate-500">
                                            {{ $user->created_at?->format('d M Y') ?? 'N/A' }}
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            @if($user->id !== Auth::id())
                                                <button 
                                                    wire:click="deleteUser({{ $user->id }})"
                                                    wire:confirm="Are you sure you want to delete account {{ $user->name }}?"
                                                    class="px-2.5 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition cursor-pointer"
                                                    title="Delete Account"
                                                >
                                                    Delete
                                                </button>
                                            @else
                                                <span class="text-[10px] font-semibold text-slate-400 italic">Current User</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-slate-400 text-xs">
                                            No users found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 4: SETTINGS (Website Title, Logo & Favicon)                  -->
        <!-- ================================================================= -->
        @if ($tab === 'settings')
            <div class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl shadow-xs p-6 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>⚙️</span> Website & Terminal Settings
                    </h2>
                    <p class="text-xs text-slate-500 mt-1">Configure your store brand identity, logo, and browser favicon</p>
                </div>

                <form wire:submit="saveSettings" class="space-y-6">
                    <!-- Website Title -->
                    <div>
                        <label for="siteTitle" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Website / Terminal Title
                        </label>
                        <input 
                            wire:model="siteTitle" 
                            type="text" 
                            id="siteTitle" 
                            placeholder="e.g. My Cafe POS, Cash Register Terminal"
                            required
                            class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">Displayed in browser title tab and terminal navigation bars.</p>
                        @error('siteTitle')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Logo Upload & Preview -->
                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Website Logo
                        </label>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                            <!-- Preview Box -->
                            <div class="w-16 h-16 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden">
                                @if ($siteLogo)
                                    <img src="{{ $siteLogo->temporaryUrl() }}" class="w-full h-full object-contain p-1">
                                @elseif ($currentLogo)
                                    <img src="{{ $currentLogo }}" class="w-full h-full object-contain p-1">
                                @else
                                    <span class="text-2xl text-slate-300">🖼️</span>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2 min-w-0">
                                <input 
                                    wire:model="siteLogo" 
                                    type="file" 
                                    accept="image/*"
                                    class="text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-900 file:text-white hover:file:bg-slate-800 cursor-pointer max-w-full"
                                >
                                <p class="text-[10px] text-slate-400">PNG, JPG, SVG, WebP up to 2MB.</p>
                                @if ($currentLogo)
                                    <button 
                                        type="button" 
                                        wire:click="removeLogo" 
                                        class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 cursor-pointer"
                                    >
                                        Remove current logo
                                    </button>
                                @endif
                                @error('siteLogo')
                                    <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Favicon Upload & Preview -->
                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Browser Favicon
                        </label>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                            <!-- Preview Box -->
                            <div class="w-12 h-12 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden">
                                @if ($siteFavicon)
                                    <img src="{{ $siteFavicon->temporaryUrl() }}" class="w-full h-full object-contain p-1">
                                @elseif ($currentFavicon)
                                    <img src="{{ $currentFavicon }}" class="w-full h-full object-contain p-1">
                                @else
                                    <span class="text-lg text-slate-300">⭐</span>
                                @endif
                            </div>

                            <div class="flex-1 space-y-2 min-w-0">
                                <input 
                                    wire:model="siteFavicon" 
                                    type="file" 
                                    accept="image/x-icon,image/png,image/svg+xml"
                                    class="text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-900 file:text-white hover:file:bg-slate-800 cursor-pointer max-w-full"
                                >
                                <p class="text-[10px] text-slate-400">ICO, PNG, or SVG icon up to 1MB.</p>
                                @if ($currentFavicon)
                                    <button 
                                        type="button" 
                                        wire:click="removeFavicon" 
                                        class="text-[11px] font-semibold text-rose-600 hover:text-rose-800 cursor-pointer"
                                    >
                                        Remove current favicon
                                    </button>
                                @endif
                                @error('siteFavicon')
                                    <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button 
                            type="submit" 
                            class="w-full sm:w-auto py-2.5 px-6 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-xs transition active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <span wire:loading.remove wire:target="saveSettings">Save Settings</span>
                            <span wire:loading wire:target="saveSettings" class="flex items-center gap-1.5">
                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Saving Settings...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        @endif

    </main>
</div>
