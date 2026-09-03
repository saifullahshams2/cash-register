<div 
    x-data="{
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
    class="flex flex-col h-screen w-screen overflow-hidden bg-white text-slate-900 font-sans"
>
    <!-- TOP STATUS & APP BAR -->
    <header class="h-14 bg-white border-b border-slate-200 px-4 flex items-center justify-between shrink-0 z-20 shadow-xs">
        <div class="flex items-center gap-3">
            @php
                $siteLogo = \App\Models\Setting::get('site_logo');
                $siteTitle = \App\Models\Setting::get('site_title', 'CASH REGISTER');
            @endphp
            @if($siteLogo)
                <img src="{{ $siteLogo }}" alt="Logo" class="w-8 h-8 rounded-lg object-contain border border-slate-200 p-0.5 bg-white shadow-2xs">
            @else
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-sm tracking-wider">
                    KW
                </div>
            @endif
            <div>
                <h1 class="font-bold text-sm tracking-tight text-slate-900 flex items-center gap-2">
                    {{ $siteTitle }}
                    <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-300">
                        KWD (3 Decimals)
                    </span>
                </h1>
                <p class="text-[11px] text-slate-500">Terminal 01</p>
            </div>
        </div>

        <!-- Notification Toast Banner -->
        @if ($notificationMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                x-init="setTimeout(() => show = false, 4000)"
                x-transition:enter="transition ease-out duration-200 transform"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                class="px-4 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-2 border shadow-sm {{ $notificationType === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' : ($notificationType === 'info' ? 'bg-sky-50 border-sky-200 text-sky-800' : 'bg-emerald-50 border-emerald-300 text-emerald-900 font-bold') }}"
            >
                <span>{{ $notificationMessage }}</span>
            </div>
        @endif

        <!-- Header Right Actions -->
        <div class="flex items-center gap-3">
            @if(count($heldCarts) > 0)
                <div class="flex items-center gap-1.5 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                    <span class="text-xs font-semibold text-amber-800">Held ({{ count($heldCarts) }}):</span>
                    @foreach($heldCarts as $index => $held)
                        <button 
                            wire:click="restoreHeldCart({{ $index }})"
                            class="px-2 py-0.5 text-[11px] bg-amber-500 hover:bg-amber-600 text-white font-bold rounded transition cursor-pointer"
                            title="Restore Order {{ $held['time'] }}"
                        >
                            #{{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
            @endif

            <!-- User Info & Role Badge -->
            @auth
                <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-300 font-bold uppercase text-[10px]">
                        Cashier
                    </span>
                    <span class="text-xs font-bold text-slate-800 max-w-[120px] truncate" title="{{ Auth::user()->name }}">
                        {{ Auth::user()->name }}
                    </span>

                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button 
                            type="submit" 
                            class="px-2.5 py-1 text-xs font-semibold bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 rounded-lg transition cursor-pointer"
                            title="Sign out of terminal"
                        >
                            Logout
                        </button>
                    </form>
                </div>
            @endauth

            <div class="text-right pl-3 border-l border-slate-200">
                <span class="text-xs font-mono text-slate-500">{{ now()->format('d M Y') }}</span>
                <div class="text-xs font-mono font-bold text-slate-800" x-data="{ time: '' }" x-init="setInterval(() => { time = new Date().toLocaleTimeString('en-GB') }, 1000)" x-text="time"></div>
            </div>
        </div>
    </header>

    <!-- 3-COLUMN MAIN LAYOUT: LEFT (PRODUCTS 20%) | MIDDLE (NUMPAD 50%) | RIGHT (PRICING & CART 30%) -->
    <div class="flex-1 flex overflow-hidden w-full h-[calc(100vh-3.5rem)] bg-white">
        
        <!-- ============================================================== -->
        <!-- 1. LEFT COLUMN (20% WIDTH): PRODUCTS LIST                      -->
        <!--    PRICELESS LIST (ENTER PRICE VIA NUMPAD THEN CLICK ITEM)     -->
        <!-- ============================================================== -->
        <aside class="w-[20%] h-full flex flex-col border-r border-slate-200 bg-white shrink-0">
            <!-- Search Header -->
            <div class="p-3 border-b border-slate-200 bg-slate-50/70 shrink-0">
                <div class="relative">
                    <input 
                        x-ref="searchInput"
                        wire:model.live.debounce.150ms="search" 
                        type="text" 
                        placeholder="Search (Press /)"
                        class="w-full bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                    >
                    @if($search)
                        <button wire:click="$set('search', '')" class="absolute right-2 top-2 text-xs text-slate-400 hover:text-slate-700 cursor-pointer">✕</button>
                    @endif
                </div>
            </div>

            <!-- Priceless Products List (Product Name Only) -->
            <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
                @forelse($products as $product)
                    <button 
                        wire:key="product-{{ $product->id }}"
                        wire:click="addToCart({{ $product->id }})"
                        type="button"
                        class="w-full text-left p-3 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 hover:border-slate-400 active:bg-slate-200 transition cursor-pointer shadow-2xs"
                        title="Click to add to cart"
                    >
                        <span class="text-xs font-semibold text-slate-900 leading-snug block">
                            {{ $product->name }}
                        </span>
                    </button>
                @empty
                    <div class="py-12 text-center text-slate-400">
                        <p class="text-xs">No products found</p>
                    </div>
                @endforelse
            </div>

            <!-- Product Count Footer -->
            <div class="p-2 border-t border-slate-200 bg-slate-50 text-center text-[11px] text-slate-500 shrink-0 font-mono">
                {{ count($products) }} Products
            </div>
        </aside>

        <!-- ============================================================== -->
        <!-- 2. MIDDLE COLUMN (50% WIDTH): NUMPAD & TENDER WORKSPACE        -->
        <!-- ============================================================== -->
        <main class="w-[50%] h-full flex flex-col border-r border-slate-200 bg-white p-3 space-y-2 shrink-0 overflow-y-auto">
            
            <!-- A. NUMPAD INPUT DISPLAY (TOTAL PRICE DIRECT ENTRY) -->
            <div class="bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 flex items-center justify-between shadow-2xs shrink-0">
                <div>
                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-500 block">
                        Total Price Entry
                    </span>
                    <span class="text-[10px] text-slate-400">
                        Type total order amount directly on numpad
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-mono text-2xl font-bold text-slate-900 tracking-tight">{{ $totalInput }}</span>
                    <span class="text-xs font-bold text-slate-600">KWD</span>
                </div>
            </div>

            <!-- B. SPLIT WORKSPACE: NUMPAD | DIVIDER | TENDER CASH SECTION -->
            <div class="flex-1 flex items-stretch gap-2.5 min-h-[220px]">
                
                <!-- 1. NUMPAD (GRID) -->
                <div class="flex-1 grid grid-cols-3 gap-1.5 h-full">
                    <!-- Row 1 -->
                    <button wire:click="numpadInput('7')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">7</button>
                    <button wire:click="numpadInput('8')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">8</button>
                    <button wire:click="numpadInput('9')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">9</button>

                    <!-- Row 2 -->
                    <button wire:click="numpadInput('4')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">4</button>
                    <button wire:click="numpadInput('5')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">5</button>
                    <button wire:click="numpadInput('6')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">6</button>

                    <!-- Row 3 -->
                    <button wire:click="numpadInput('1')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">1</button>
                    <button wire:click="numpadInput('2')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">2</button>
                    <button wire:click="numpadInput('3')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">3</button>

                    <!-- Row 4 -->
                    <button wire:click="numpadInput('0')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">0</button>
                    <button wire:click="numpadInput('00')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-sm text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">00</button>
                    <button wire:click="numpadInput('.')" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-2xl text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">.</button>

                    <!-- Row 5 -->
                    <button wire:click="numpadClear" type="button" class="col-span-2 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-mono font-bold text-xs transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">CLEAR TOTAL</button>
                    <button wire:click="numpadBackspace" type="button" class="rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-300 text-rose-700 font-mono font-bold text-lg transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">⌫</button>
                </div>

                <!-- Small vertical divider between numpad and tender buttons -->
                <div class="w-px bg-slate-200 self-stretch my-0.5 rounded-full shrink-0"></div>

                <!-- 2. TENDER CASH MULTI-SELECTOR -->
                <div class="w-[36%] flex flex-col gap-1.5 h-full">
                    <!-- Denomination Multi-Selector Buttons (Cumulative addition) -->
                    <div class="grid grid-cols-2 gap-1.5 flex-1">
                        <button wire:click="addTender(20.000)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 20 KWD to Cash">+20</button>
                        <button wire:click="addTender(10.000)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 10 KWD to Cash">+10</button>
                        <button wire:click="addTender(5.000)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 5 KWD to Cash">+5</button>
                        <button wire:click="addTender(1.000)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 1 KWD to Cash">+1</button>
                        <button wire:click="addTender(0.500)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 0.500 KWD to Cash">+0.500</button>
                        <button wire:click="addTender(0.250)" type="button" class="rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer" title="Add 0.250 KWD to Cash">+0.250</button>
                    </div>

                    <!-- EXACT Button (Higher / Prominent) -->
                    <button wire:click="setExact" type="button" class="py-4 rounded-xl bg-emerald-50 hover:bg-emerald-100 border-2 border-emerald-400 text-emerald-900 font-mono font-extrabold text-sm tracking-wide transition active:scale-95 flex items-center justify-center shadow-xs cursor-pointer">
                        EXACT
                    </button>

                    <!-- Dedicated CLEAR TENDER Button -->
                    <button wire:click="clearTender" type="button" class="py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-300 text-rose-700 font-mono font-bold text-xs transition active:scale-95 flex items-center justify-center shadow-2xs cursor-pointer">
                        CLEAR TENDER
                    </button>
                </div>

            </div>

            <!-- C. PAYMENT METHOD BUTTONS: 1. CASH, 2. K-NET -->
            <div class="grid grid-cols-2 gap-2 shrink-0 pt-1">
                <!-- 1. CASH BUTTON -->
                <button 
                    wire:click="setPaymentMethod('CASH')"
                    type="button"
                    class="py-3 px-4 rounded-xl border-2 font-bold text-sm flex items-center justify-center gap-2 transition active:scale-98 cursor-pointer shadow-xs {{ $paymentMethod === 'CASH' ? 'bg-slate-900 border-slate-900 text-white ring-2 ring-slate-900/30' : 'bg-white border-slate-300 text-slate-800 hover:border-slate-500 hover:bg-slate-50' }}"
                >
                    <span class="text-base">💵</span>
                    <span>1. CASH</span>
                    @if($paymentMethod === 'CASH')
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    @endif
                </button>

                <!-- 2. K-NET BUTTON -->
                <button 
                    wire:click="setPaymentMethod('KNET')"
                    type="button"
                    class="py-3 px-4 rounded-xl border-2 font-bold text-sm flex items-center justify-center gap-2 transition active:scale-98 cursor-pointer shadow-xs {{ $paymentMethod === 'KNET' ? 'bg-slate-900 border-slate-900 text-white ring-2 ring-slate-900/30' : 'bg-white border-slate-300 text-slate-800 hover:border-slate-500 hover:bg-slate-50' }}"
                >
                    <span class="text-base">💳</span>
                    <span>2. K-NET</span>
                    @if($paymentMethod === 'KNET')
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    @endif
                </button>
            </div>

            <!-- D. DIRECT CHECKOUT BUTTON (BLOCKED WITHOUT PAYMENT METHOD SELECTION) -->
            <div class="shrink-0 pt-1">
                @if(empty($paymentMethod))
                    <button 
                        type="button"
                        disabled
                        class="w-full py-3.5 rounded-xl bg-slate-200 text-slate-400 font-bold text-xs tracking-wider uppercase border border-slate-300 cursor-not-allowed flex items-center justify-center gap-2 shadow-2xs"
                    >
                        <span>🔒 Select Cash or K-Net to Checkout</span>
                    </button>
                @else
                    <button 
                        wire:click="checkout"
                        type="button"
                        @disabled(count($cart) === 0)
                        class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-sm tracking-wide shadow-sm transition active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <span>CHECKOUT ({{ $paymentMethod }})</span>
                        <span class="font-mono bg-white/20 px-2.5 py-0.5 rounded-md text-xs">
                            {{ number_format($total, 3) }} KWD
                        </span>
                        <span class="text-[10px] opacity-80">(Enter)</span>
                    </button>
                @endif
            </div>

        </main>

        <!-- ============================================================== -->
        <!-- 3. RIGHT COLUMN (30% WIDTH): CART DISPLAY & PRICING IN 1 BOX   -->
        <!--    TOP HALF: CART ITEMS DISPLAY                                -->
        <!--    BOTTOM HALF: PRICING (1. TOTAL, 2. CASH, 3. CHANGE IN 1 BOX)-->
        <!-- ============================================================== -->
        <section class="w-[30%] h-full flex flex-col bg-white shrink-0">
            
            <!-- TOP HALF: CART DISPLAY -->
            <div class="flex-[1.2] flex flex-col border-b border-slate-200 overflow-hidden bg-white">
                <!-- Cart Title & Quick Controls -->
                <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Cart Display</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-200 text-slate-800">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'Item' : 'Items' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if(count($cart) > 0)
                            <button 
                                wire:click="holdCart" 
                                class="px-2.5 py-1 text-[11px] font-semibold bg-white hover:bg-slate-100 text-amber-700 border border-amber-300 rounded transition cursor-pointer"
                            >
                                Hold
                            </button>
                            <button 
                                wire:click="clearCart" 
                                class="px-2.5 py-1 text-[11px] font-semibold bg-white hover:bg-slate-100 text-rose-700 border border-rose-300 rounded transition cursor-pointer"
                            >
                                Clear
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Cart Items List (Product items without quantity controls) -->
                <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
                    @forelse($cart as $key => $item)
                        <div 
                            wire:key="cart-item-{{ $key }}"
                            class="flex items-center justify-between p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs hover:border-slate-300 transition"
                        >
                            <!-- Product Name -->
                            <div class="min-w-0 flex-1 pr-2">
                                <h5 class="text-xs font-bold text-slate-900 truncate">{{ $item['name'] }}</h5>
                            </div>

                            <!-- Remove Button -->
                            <button 
                                wire:click="removeFromCart({{ $key }})"
                                class="text-slate-400 hover:text-rose-600 p-1.5 transition text-xs font-bold cursor-pointer rounded hover:bg-slate-100"
                                title="Remove"
                            >
                                ✕
                            </button>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-slate-400 py-8">
                            <p class="text-xs text-center">Cart is empty.<br><span class="text-[11px] text-slate-400">Click products on the left to add items.</span></p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- BOTTOM HALF: PRICING ALL IN 1 UNIFIED BOX (1. TOTAL, 2. CASH, 3. CHANGE) -->
            <div class="p-3 bg-slate-50/70 shrink-0">
                
                <!-- UNIFIED 1-BOX PRICING CONTAINER -->
                <div class="bg-white border-2 border-slate-300 rounded-xl shadow-xs overflow-hidden divide-y divide-slate-200">
                    
                    <!-- 1. TOTAL ROW -->
                    <div class="p-3 bg-slate-900 text-white flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block">1. Total</span>
                            <span class="text-[10px] text-slate-400">Direct Numpad Total</span>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-extrabold text-2xl text-white block leading-tight">
                                {{ number_format($total, 3) }}
                            </span>
                            <span class="text-[10px] font-bold text-slate-300">KWD</span>
                        </div>
                    </div>

                    <!-- 2. CASH ROW -->
                    <div class="p-3 bg-white flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 block">2. Cash</span>
                            <span class="text-[10px] text-slate-400">Tendered Amount</span>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-bold text-xl text-slate-900 block leading-tight">
                                {{ number_format((float) $tenderedInput, 3) }}
                            </span>
                            <span class="text-[10px] font-bold text-slate-500">KWD</span>
                        </div>
                    </div>

                    <!-- 3. CHANGE ROW -->
                    <div class="p-3 {{ $changeDue >= 0 ? 'bg-emerald-50/70' : 'bg-rose-50/70' }} flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider {{ $changeDue >= 0 ? 'text-emerald-800' : 'text-rose-800' }} block">3. Change</span>
                            <span class="text-[10px] text-slate-500">Balance Due</span>
                        </div>
                        <div class="text-right">
                            <span class="font-mono font-extrabold text-xl block leading-tight {{ $changeDue >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ number_format(max(0, $changeDue), 3) }}
                            </span>
                            <span class="text-[10px] font-bold text-slate-600">KWD</span>
                        </div>
                    </div>

                </div>

            </div>
        </section>

    </div>
</div>

