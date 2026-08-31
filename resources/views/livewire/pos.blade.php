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
    <!-- TOP STATUS & APP BAR (WHITE THEME) -->
    <header class="h-14 bg-white border-b border-slate-200 px-4 flex items-center justify-between shrink-0 z-20 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-sm tracking-wider">
                KW
            </div>
            <div>
                <h1 class="font-bold text-sm tracking-tight text-slate-900 flex items-center gap-2">
                    CASH REGISTER
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
                            class="px-2 py-0.5 text-[11px] bg-amber-500 hover:bg-amber-600 text-white font-bold rounded transition"
                            title="Restore Order {{ $held['time'] }}"
                        >
                            #{{ $index + 1 }}
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="text-right pl-3 border-l border-slate-200">
                <span class="text-xs font-mono text-slate-500">{{ now()->format('d M Y') }}</span>
                <div class="text-xs font-mono font-bold text-slate-800" x-data="{ time: '' }" x-init="setInterval(() => { time = new Date().toLocaleTimeString('en-GB') }, 1000)" x-text="time"></div>
            </div>
        </div>
    </header>

    <!-- 3-COLUMN MAIN LAYOUT -->
    <div class="flex-1 flex overflow-hidden w-full h-[calc(100vh-3.5rem)] bg-white">
        
        <!-- ============================================================== -->
        <!-- 1. LEFT COLUMN: 25% WIDTH FOR PRODUCTS (NO ICONS/SKU/PRICE/CAT) -->
        <!-- ============================================================== -->
        <aside class="w-[25%] h-full flex flex-col border-r border-slate-200 bg-white shrink-0">
            <!-- Search Header -->
            <div class="p-3 border-b border-slate-200 bg-slate-50/70 shrink-0">
                <div class="relative">
                    <input 
                        x-ref="searchInput"
                        wire:model.live.debounce.150ms="search" 
                        type="text" 
                        placeholder="Search product (Press /)"
                        class="w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                    >
                    @if($search)
                        <button wire:click="$set('search', '')" class="absolute right-2.5 top-2 text-xs text-slate-400 hover:text-slate-700">✕</button>
                    @endif
                </div>
            </div>

            <!-- Products List (Clean Minimalist Buttons with Product Name Only) -->
            <div class="flex-1 overflow-y-auto p-2.5 space-y-1.5">
                @forelse($products as $product)
                    <button 
                        wire:key="product-{{ $product->id }}"
                        wire:click="addToCart({{ $product->id }})"
                        type="button"
                        class="w-full text-left p-3 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 hover:border-slate-400 active:bg-slate-200 transition cursor-pointer shadow-2xs"
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
        <!-- 2. MIDDLE COLUMN: 30% WIDTH (BLANK FOR NOW)                     -->
        <!-- ============================================================== -->
        <main class="w-[30%] h-full flex flex-col border-r border-slate-200 bg-slate-50/50 relative shrink-0">
            <div class="flex-1 flex flex-col items-center justify-center p-6 text-center">
                <!-- Clean Minimal White Blank Canvas -->
                <div class="w-full max-w-xs p-6 rounded-xl border border-dashed border-slate-300 bg-white shadow-2xs flex flex-col items-center justify-center">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Workspace</span>
                    <p class="text-[11px] text-slate-400 mt-1">30% Middle Area (Blank)</p>
                </div>
            </div>
        </main>

        <!-- ============================================================== -->
        <!-- 3. RIGHT COLUMN: 45% WIDTH FOR CART, DENOMINATIONS & NUMPAD   -->
        <!-- ============================================================== -->
        <section class="w-[45%] h-full flex flex-col bg-white shrink-0">
            
            <!-- A. CART DISPLAY PORTION (TOP HALF) -->
            <div class="flex-[1.1] flex flex-col border-b border-slate-200 overflow-hidden bg-white">
                <!-- Cart Title & Quick Controls -->
                <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50/70 flex items-center justify-between shrink-0">
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
                                class="px-2.5 py-1 text-[11px] font-semibold bg-white hover:bg-slate-100 text-amber-700 border border-amber-300 rounded transition"
                            >
                                Hold
                            </button>
                            <button 
                                wire:click="clearCart" 
                                class="px-2.5 py-1 text-[11px] font-semibold bg-white hover:bg-slate-100 text-rose-700 border border-rose-300 rounded transition"
                            >
                                Clear
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Cart Items List -->
                <div class="flex-1 overflow-y-auto p-3 space-y-1.5">
                    @forelse($cart as $key => $item)
                        <div 
                            wire:key="cart-item-{{ $key }}"
                            class="flex items-center justify-between p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs hover:border-slate-300 transition"
                        >
                            <!-- Product Name & Unit Price -->
                            <div class="min-w-0 flex-1 pr-2">
                                <h5 class="text-xs font-semibold text-slate-900 truncate">{{ $item['name'] }}</h5>
                                <span class="text-[10px] font-mono text-slate-500">
                                    {{ number_format($item['price'], 3) }} KWD
                                </span>
                            </div>

                            <!-- Quantity Controls -->
                            <div class="flex items-center gap-1 shrink-0 mx-2">
                                <button 
                                    wire:click="decreaseQuantity({{ $item['id'] }})"
                                    class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 flex items-center justify-center text-xs font-bold transition"
                                >
                                    -
                                </button>
                                <span class="w-7 text-center font-mono font-bold text-xs text-slate-900">
                                    {{ $item['quantity'] }}
                                </span>
                                <button 
                                    wire:click="increaseQuantity({{ $item['id'] }})"
                                    class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 flex items-center justify-center text-xs font-bold transition"
                                >
                                    +
                                </button>
                            </div>

                            <!-- Subtotal in KWD (3 decimals) -->
                            <div class="text-right shrink-0 w-24">
                                <span class="font-mono font-bold text-xs text-slate-900 block">
                                    {{ number_format($item['subtotal'], 3) }}
                                </span>
                                <span class="text-[9px] font-semibold text-slate-500">KWD</span>
                            </div>

                            <!-- Remove Button -->
                            <button 
                                wire:click="removeFromCart({{ $item['id'] }})"
                                class="ml-2 text-slate-400 hover:text-rose-600 p-1 transition text-xs font-bold"
                                title="Remove"
                            >
                                ✕
                            </button>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-slate-400 py-8">
                            <p class="text-xs">Cart is empty. Select items from the left product list.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Totals Summary Bar -->
                <div class="p-3 bg-slate-50 border-t border-slate-200 grid grid-cols-2 gap-2.5 shrink-0">
                    <div class="bg-white border border-slate-200 rounded-lg p-2.5 flex flex-col justify-between shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Subtotal</span>
                            <span class="font-mono text-slate-700">{{ number_format($subtotal, 3) }} KWD</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold text-slate-900 mt-1 pt-1 border-t border-slate-100">
                            <span>Total</span>
                            <span class="font-mono text-slate-900 text-sm font-extrabold">{{ number_format($total, 3) }} KWD</span>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg p-2.5 flex flex-col justify-between shadow-2xs">
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Tendered</span>
                            <span class="font-mono font-bold text-slate-800">{{ number_format((float)$tenderedInput, 3) }} KWD</span>
                        </div>
                        <div class="flex items-center justify-between text-xs font-bold mt-1 pt-1 border-t border-slate-100">
                            <span class="text-slate-500">Change Due</span>
                            <span class="font-mono font-extrabold text-sm {{ $changeDue >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                                {{ number_format(max(0, $changeDue), 3) }} KWD
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- B. PAYMENT & NUMPAD PORTION (BOTTOM HALF) -->
            <div class="flex-1 flex flex-col p-3 bg-white overflow-hidden space-y-2">
                
                <!-- 1. Denominations (0.250, 0.500, 1, 5, 10, 20 and EXACT) -->
                <div class="grid grid-cols-7 gap-1.5 shrink-0">
                    <button 
                        wire:click="setDenomination(0.250)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        0.250
                    </button>
                    <button 
                        wire:click="setDenomination(0.500)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        0.500
                    </button>
                    <button 
                        wire:click="setDenomination(1.000)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        1
                    </button>
                    <button 
                        wire:click="setDenomination(5.000)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        5
                    </button>
                    <button 
                        wire:click="setDenomination(10.000)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        10
                    </button>
                    <button 
                        wire:click="setDenomination(20.000)"
                        class="py-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-800 transition active:scale-95 text-center shadow-2xs"
                    >
                        20
                    </button>
                    <button 
                        wire:click="setExact"
                        class="py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-400 text-emerald-800 font-mono font-bold text-xs transition active:scale-95 text-center shadow-2xs"
                    >
                        EXACT
                    </button>
                </div>

                <!-- 2. Tender Display & Payment Mode -->
                <div class="flex items-center gap-2 shrink-0">
                    <div class="flex-1 bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 flex items-center justify-between shadow-2xs">
                        <span class="text-[10px] uppercase font-bold text-slate-500">Tender Cash</span>
                        <div class="flex items-center gap-1">
                            <span class="font-mono text-base font-bold text-slate-900">{{ $tenderedInput }}</span>
                            <span class="text-[10px] font-bold text-slate-600">KWD</span>
                        </div>
                    </div>

                    <div class="flex items-center p-1 bg-slate-50 border border-slate-300 rounded-lg gap-1 shrink-0">
                        <button 
                            wire:click="setPaymentMethod('CASH')"
                            class="px-2.5 py-1 rounded text-xs font-bold transition {{ $paymentMethod === 'CASH' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            Cash
                        </button>
                        <button 
                            wire:click="setPaymentMethod('KNET')"
                            class="px-2.5 py-1 rounded text-xs font-bold transition {{ $paymentMethod === 'KNET' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            K-NET
                        </button>
                        <button 
                            wire:click="setPaymentMethod('CARD')"
                            class="px-2.5 py-1 rounded text-xs font-bold transition {{ $paymentMethod === 'CARD' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            Card
                        </button>
                    </div>
                </div>

                <!-- 3. Full Numpad -->
                <div class="flex-1 grid grid-cols-4 gap-1.5">
                    <!-- Row 1 -->
                    <button wire:click="numpadInput('7')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">7</button>
                    <button wire:click="numpadInput('8')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">8</button>
                    <button wire:click="numpadInput('9')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">9</button>
                    <button wire:click="numpadBackspace" class="rounded-lg bg-rose-50 hover:bg-rose-100 border border-rose-300 text-rose-700 font-mono font-bold text-sm transition active:scale-95 flex items-center justify-center shadow-2xs">⌫</button>

                    <!-- Row 2 -->
                    <button wire:click="numpadInput('4')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">4</button>
                    <button wire:click="numpadInput('5')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">5</button>
                    <button wire:click="numpadInput('6')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">6</button>
                    <button wire:click="numpadClear" class="rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 font-mono font-bold text-xs transition active:scale-95 flex items-center justify-center shadow-2xs">CLEAR</button>

                    <!-- Row 3 -->
                    <button wire:click="numpadInput('1')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">1</button>
                    <button wire:click="numpadInput('2')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">2</button>
                    <button wire:click="numpadInput('3')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">3</button>
                    <button wire:click="addDenomination(1)" class="rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 font-mono font-semibold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs">+1.000</button>

                    <!-- Row 4 -->
                    <button wire:click="numpadInput('0')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-base text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">0</button>
                    <button wire:click="numpadInput('00')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-xs text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">00</button>
                    <button wire:click="numpadInput('.')" class="rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-300 font-mono font-bold text-lg text-slate-900 transition active:scale-95 flex items-center justify-center shadow-2xs">.</button>
                    <button wire:click="addDenomination(5)" class="rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 font-mono font-semibold text-xs text-slate-800 transition active:scale-95 flex items-center justify-center shadow-2xs">+5.000</button>
                </div>

                <!-- 4. Direct Checkout / Charge Button (No popup, saves directly and resets cart) -->
                <button 
                    wire:click="checkout"
                    @disabled(count($cart) === 0)
                    class="w-full py-3 rounded-lg bg-slate-900 hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold text-sm tracking-wide shadow-sm transition active:scale-[0.99] flex items-center justify-center gap-2 shrink-0"
                >
                    <span>CHECKOUT</span>
                    <span class="font-mono bg-white/20 px-2 py-0.5 rounded text-xs">
                        {{ number_format($total, 3) }} KWD
                    </span>
                    <span class="text-[10px] opacity-75">(Enter)</span>
                </button>
            </div>
        </section>
    </div>
</div>
