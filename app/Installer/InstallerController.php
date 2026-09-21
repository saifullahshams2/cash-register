<?php

namespace App\Installer;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\DatabaseHostValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use PDO;
use Throwable;

class InstallerController extends Controller
{
    public function __construct()
    {
        // Register view namespace for self-contained installer views
        if (! View::exists('installer::install')) {
            View::addNamespace('installer', __DIR__.'/views');
        }
    }

    public function index()
    {
        $this->prepareDirectoriesAndEnvironment();

        if ($this->isAlreadyInstalled()) {
            return redirect()->route('login')->with('info', 'Application is already installed.');
        }

        $requirements = $this->checkRequirements();
        $allRequirementsPassed = ! in_array(false, $requirements, true);

        return view('installer::install', [
            'title' => 'Production Setup & Installation',
            'requirements' => $requirements,
            'allRequirementsPassed' => $allRequirementsPassed,
            'isFinished' => false,
        ]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $this->prepareDirectoriesAndEnvironment();

        if ($this->isAlreadyInstalled()) {
            return response()->json([
                'success' => false,
                'message' => 'Application is already installed.',
            ], 403);
        }

        $host = (string) $request->input('host', '127.0.0.1');
        $port = (int) $request->input('port', 3306);
        $database = (string) $request->input('database', 'cash_register');
        $username = (string) $request->input('username', 'root');
        $password = (string) $request->input('password', '');

        // SSRF protection: deny-by-default. Resolve once and validate the
        // RESOLVED address so a later DNS re-resolution (TOCTOU) cannot point
        // the connection at a blocked target. Loopback, link-local, private
        // (RFC1918), reserved, and metadata ranges are rejected, as is any
        // value that does not resolve to a plain IP (which also blocks DSN
        // parameter injection such as 'host;user=...').
        $resolvedIp = null;
        if (! DatabaseHostValidator::isPermitted($host, $resolvedIp)) {
            return response()->json([
                'success' => false,
                'message' => 'Target database host is not permitted.',
            ], 422);
        }

        if ($port < 1 || $port > 65535) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid database port specified.',
            ], 422);
        }

        if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $database)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid database name. Use only alphanumeric characters, underscores, and hyphens (max 64 characters).',
            ], 422);
        }

        try {
            $dsn = "mysql:host={$resolvedIp};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 4,
            ]);

            $escapedDb = str_replace('`', '``', $database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$escapedDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return response()->json([
                'success' => true,
                'message' => "Connection successful! Database '{$database}' is ready.",
            ]);
        } catch (Throwable $e) {
            Log::error('Installer database test failed: '.$e->getMessage());
            $errorMsg = 'Database connection test failed. Please verify credentials and host.';

            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$errorMsg,
            ], 422);
        }
    }

    public function process(Request $request)
    {
        $this->prepareDirectoriesAndEnvironment();

        // Atomic single-flight gate: acquire an exclusive advisory lock before
        // any install work so concurrent requests cannot both pass the
        // installed check and run the installer (TOCTOU). Callers that lose
        // the race are redirected away.
        $installLock = fopen(storage_path('install.lock'), 'c');
        if ($installLock === false || ! flock($installLock, LOCK_EX | LOCK_NB)) {
            return redirect()->route('login');
        }

        if ($this->isAlreadyInstalled()) {
            fclose($installLock);

            return redirect()->route('login');
        }

        $rules = [
            'db_connection' => 'required|in:sqlite,mysql',
            'company_name' => 'required|string|max:150',
            'site_title' => 'required|string|max:100',
            'app_url' => 'required|url',
            // 'app_env' is intentionally not accepted: the installed application
            // must always run in production with debugging disabled. Accepting a
            // client-supplied value let unauthenticated callers force APP_DEBUG=true.
            'app_env' => 'prohibited',
            'admin_name' => 'required|string|max:100',
            'admin_username' => 'required|string|max:100',
            'admin_password' => 'required|string|min:6|confirmed',
        ];

        if ($request->input('db_connection') === 'mysql') {
            $rules['mysql_host'] = ['required', 'string', 'regex:/^[a-zA-Z0-9.-]+$/'];
            $rules['mysql_port'] = 'required|numeric|min:1|max:65535';
            $rules['mysql_database'] = ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'];
            $rules['mysql_username'] = 'required|string';
        }

        try {
            $request->validate($rules);

            $dbConn = $request->input('db_connection');
            $envUpdates = [
                'APP_NAME' => $request->input('site_title'),
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $request->input('app_url'),
                'DB_CONNECTION' => $dbConn,
            ];

            // Auto-generate APP_KEY if empty or missing on disk so user never needs to run php artisan key:generate
            $envPath = base_path('.env');
            $envContent = file_exists($envPath) ? (string) file_get_contents($envPath) : '';
            $hasKeyOnDisk = preg_match('/^APP_KEY=(.+)$/m', $envContent, $keyMatches)
                && ! empty(trim($keyMatches[1]))
                && ! str_contains($keyMatches[1], 'YOUR_APP_KEY_HERE');

            if (! $hasKeyOnDisk) {
                $newKey = 'base64:'.base64_encode(random_bytes(32));
                $envUpdates['APP_KEY'] = $newKey;
                config(['app.key' => $newKey]);
            }

            if ($dbConn === 'sqlite') {
                $sqlitePath = database_path('database.sqlite');
                if (! file_exists($sqlitePath)) {
                    touch($sqlitePath);
                }
            } else {
                $rawHost = (string) $request->input('mysql_host', '127.0.0.1');
                $resolvedIp = null;
                if (! DatabaseHostValidator::isPermitted($rawHost, $resolvedIp)) {
                    if (is_resource($installLock)) {
                        fclose($installLock);
                    }

                    return back()->withErrors(['mysql_host' => 'Target database host is not permitted.'])->withInput();
                }

                $envUpdates['DB_HOST'] = $rawHost;
                $envUpdates['DB_PORT'] = (string) $request->input('mysql_port', '3306');
                $envUpdates['DB_DATABASE'] = $request->input('mysql_database', 'cash_register');
                $envUpdates['DB_USERNAME'] = $request->input('mysql_username', 'root');
                $envUpdates['DB_PASSWORD'] = (string) $request->input('mysql_password', '');

                // Ensure MySQL database exists
                $dsn = "mysql:host={$resolvedIp};port={$envUpdates['DB_PORT']};charset=utf8mb4";
                $pdo = new PDO($dsn, $envUpdates['DB_USERNAME'], $envUpdates['DB_PASSWORD'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 4,
                ]);
                $escapedDb = str_replace('`', '``', $envUpdates['DB_DATABASE']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$escapedDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // 1. Update .env
            $this->updateEnvFile($envUpdates);
            Artisan::call('config:clear');

            // 2. Run database migrations
            Artisan::call('migrate', ['--force' => true]);

            // 4. Create the First Admin Account (NO email required, NO default cashier)
            if (User::where('username', $request->input('admin_username'))->exists()) {
                throw new \RuntimeException('An account with this username already exists.');
            }

            $adminUser = User::create([
                'username' => $request->input('admin_username'),
                'name' => $request->input('admin_name'),
                'password' => Hash::make($request->input('admin_password')),
                'email_verified_at' => now(),
            ]);
            $adminUser->forceFill(['role' => User::ROLE_ADMIN])->save();

            // 5. Store Company and Site Title settings
            Setting::set('company_name', trim($request->input('company_name')));
            Setting::set('site_title', trim($request->input('site_title')));

            // 6. Compile production frontend assets if npm is available
            try {
                if (function_exists('shell_exec') && ! app()->environment('testing')) {
                    @shell_exec('npm run build 2>&1');
                }
            } catch (Throwable $e) {
                Log::warning('NPM build during installation skipped: '.$e->getMessage());
            }

            // 7. Write storage/installed lockfile
            file_put_contents(storage_path('installed'), 'INSTALLED_AT='.now()->toIso8601String()."\n");
            if (is_resource($installLock)) {
                fclose($installLock);
            }

            return view('installer::install', [
                'title' => 'Installation Complete',
                'isFinished' => true,
                'adminUsername' => $request->input('admin_username'),
                'dbConnection' => $dbConn,
            ]);
        } catch (ValidationException $e) {
            if (is_resource($installLock)) {
                fclose($installLock);
            }

            throw $e;
        } catch (Throwable $e) {
            if (is_resource($installLock)) {
                fclose($installLock);
            }
            Log::error('Installation failed: '.$e->getMessage());
            $errorMsg = 'An unexpected error occurred during installation. Please check server logs.';

            return back()->withInput()->with('error', 'Installation failed: '.$errorMsg);
        }
    }

    public function isAlreadyInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    protected function checkRequirements(): array
    {
        $envPath = base_path('.env');
        $envWritable = file_exists($envPath) ? is_writable($envPath) : is_writable(base_path());

        return [
            'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'SQLite Support' => extension_loaded('pdo_sqlite'),
            'MySQL Support' => extension_loaded('pdo_mysql'),
            'OpenSSL Extension' => extension_loaded('openssl'),
            'Mbstring Extension' => extension_loaded('mbstring'),
            'Tokenizer Extension' => extension_loaded('tokenizer'),
            'XML Extension' => extension_loaded('xml'),
            'JSON Extension' => extension_loaded('json'),
            'ZIP Extension' => extension_loaded('zip'),
            'Environment File Writable' => $envWritable,
            'Storage Writable' => is_writable(storage_path()),
            'Bootstrap Cache Writable' => is_writable(base_path('bootstrap/cache')),
            'Database Dir Writable' => is_writable(database_path()),
        ];
    }

    protected function prepareDirectoriesAndEnvironment(): void
    {
        // 1. Ensure .env exists automatically
        $envPath = base_path('.env');
        if (! file_exists($envPath) && file_exists(base_path('.env.example'))) {
            @copy(base_path('.env.example'), $envPath);
        }

        // 2. Ensure all storage folders exist with write permissions
        $folders = [
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
            database_path(),
        ];

        foreach ($folders as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        // 3. Ensure public/storage exists or is symlinked
        $publicStorage = public_path('storage');
        $targetStorage = storage_path('app/public');
        if (! file_exists($publicStorage)) {
            try {
                @symlink($targetStorage, $publicStorage);
            } catch (Throwable) {
                // If symlink not permitted on shared host / windows, silently proceed
            }
        }
    }

    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            if (file_exists(base_path('.env.example'))) {
                copy(base_path('.env.example'), $envPath);
            } else {
                touch($envPath);
            }
        }

        if (! is_writable($envPath)) {
            throw new \RuntimeException("The .env file at [{$envPath}] is not writable. Please verify file permissions.");
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $sanitized = str_replace(["\r", "\n"], '', (string) $value);

            $escapedValue = (preg_match('/\s/', $sanitized) || str_contains($sanitized, '#') || str_contains($sanitized, '"'))
                ? '"'.addcslashes($sanitized, '"').'"'
                : $sanitized;

            $pattern = "/^#?\s*({$key}\s*=.*)$/m";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$escapedValue}", $content);
            } else {
                $content .= "\n{$key}={$escapedValue}";
            }
        }

        $written = file_put_contents($envPath, $content);
        if ($written === false) {
            throw new \RuntimeException("Failed to write updates to .env file at [{$envPath}].");
        }
    }
}
