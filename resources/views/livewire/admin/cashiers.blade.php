<div class="flex flex-col min-h-dvh w-full bg-slate-100 font-sans text-slate-900">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-2.5 sm:py-0 sm:h-14 flex items-center justify-between flex-wrap sm:flex-nowrap gap-2.5 shrink-0 shadow-2xs">
        <div class="flex items-center gap-3">
            <a 
                href="{{ route('pos') }}" 
                class="flex items-center gap-1.5 px-2.5 sm:px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-300 text-xs font-bold text-slate-800 transition shrink-0"
            >
                <span>←</span>
                <span>POS</span>
            </a>
            <div class="h-5 w-px bg-slate-300 hidden sm:block"></div>
            <div>
                <h1 class="font-bold text-xs sm:text-sm tracking-tight text-slate-900 truncate max-w-[200px] sm:max-w-none">
                    USER MANAGEMENT
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <div class="flex items-center gap-1.5 sm:gap-2 text-xs">
                <span class="w-2.5 h-2.5 rounded-full {{ Auth::user()->isAdmin() ? 'bg-purple-600' : 'bg-emerald-500' }} shrink-0" title="{{ Auth::user()->isAdmin() ? 'Admin' : 'Cashier' }}"></span>
                <span class="font-bold text-slate-800 text-xs truncate max-w-[120px] sm:max-w-none">{{ Auth::user()->name }}</span>
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
    <main class="flex-1 p-3.5 sm:p-6 max-w-7xl mx-auto w-full space-y-6">
        
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
            
            <!-- 1. CREATE USER ACCOUNT FORM (1 COLUMN) -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-xs p-5 space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-2">
                        <span>➕</span> Create New User
                    </h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Add a new cashier or administrator account</p>
                </div>

                <form wire:submit="createUser" class="space-y-3.5">
                    <!-- Role Selection -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Account Role
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-semibold transition {{ $role === 'cashier' ? 'border-emerald-500 bg-emerald-50 text-emerald-900 ring-1 ring-emerald-500' : 'border-slate-200 bg-slate-50/50 text-slate-700 hover:bg-slate-50' }}">
                                <input 
                                    wire:model.live="role" 
                                    type="radio" 
                                    value="cashier" 
                                    name="role"
                                    class="sr-only"
                                >
                                <span class="w-2.5 h-2.5 rounded-full {{ $role === 'cashier' ? 'bg-emerald-600' : 'bg-slate-300' }}"></span>
                                <span>Cashier</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer text-xs font-semibold transition {{ $role === 'admin' ? 'border-purple-500 bg-purple-50 text-purple-900 ring-1 ring-purple-500' : 'border-slate-200 bg-slate-50/50 text-slate-700 hover:bg-slate-50' }}">
                                <input 
                                    wire:model.live="role" 
                                    type="radio" 
                                    value="admin" 
                                    name="role"
                                    class="sr-only"
                                >
                                <span class="w-2.5 h-2.5 rounded-full {{ $role === 'admin' ? 'bg-purple-600' : 'bg-slate-300' }}"></span>
                                <span>Admin</span>
                            </label>
                        </div>
                        @error('role')
                            <p class="text-[11px] text-rose-600 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>

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

                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Username
                        </label>
                        <input 
                            wire:model="username" 
                            type="text" 
                            id="username" 
                            placeholder="e.g. sarah, cashier_1, or any character"
                            required
                            class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                        >
                        <p class="text-[10px] text-slate-400 mt-1">Username can be any character (letters, numbers, symbols, etc.)</p>
                        @error('username')
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
                        <span wire:loading.remove>Create {{ ucfirst($role) }} Account</span>
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
    </main>
</div>

