@extends('installer::layout')

@section('content')
<div class="bg-white border border-slate-200 rounded-3xl shadow-sm overflow-hidden">
    
    @if($isFinished ?? false)
        <!-- SUCCESS / COMPLETED SCREEN -->
        <div class="p-8 sm:p-10 text-center space-y-6">
            <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl shadow-inner">
                ✓
            </div>

            <div class="space-y-2">
                <h2 class="text-2xl font-black text-slate-900 tracking-tight">Installation Completed!</h2>
                <p class="text-sm text-slate-600 max-w-md mx-auto">
                    Your Cash Register POS system has been successfully installed, migrated, and configured for production use.
                </p>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-left max-w-md mx-auto space-y-2 text-xs">
                <div class="font-bold text-slate-800 uppercase tracking-wider text-[11px] pb-1 border-b border-slate-200">
                    Administrator Credentials
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-500 font-medium">Username:</span>
                    <strong class="font-mono text-slate-900">{{ $adminUsername }}</strong>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-500 font-medium">Active Database:</span>
                    <strong class="uppercase text-slate-900">{{ $dbConnection }}</strong>
                </div>
            </div>

            <!-- Notice: User can delete installer folder -->
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-left max-w-md mx-auto text-xs text-amber-900 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <span>🛡️</span> Security &amp; Cleanup Notice
                </div>
                <p class="text-[11px] text-amber-800 leading-relaxed">
                    You can now safely delete the <strong><code class="bg-amber-100 px-1 py-0.5 rounded font-mono">app/Installer</code></strong> folder. The installer has been permanently locked, and removing the folder will leave your application running seamlessly.
                </p>
            </div>

            <div class="pt-2">
                <a 
                    href="{{ route('login') }}" 
                    class="inline-flex items-center justify-center px-8 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm shadow-md transition active:scale-98 cursor-pointer"
                >
                    Proceed to Login &rarr;
                </a>
            </div>
        </div>
    @else
        <!-- INSTALLATION WIZARD FORM -->
        <div class="border-b border-slate-100 px-6 sm:px-8 py-4 bg-slate-50/50 flex items-center justify-between">
            <h2 class="text-sm font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                <span>🚀</span> Setup &amp; Deployment Wizard
            </h2>
            <span class="text-xs font-bold text-slate-500 bg-white border border-slate-200 px-2.5 py-1 rounded-full shadow-2xs">
                v1.0 Production
            </span>
        </div>

        @if(session('error'))
            <div class="m-6 sm:m-8 mb-0 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('installer.process') }}" method="POST" class="p-6 sm:p-8 space-y-8">
            @csrf

            <!-- SECTION 1: System Requirements -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px]">1</span>
                        System &amp; Server Requirements
                    </h3>
                    @if($allRequirementsPassed)
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                            ✓ Ready for Production
                        </span>
                    @else
                        <span class="text-[11px] font-bold text-rose-700 bg-rose-50 border border-rose-200 px-2.5 py-0.5 rounded-full">
                            ⚠️ Some Requirements Failed
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    @foreach($requirements as $item => $passed)
                        <div class="p-2 rounded-xl border flex items-center justify-between {{ $passed ? 'bg-emerald-50/40 border-emerald-200 text-emerald-900' : 'bg-rose-50/40 border-rose-200 text-rose-900' }}">
                            <span class="font-medium truncate mr-1">{{ $item }}</span>
                            <span class="font-bold shrink-0 {{ $passed ? 'text-emerald-600' : 'text-rose-600' }}">{{ $passed ? '✓' : '✗' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- SECTION 2: Database Configuration -->
            <div class="space-y-3 pt-6 border-t border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px]">2</span>
                    Select Database Engine
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="p-4 rounded-2xl border cursor-pointer transition select-none has-checked:border-emerald-600 has-checked:bg-emerald-50/30 has-checked:ring-1 has-checked:ring-emerald-600 border-slate-200 bg-white hover:bg-slate-50">
                        <input type="radio" name="db_connection" value="sqlite" id="db_sqlite" class="sr-only" checked onchange="toggleDatabaseFields('sqlite')">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm text-slate-900">SQLite</span>
                            <span class="text-[10px] uppercase font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded">Zero Config</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Fast, local file-based database. Ready out of the box, no database server needed.</p>
                    </label>

                    <label class="p-4 rounded-2xl border cursor-pointer transition select-none has-checked:border-blue-600 has-checked:bg-blue-50/30 has-checked:ring-1 has-checked:ring-blue-600 border-slate-200 bg-white hover:bg-slate-50">
                        <input type="radio" name="db_connection" value="mysql" id="db_mysql" class="sr-only" onchange="toggleDatabaseFields('mysql')">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-sm text-slate-900">MySQL / MariaDB</span>
                            <span class="text-[10px] uppercase font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded">Enterprise Scale</span>
                        </div>
                        <p class="text-[11px] text-slate-500">Production client-server RDBMS (XAMPP, Laragon, or cloud MySQL server).</p>
                    </label>
                </div>

                <!-- MySQL Specific Fields (Toggled) -->
                <div id="mysql_fields" class="hidden p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3.5 mt-2">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">MySQL Host</label>
                            <input type="text" name="mysql_host" id="mysql_host" value="{{ old('mysql_host', '127.0.0.1') }}" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">MySQL Port</label>
                            <input type="text" name="mysql_port" id="mysql_port" value="{{ old('mysql_port', '3306') }}" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Database Name</label>
                            <input type="text" name="mysql_database" id="mysql_database" value="{{ old('mysql_database', 'cash_register') }}" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">MySQL Username</label>
                            <input type="text" name="mysql_username" id="mysql_username" value="{{ old('mysql_username', 'root') }}" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">MySQL Password</label>
                            <input type="password" name="mysql_password" id="mysql_password" placeholder="Leave empty if default root without password" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                        </div>
                    </div>

                    <!-- Ajax Test Button -->
                    <div class="flex items-center justify-between pt-1">
                        <div id="test_result" class="text-xs font-semibold"></div>
                        <button 
                            type="button" 
                            onclick="testMysqlConnection()" 
                            class="px-3.5 py-1.5 text-xs font-bold rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-700 transition cursor-pointer shadow-2xs"
                        >
                            Test Connection
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Store & Environment Details -->
            <div class="space-y-3 pt-6 border-t border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px]">3</span>
                    Company &amp; Environment Setup
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Company / Store Name</label>
                        <input type="text" name="company_name" required value="{{ old('company_name', 'My Store POS') }}" placeholder="e.g. Al-Bustan Stores" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Website / Terminal Title</label>
                        <input type="text" name="site_title" required value="{{ old('site_title', 'CASH REGISTER') }}" placeholder="e.g. CASH REGISTER TERMINAL" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Application URL</label>
                        <input type="url" name="app_url" required value="{{ old('app_url', url('/')) }}" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Environment Mode</label>
                        <select name="app_env" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                            <option value="production" selected>Production (Secure, Debug Disabled)</option>
                            <option value="local">Local Development (Debug Enabled)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: First Administrator Account (NO EMAIL) -->
            <div class="space-y-3 pt-6 border-t border-slate-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px]">4</span>
                        Create First Administrator Account
                    </h3>
                    <span class="text-[10px] text-slate-400 font-semibold">Super Admin Privileges</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Admin Full Name</label>
                        <input type="text" name="admin_name" required value="{{ old('admin_name', 'System Administrator') }}" placeholder="e.g. Ahmed Al-Sabah" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:border-slate-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Admin Username</label>
                        <input type="text" name="admin_username" required value="{{ old('admin_username', 'admin') }}" placeholder="e.g. admin, manager, or any character" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:border-slate-900">
                        <p class="text-[10px] text-slate-400 mt-0.5">Supports any characters. No email required.</p>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Password</label>
                        <input type="password" name="admin_password" required placeholder="Minimum 6 characters" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:border-slate-900">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Confirm Password</label>
                        <input type="password" name="admin_password_confirmation" required placeholder="Re-type password" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs text-slate-800 font-mono focus:outline-none focus:border-slate-900">
                    </div>
                </div>
            </div>

            <!-- SUBMIT BUTTON -->
            <div class="pt-6 border-t border-slate-100 flex items-center justify-between">
                <div class="text-[11px] text-slate-400">
                    Migrations will be executed automatically.
                </div>
                <button 
                    type="submit" 
                    id="submit_btn"
                    class="py-3 px-8 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-md transition active:scale-98 flex items-center justify-center gap-2 cursor-pointer"
                >
                    Install &amp; Launch System &rarr;
                </button>
            </div>
        </form>
    @endif

</div>
@endsection

@section('scripts')
<script>
    function toggleDatabaseFields(driver) {
        const mysqlBox = document.getElementById('mysql_fields');
        if (driver === 'mysql') {
            mysqlBox.classList.remove('hidden');
        } else {
            mysqlBox.classList.add('hidden');
        }
    }

    async function testMysqlConnection() {
        const resultEl = document.getElementById('test_result');
        resultEl.innerHTML = '<span class="text-slate-500">Testing connection...</span>';

        const payload = {
            _token: '{{ csrf_token() }}',
            host: document.getElementById('mysql_host').value,
            port: document.getElementById('mysql_port').value,
            database: document.getElementById('mysql_database').value,
            username: document.getElementById('mysql_username').value,
            password: document.getElementById('mysql_password').value,
        };

        try {
            const res = await fetch('{{ route('installer.test-db') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                resultEl.innerHTML = '<span class="text-emerald-600">✓ ' + data.message + '</span>';
            } else {
                resultEl.innerHTML = '<span class="text-rose-600">✗ ' + data.message + '</span>';
            }
        } catch (err) {
            resultEl.innerHTML = '<span class="text-rose-600">✗ Network/request failed</span>';
        }
    }
</script>
@endsection
