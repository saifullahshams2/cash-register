<div class="flex flex-col min-h-screen w-screen bg-slate-100 font-sans text-slate-900">
    <!-- Header -->
    <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-between shrink-0 shadow-2xs">
        <div class="flex items-center gap-4">
            <a 
                href="{{ route('pos') }}" 
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-bold text-slate-800 transition"
            >
                <span>←</span>
                <span>Back to POS</span>
            </a>
            <div class="h-5 w-px bg-slate-300"></div>
            <div>
                <h1 class="font-bold text-sm tracking-tight text-slate-900 flex items-center gap-2">
                    ADMIN PANEL: CASHIER MANAGEMENT
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 text-xs">
                <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-800 border border-purple-300 font-bold uppercase text-[10px]">
                    Admin
                </span>
                <span class="font-semibold text-slate-700">{{ Auth::user()->name }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button 
                    type="submit" 
                    class="px-2.5 py-1 text-xs font-semibold bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 rounded-lg transition cursor-pointer"
                >
                    Logout
                </button>
            </form>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 p-6 max-w-7xl mx-auto w-full space-y-6">
        
        <!-- Alerts Banner -->
        @if ($successMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                x-init="setTimeout(() => show = false, 5000)"
                class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-900 font-semibold text-xs flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2">
                    <span>✓</span>
                    <span>{{ $successMessage }}</span>
                </div>
                <button @click="show = false" class="text-emerald-700 hover:text-emerald-900 font-bold">✕</button>
            </div>
        @endif

        @if ($errorMessage)
            <div 
                x-data="{ show: true }" 
                x-show="show" 
                class="p-3.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 font-semibold text-xs flex items-center justify-between shadow-2xs"
            >
                <div class="flex items-center gap-2">
                    <span>⚠️</span>
                    <span>{{ $errorMessage }}</span>
                </div>
                <button @click="show = false" class="text-rose-700 hover:text-rose-900 font-bold">✕</button>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- 1. CREATE CASHIER ACCOUNT FORM (1 COLUMN) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>➕</span> Create New Cashier
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Add a new cashier login to access the terminal</p>
                </div>

                <form wire:submit="createCashier" class="space-y-3.5">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Full Name
                        </label>
                        <input 
                            wire:model="name" 
                            type="text" 
                            id="name" 
                            placeholder="e.g. Sarah Ahmad"
                            required
                            class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-sans"
                        >
                        @error('name')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Email Address (Login ID)
                        </label>
                        <input 
                            wire:model="email" 
                            type="email" 
                            id="email" 
                            placeholder="e.g. sarah@pos.test"
                            required
                            class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                        >
                        @error('email')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Password (Min 6 chars)
                        </label>
                        <input 
                            wire:model="password" 
                            type="password" 
                            id="password" 
                            placeholder="••••••••"
                            required
                            class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                        >
                        @error('password')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Confirm Password
                        </label>
                        <input 
                            wire:model="password_confirmation" 
                            type="password" 
                            id="password_confirmation" 
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
                        <span wire:loading.remove>Create Cashier Account</span>
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

            <!-- 2. USERS & CASHIERS LIST TABLE (2 COLUMNS) -->
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
                    <table class="w-full text-left border-collapse">
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
                                        <div class="font-mono text-[11px] text-slate-500">{{ $user->email }}</div>
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
                                                wire:click="deleteCashier({{ $user->id }})"
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
    </main>
</div>

