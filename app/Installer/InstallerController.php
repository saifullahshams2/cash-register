<?php

namespace App\Installer;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
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

        $host = $request->input('host', '127.0.0.1');
        $port = $request->input('port', '3306');
        $database = $request->input('database', 'cash_register');
        $username = $request->input('username', 'root');
        $password = (string) $request->input('password', '');

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 4,
            ]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return response()->json([
                'success' => true,
                'message' => "Connection successful! Database '{$database}' is ready.",
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ], 422);
        }
    }

    public function process(Request $request)
    {
        $this->prepareDirectoriesAndEnvironment();

        if ($this->isAlreadyInstalled()) {
            return redirect()->route('login');
        }

        $rules = [
            'db_connection' => 'required|in:sqlite,mysql',
            'company_name' => 'required|string|max:150',
            'site_title' => 'required|string|max:100',
            'app_url' => 'required|url',
            'app_env' => 'required|in:production,local',
            'admin_name' => 'required|string|max:100',
            'admin_username' => 'required|string|max:100',
            'admin_password' => 'required|string|min:6|confirmed',
        ];

        if ($request->input('db_connection') === 'mysql') {
            $rules['mysql_host'] = 'required|string';
            $rules['mysql_port'] = 'required|numeric';
            $rules['mysql_database'] = 'required|string';
            $rules['mysql_username'] = 'required|string';
        }

        $request->validate($rules);

        try {
            $dbConn = $request->input('db_connection');
            $envUpdates = [
                'APP_NAME' => $request->input('site_title'),
                'APP_ENV' => $request->input('app_env'),
                'APP_DEBUG' => $request->input('app_env') === 'local' ? 'true' : 'false',
                'APP_URL' => $request->input('app_url'),
                'DB_CONNECTION' => $dbConn,
            ];

            // Auto-generate APP_KEY if empty so user never needs to run php artisan key:generate
            $currentKey = config('app.key') ?: env('APP_KEY');
            if (empty($currentKey) || $currentKey === 'base64:YOUR_APP_KEY_HERE') {
                $envUpdates['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
            }

            if ($dbConn === 'sqlite') {
                $sqlitePath = database_path('database.sqlite');
                if (! file_exists($sqlitePath)) {
                    touch($sqlitePath);
                }
            } else {
                $envUpdates['DB_HOST'] = $request->input('mysql_host', '127.0.0.1');
                $envUpdates['DB_PORT'] = (string) $request->input('mysql_port', '3306');
                $envUpdates['DB_DATABASE'] = $request->input('mysql_database', 'cash_register');
                $envUpdates['DB_USERNAME'] = $request->input('mysql_username', 'root');
                $envUpdates['DB_PASSWORD'] = (string) $request->input('mysql_password', '');

                // Ensure MySQL database exists
                $dsn = "mysql:host={$envUpdates['DB_HOST']};port={$envUpdates['DB_PORT']};charset=utf8mb4";
                $pdo = new PDO($dsn, $envUpdates['DB_USERNAME'], $envUpdates['DB_PASSWORD'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 4,
                ]);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$envUpdates['DB_DATABASE']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // 1. Update .env
            $this->updateEnvFile($envUpdates);
            Artisan::call('config:clear');

            // 2. Run database migrations
            Artisan::call('migrate', ['--force' => true]);

            // 4. Create the First Admin Account (NO email required, NO default cashier)
            User::updateOrCreate(
                ['username' => $request->input('admin_username')],
                [
                    'name' => $request->input('admin_name'),
                    'password' => Hash::make($request->input('admin_password')),
                    'role' => User::ROLE_ADMIN,
                    'email_verified_at' => now(),
                ]
            );

            // 5. Store Company and Site Title settings
            Setting::set('company_name', trim($request->input('company_name')));
            Setting::set('site_title', trim($request->input('site_title')));

            // 6. Write storage/installed lockfile
            file_put_contents(storage_path('installed'), 'INSTALLED_AT='.now()->toIso8601String()."\n");

            return view('installer::install', [
                'title' => 'Installation Complete',
                'isFinished' => true,
                'adminUsername' => $request->input('admin_username'),
                'dbConnection' => $dbConn,
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Installation failed: '.$e->getMessage());
        }
    }

    public function isAlreadyInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    protected function checkRequirements(): array
    {
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
                @mkdir($dir, 0777, true);
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
            copy(base_path('.env.example'), $envPath);
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
}
