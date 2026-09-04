<div class="min-h-dvh w-full flex items-center justify-center bg-slate-100 p-4 sm:p-6 font-sans text-slate-900">
    <div class="w-full max-w-md">
        <!-- Logo & Header -->
        <div class="text-center mb-6">
            @php
                $siteLogo = \App\Models\Setting::get('site_logo');
                $siteTitle = \App\Models\Setting::get('site_title', 'CASH REGISTER TERMINAL');
            @endphp
            @if($siteLogo)
                <img src="{{ $siteLogo }}" alt="Logo" class="inline-block w-12 h-12 object-contain rounded-xl border border-slate-200 p-0.5 bg-white mb-3 shadow-sm">
            @else
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-900 text-white font-bold text-lg mb-3 shadow-sm">
                    KW
                </div>
            @endif
            <h1 class="text-xl font-bold text-slate-900 tracking-tight">{{ $siteTitle }}</h1>
            <p class="text-xs text-slate-500 mt-1">Sign in with your Admin or Cashier account</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 space-y-5">
            <form wire:submit="login" class="space-y-4">
                <!-- Username Input -->
                <div>
                    <label for="username" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Username
                    </label>
                    <input 
                        wire:model="username" 
                        type="text" 
                        id="username" 
                        placeholder="e.g. admin, cashier, or any character"
                        required
                        autofocus
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                    >
                    @error('username')
                        <p class="text-xs text-rose-600 font-semibold mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                        Password
                    </label>
                    <input 
                        wire:model="password" 
                        type="password" 
                        id="password" 
                        placeholder="••••••••"
                        required
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-slate-900 focus:ring-1 focus:ring-slate-900 transition font-mono"
                    >
                    @error('password')
                        <p class="text-xs text-rose-600 font-semibold mt-1.5">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 select-none">
                        <input 
                            wire:model="remember" 
                            type="checkbox" 
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-900 w-4 h-4 cursor-pointer"
                        >
                        <span>Remember my login</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full py-3 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-xs transition active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
                >
                    <span wire:loading.remove>Sign In to Terminal</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Authenticating...
                    </span>
                </button>
            </form>

            <!-- Quick Demo Accounts Helper Box -->
            <div class="pt-4 border-t border-slate-100 text-[11px] text-slate-500 space-y-2">
                <div class="font-semibold text-slate-700 flex items-center justify-between">
                    <span>Default Accounts (password: <code class="bg-slate-100 px-1 py-0.5 rounded font-mono">password</code>):</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <button 
                        type="button"
                        wire:click="$set('username', 'admin'); $set('password', 'password');"
                        class="p-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-left cursor-pointer transition"
                    >
                        <div class="font-bold text-slate-800">Admin Account</div>
                        <div class="text-[10px] text-slate-500 font-mono truncate">admin</div>
                    </button>
                    <button 
                        type="button"
                        wire:click="$set('username', 'cashier'); $set('password', 'password');"
                        class="p-2 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-left cursor-pointer transition"
                    >
                        <div class="font-bold text-slate-800">Cashier Account</div>
                        <div class="text-[10px] text-slate-500 font-mono truncate">cashier</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

