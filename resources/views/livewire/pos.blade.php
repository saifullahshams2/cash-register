<div 
    x-data="{
        searchFocus() { $refs.searchInput?.focus(); },
        init() {
            window.addEventListener('keydown', (e) => {
                // Focus search on '/'
                if (e.key === '/' && document.activeElement !== $refs.searchInput) {
                    e.preventDefault();
                    $refs.searchInput?.focus();
                }
                // Numpad physical keys if not typing in search
                if (document.activeElement !== $refs.searchInput) {
                    if (['0','1','2','3','4','5','6','7','8','9','.'].includes(e.key)) {
                        $wire.numpadInput(e.key);
                    } else if (e.key === 'Backspace') {
                        $wire.numpadBackspace();
                    } else if (e.key === 'Escape') {
                        $wire.numpadClear();
                    } else if (e.key === 'Enter') {
                        $wire.checkout();
                    }
                }
            });
        }
    }"
    class="flex flex-col h-screen w-screen overflow-hidden bg-slate-950 text-slate-100 font-sans"
>
    <!-- TOP STATUS & APP BAR -->
    <header class="h-14 bg-slate-900/90 border-b border-slate-800/80 px-4 flex items-center justify-between shrink-0 z-20 backdrop-blur-md">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20 font-bold text-lg">
                KW
            </div>
            <div>
                <h1 class="font-bold text-sm tracking-wide text-slate-100 flex items-center gap-2">
                    CASH REGISTER POS
                    <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        KWD (3 Decimals)
                    </span>
                </h1>
                <p class="text-xs text-slate-400">Terminal #01 • Cashier: Admin</p>
            </div>
        </div>

        <!-- Notification Toast Banner -->
        @if ($notificationMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                x-init="setTimeout(() => show = false, 3500)"
                x-transition:enter="transition ease-out duration-300 transform"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                class="px-4 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 border shadow-lg {{ $notificationType === 'error' ? 'bg-rose-500/20 border-rose-500/40 text-rose-300' : ($notificationType === 'info' ? 'bg-sky-500/20 border-sky-500/40 text-sky-300' : 'bg-emerald-500/20 border-emerald-500/40 text-emerald-300') }}"
            >
                <span>{{ $notificationMessage }}</span>
            </div>
        @endif

        <!-- Header Actions -->
        <div class="flex items-center gap-2">
            @if(count($heldCarts) > 0)
                <div class="flex items-center gap-1.5 bg-amber-500/10 border border-amber-500/30 rounded-lg px-2.5 py-1">
                    <span class="text-xs font-semibold text-amber-400">Held Orders ({{ count($heldCarts) }}):</span>
                    @foreach($heldCarts as $index => $held)
                        <button 
                            wire:click="restoreHeldCart({{ $index }})"
                            class="px-2 py-0.5 text-[11px] bg-amber-500 text-slate-950 font-bold rounded hover:bg-amber-400 transition"
                            title="Restore Order {{ $held['time'] }} ({{ number_format($held['total'], 3) }} KWD)"
                        >
                            #{{ $index + 1 }} ({{ number_format($held['total'], 3) }})
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="text-right pl-3 border-l border-slate-800">
                <span class="text-xs font-mono text-slate-400">{{ now()->format('D, d M Y') }}</span>
                <div class="text-xs font-mono font-bold text-slate-200" x-data="{ time: '' }" x-init="setInterval(() => { time = new Date().toLocaleTimeString('en-GB') }, 1000)" x-text="time"></div>
            </div>
        </div>
    </header>

    <!-- 3-COLUMN MAIN LAYOUT -->
    <div class="flex-1 flex overflow-hidden w-full h-[calc(100vh-3.5rem)]">
        
        <!-- ========================================== -->
        <!-- 1. LEFT COLUMN: 25% WIDTH FOR PRODUCTS     -->
        <!-- ========================================== -->
        <aside class="w-[25%] h-full flex flex-col border-r border-slate-800 bg-slate-900/40 shrink-0">
            <!-- Search and Filter Header -->
            <div class="p-3 border-b border-slate-800/80 bg-slate-900/70 space-y-2.5 shrink-0">
                <div class="relative">
                    <input 
                        x-ref="searchInput"
                        wire:model.live.debounce.150ms="search" 
                        type="text" 
                        placeholder="Search product / SKU (Press /)"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-emerald-500/70 focus:ring-1 focus:ring-emerald-500/50 transition font-sans"
                    >
                    @if($search)
                        <button wire:click="$set('search', '')" class="absolute right-2.5 top-2.5 text-xs text-slate-400 hover:text-slate-200">✕</button>
                    @endif
                </div>

                <!-- Category Tabs -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none text-[11px] font-medium">
                    @foreach($categories as $cat)
                        <button 
                            wire:click="setCategory('{{ $cat }}')"
                            class="px-2.5 py-1 rounded-lg shrink-0 transition {{ $selectedCategory === $cat ? 'bg-emerald-500 text-slate-950 font-bold shadow-md shadow-emerald-500/20' : 'bg-slate-800/80 text-slate-400 hover:text-slate-200 hover:bg-slate-800' }}"
                        >
                            {{ $cat }}
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Product List Scroll Container (Max 100vh constrained) -->
            <div class="flex-1 overflow-y-auto p-2.5 space-y-2">
                @forelse($products as $product)
                    <div 
                        wire:key="product-{{ $product->id }}"
                        wire:click="addToCart({{ $product->id }})"
                        class="group relative flex items-center justify-between p-2.5 rounded-xl bg-slate-900/80 hover:bg-slate-800/90 border border-slate-800/80 hover:border-emerald-500/50 cursor-pointer transition active:scale-[0.98] shadow-sm"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <!-- Icon / Avatar -->
                            <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700/60 flex items-center justify-center text-xl shrink-0 group-hover:scale-105 transition">
                                {{ $product->image ?? '📦' }}
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-xs font-semibold text-slate-100 truncate group-hover:text-emerald-400 transition">
                                    {{ $product->name }}
                                </h4>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[10px] font-mono text-slate-500">{{ $product->code }}</span>
                                    <span class="text-[9px] px-1.5 py-0.2 rounded bg-slate-800 text-slate-400">{{ $product->category }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Price Tag -->
                        <div class="text-right shrink-0 pl-2">
                            <span class="font-mono font-bold text-xs text-emerald-400 block">
                                {{ number_format($product->price, 3) }}
                            </span>
                            <span class="text-[9px] font-medium text-slate-500 uppercase">KWD</span>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-slate-500">
                        <div class="text-3xl mb-2">🔍</div>
                        <p class="text-xs">No products found</p>
                    </div>
                @endforelse
            </div>

            <!-- Left Footer Count -->
            <div class="p-2 border-t border-slate-800 bg-slate-900/60 text-center text-[10px] text-slate-400 shrink-0 font-mono">
                Showing {{ count($products) }} items
            </div>
        </aside>

        <!-- ========================================== -->
        <!-- 2. MIDDLE COLUMN: 30% WIDTH (BLANK SPACE)  -->
        <!-- ========================================== -->
        <main class="w-[30%] h-full flex flex-col border-r border-slate-800 bg-slate-950/60 relative shrink-0">
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <!-- Sleek Minimal Blank Canvas / Reserved Area -->
                <div class="w-full max-w-xs p-6 rounded-2xl border border-dashed border-slate-800 bg-slate-900/20 backdrop-blur-sm flex flex-col items-center justify-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-600 mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reserved Workspace</h3>
                    <p class="text-[11px] text-slate-600 mt-1">30% Middle Section (Blank for future modules / order canvas)</p>
                </div>
            </div>
        </main>

        <!-- ============================================================== -->
        <!-- 3. RIGHT COLUMN: 45% WIDTH FOR CART, DENOMINATIONS & NUMPAD   -->
        <!-- ============================================================== -->
        <section class="w-[45%] h-full flex flex-col bg-slate-900/50 shrink-0">
            
            <!-- A. CART DISPLAY PORTION (TOP HALF) -->
            <div class="flex-[1.1] flex flex-col border-b border-slate-800 overflow-hidden bg-slate-900/30">
                <!-- Cart Title & Quick Controls -->
                <div class="px-4 py-2.5 border-b border-slate-800/80 bg-slate-900/80 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Cart Display</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'Item' : 'Items' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if(count($cart) > 0)
                            <button 
                                wire:click="holdCart" 
                                class="px-2.5 py-1 text-[11px] font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-lg transition"
                            >
                                Hold
                            </button>
                            <button 
                                wire:click="clearCart" 
                                class="px-2.5 py-1 text-[11px] font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 rounded-lg transition"
                            >
                                Clear
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Cart Items Table / Scroll View -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    @forelse($cart as $key => $item)
                        <div 
                            wire:key="cart-item-{{ $key }}"
                            class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-slate-700 transition"
                        >
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-sm shrink-0">
                                    {{ $item['image'] ?? '📦' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h5 class="text-xs font-semibold text-slate-100 truncate">{{ $item['name'] }}</h5>
                                    <span class="text-[10px] font-mono text-slate-400">
                                        {{ number_format($item['price'], 3) }} KWD / unit
                                    </span>
                                </div>
                            </div>

                            <!-- Quantity Controls -->
                            <div class="flex items-center gap-1.5 mx-3 shrink-0">
                                <button 
                                    wire:click="decreaseQuantity({{ $item['id'] }})"
                                    class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center text-xs font-bold transition"
                                >
                                    -
                                </button>
                                <span class="w-7 text-center font-mono font-bold text-xs text-slate-100">
                                    {{ $item['quantity'] }}
                                </span>
                                <button 
                                    wire:click="increaseQuantity({{ $item['id'] }})"
                                    class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center text-xs font-bold transition"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Item Subtotal -->
                            <div class="text-right shrink-0 w-24">
                                <span class="font-mono font-bold text-xs text-emerald-400 block">
                                    {{ number_format($item['subtotal'], 3) }}
                                </span>
                                <span class="text-[9px] text-slate-500">KWD</span>
                            </div>

                            <!-- Remove Button -->
                            <button 
                                wire:click="removeFromCart({{ $item['id'] }})"
                                class="ml-2 text-slate-500 hover:text-rose-400 p-1 transition text-xs"
                                title="Remove item"
                            >
                                ✕
                            </button>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-slate-500 py-8">
                            <div class="text-3xl mb-1">🛒</div>
                            <p class="text-xs">Cart is empty. Select items from left product catalog.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Totals Summary Bar -->
                <div class="p-3 bg-slate-950 border-t border-slate-800/90 grid grid-cols-2 gap-3 shrink-0">
                    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <span>Subtotal</span>
                            <span class="font-mono text-slate-300">{{ number_format($subtotal, 3) }} KWD</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-200 mt-1 pt-1 border-t border-slate-800">
                            <span>Grand Total</span>
                            <span class="font-mono text-emerald-400 text-sm">{{ number_format($total, 3) }} KWD</span>
                        </div>
                    </div>

                    <!-- Live Tender & Change Metric -->
                    <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 flex flex-col justify-between">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-slate-400">Tendered</span>
                            <span class="font-mono font-bold text-slate-200">{{ number_format((float)$tenderedInput, 3) }} KWD</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold mt-1 pt-1 border-t border-slate-800">
                            <span class="text-slate-400">Change Due</span>
                            <span class="font-mono font-bold text-sm {{ $changeDue >= 0 ? 'text-teal-400' : 'text-rose-400' }}">
                                {{ number_format(max(0, $changeDue), 3) }} KWD
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- B. PAYMENT & NUMPAD PORTION (BOTTOM HALF) -->
            <div class="flex-1 flex flex-col p-3 bg-slate-950 overflow-hidden space-y-2">
                
                <!-- 1. Quick Denomination Buttons in Kuwaiti Dinar (KWD) -->
                <!-- 0.250, 0.500, 1, 5, 10, 20 and EXACT as requested -->
                <div class="grid grid-cols-7 gap-1.5 shrink-0">
                    <button 
                        wire:click="setDenomination(0.250)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 0.250 KWD"
                    >
                        0.250
                    </button>
                    <button 
                        wire:click="setDenomination(0.500)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 0.500 KWD"
                    >
                        0.500
                    </button>
                    <button 
                        wire:click="setDenomination(1.000)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 1.000 KWD"
                    >
                        1
                    </button>
                    <button 
                        wire:click="setDenomination(5.000)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 5.000 KWD"
                    >
                        5
                    </button>
                    <button 
                        wire:click="setDenomination(10.000)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 10.000 KWD"
                    >
                        10
                    </button>
                    <button 
                        wire:click="setDenomination(20.000)"
                        class="py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 font-mono font-bold text-xs text-slate-200 transition active:scale-95 text-center"
                        title="Set Tendered to 20.000 KWD"
                    >
                        20
                    </button>
                    <button 
                        wire:click="setExact"
                        class="py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/40 text-emerald-400 font-mono font-bold text-xs transition active:scale-95 text-center"
                        title="Exact Tender Amount"
                    >
                        EXACT
                    </button>
                </div>

                <!-- 2. Tendered Display Bar & Payment Method Selector -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Tender input screen -->
                    <div class="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 flex items-center justify-between">
                        <span class="text-[10px] uppercase font-bold text-slate-500">Tender Cash</span>
                        <div class="flex items-center gap-1">
                            <span class="font-mono text-base font-bold text-slate-100">{{ $tenderedInput }}</span>
                            <span class="text-[10px] font-bold text-emerald-400">KWD</span>
                        </div>
                    </div>

                    <!-- Payment Mode Switch -->
                    <div class="flex items-center p-1 bg-slate-900 border border-slate-800 rounded-xl gap-1 shrink-0">
                        <button 
                            wire:click="setPaymentMethod('CASH')"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition {{ $paymentMethod === 'CASH' ? 'bg-emerald-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                        >
                            Cash
                        </button>
                        <button 
                            wire:click="setPaymentMethod('KNET')"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition {{ $paymentMethod === 'KNET' ? 'bg-teal-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                        >
                            K-NET
                        </button>
                        <button 
                            wire:click="setPaymentMethod('CARD')"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold transition {{ $paymentMethod === 'CARD' ? 'bg-indigo-500 text-slate-950 shadow-sm' : 'text-slate-400 hover:text-slate-200' }}"
                        >
                            Card
                        </button>
                    </div>
                </div>

                <!-- 3. High-Performance Full Numpad + Action Checkout -->
                <div class="flex-1 grid grid-cols-4 gap-1.5">
                    <!-- Row 1 -->
                    <button wire:click="numpadInput('7')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">7</button>
                    <button wire:click="numpadInput('8')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">8</button>
                    <button wire:click="numpadInput('9')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">9</button>
                    <button wire:click="numpadBackspace" class="rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 font-mono font-bold text-sm transition active:scale-95 flex items-center justify-center">⌫</button>

                    <!-- Row 2 -->
                    <button wire:click="numpadInput('4')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">4</button>
                    <button wire:click="numpadInput('5')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">5</button>
                    <button wire:click="numpadInput('6')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">6</button>
                    <button wire:click="numpadClear" class="rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-mono font-bold text-xs transition active:scale-95 flex items-center justify-center">CLEAR</button>

                    <!-- Row 3 -->
                    <button wire:click="numpadInput('1')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">1</button>
                    <button wire:click="numpadInput('2')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">2</button>
                    <button wire:click="numpadInput('3')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">3</button>
                    <button wire:click="addDenomination(1)" class="rounded-xl bg-slate-800/80 hover:bg-slate-700 border border-slate-700/60 font-mono font-semibold text-xs text-slate-200 transition active:scale-95 flex items-center justify-center">+1.000</button>

                    <!-- Row 4 -->
                    <button wire:click="numpadInput('0')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-base text-slate-100 transition active:scale-95 flex items-center justify-center">0</button>
                    <button wire:click="numpadInput('00')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-xs text-slate-100 transition active:scale-95 flex items-center justify-center">00</button>
                    <button wire:click="numpadInput('.')" class="rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 font-mono font-bold text-lg text-slate-100 transition active:scale-95 flex items-center justify-center">.</button>
                    <button wire:click="addDenomination(5)" class="rounded-xl bg-slate-800/80 hover:bg-slate-700 border border-slate-700/60 font-mono font-semibold text-xs text-slate-200 transition active:scale-95 flex items-center justify-center">+5.000</button>
                </div>

                <!-- 4. Main Checkout Button -->
                <button 
                    wire:click="checkout"
                    @disabled(count($cart) === 0)
                    class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 disabled:opacity-40 disabled:cursor-not-allowed text-slate-950 font-bold text-sm tracking-wide shadow-lg shadow-emerald-500/25 transition active:scale-[0.99] flex items-center justify-center gap-2 shrink-0"
                >
                    <span>CHARGE & PRINT RECEIPT</span>
                    <span class="font-mono bg-slate-950/20 px-2 py-0.5 rounded text-xs">
                        {{ number_format($total, 3) }} KWD
                    </span>
                    <span class="text-[10px] opacity-75">(Enter)</span>
                </button>
            </div>
        </section>
    </div>

    <!-- ========================================== -->
    <!-- RECEIPT MODAL / DIALOG                     -->
    <!-- ========================================== -->
    @if($showReceiptModal && $lastOrder)
        <div 
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-sm p-4"
            x-data
            @keydown.escape.window="$wire.closeReceiptModal()"
        >
            <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-5 flex flex-col text-slate-100 animate-in fade-in zoom-in-95 duration-200">
                <!-- Receipt Header -->
                <div class="text-center pb-4 border-b border-dashed border-slate-800">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mx-auto flex items-center justify-center text-xl font-bold mb-2">
                        ✓
                    </div>
                    <h3 class="font-bold text-base text-slate-100">PAYMENT SUCCESSFUL</h3>
                    <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $lastOrder->order_number }}</p>
                    <p class="text-[11px] text-slate-500">{{ $lastOrder->created_at->format('d M Y, h:i:s A') }}</p>
                </div>

                <!-- Items Breakdown -->
                <div class="py-3 border-b border-dashed border-slate-800 space-y-1.5 max-h-48 overflow-y-auto">
                    @foreach($lastOrderItems as $item)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-300">{{ $item['name'] }} × {{ $item['quantity'] }}</span>
                            <span class="font-mono text-slate-200">{{ number_format($item['subtotal'], 3) }} KWD</span>
                        </div>
                    @endforeach
                </div>

                <!-- Financial Breakdown -->
                <div class="py-3 border-b border-slate-800 space-y-1 text-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Subtotal</span>
                        <span class="font-mono">{{ number_format($lastOrder->subtotal, 3) }} KWD</span>
                    </div>
                    <div class="flex items-center justify-between font-bold text-sm text-slate-100 pt-1">
                        <span>Total Paid ({{ $lastOrder->payment_method }})</span>
                        <span class="font-mono text-emerald-400">{{ number_format($lastOrder->total, 3) }} KWD</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400 pt-1">
                        <span>Tendered</span>
                        <span class="font-mono">{{ number_format($lastOrder->tendered, 3) }} KWD</span>
                    </div>
                    <div class="flex items-center justify-between font-bold text-teal-400">
                        <span>Change Returned</span>
                        <span class="font-mono">{{ number_format($lastOrder->change, 3) }} KWD</span>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <button 
                        onclick="window.print()" 
                        class="py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 transition"
                    >
                        🖨 Print
                    </button>
                    <button 
                        wire:click="closeReceiptModal" 
                        class="py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-xs font-bold text-slate-950 transition"
                    >
                        New Sale (Esc)
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
