<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;

class SwitchDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:switch 
                            {connection=mysql : Database driver to switch to (mysql or sqlite)}
                            {--host= : MySQL host}
                            {--port= : MySQL port}
                            {--database= : Database name}
                            {--username= : MySQL username}
                            {--password= : MySQL password}
                            {--migrate : Run database migrations after switching}
                            {--seed : Seed database after running migrations}
                            {--force : Bypass confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch the active database engine between MySQL and SQLite';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $connection = strtolower(trim($this->argument('connection')));

        if (! in_array($connection, ['mysql', 'sqlite'], true)) {
            $this->error("Invalid connection '{$connection}'. Supported drivers are 'mysql' and 'sqlite'.");

            return Command::FAILURE;
        }

        if ($connection === 'sqlite') {
            return $this->switchToSqlite();
        }

        return $this->switchToMysql();
    }

    protected function switchToSqlite(): int
    {
        $this->info('Switching active database connection to SQLite...');

        $sqlitePath = database_path('database.sqlite');
        if (! file_exists($sqlitePath)) {
            touch($sqlitePath);
            $this->info("Created SQLite file at: {$sqlitePath}");
        }

        $this->updateEnv(['DB_CONNECTION' => 'sqlite']);
        $this->call('config:clear');

        $this->info('Successfully switched database connection to SQLite!');

        if ($this->option('migrate') || ($this->input->isInteractive() && ! $this->option('force') && $this->confirm('Would you like to run migrations on the SQLite database now?', false))) {
            $migrateArgs = [];
            if ($this->option('seed')) {
                $migrateArgs['--seed'] = true;
            }
            $this->call('migrate', $migrateArgs);
        }

        return Command::SUCCESS;
    }

    protected function switchToMysql(): int
    {
        $host = $this->option('host') ?: env('DB_HOST', '127.0.0.1');
        $port = $this->option('port') ?: env('DB_PORT', '3306');
        $database = $this->option('database') ?: env('DB_DATABASE', 'cash_register');
        $username = $this->option('username') ?: env('DB_USERNAME', 'root');
        $password = $this->option('password') !== null ? (string) $this->option('password') : (string) env('DB_PASSWORD', '');

        $this->info("Testing MySQL connection on {$host}:{$port} as '{$username}'...");

        try {
            // First attempt connection to MySQL host
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 4,
            ]);

            // Ensure database exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("MySQL database '{$database}' is verified and ready.");
        } catch (Throwable $e) {
            $this->error("Failed to connect to MySQL: {$e->getMessage()}");
            $this->warn('Please verify that your MySQL service (e.g. XAMPP, Laragon, MySQL Server) is running on port '.$port);

            if (! $this->option('force')) {
                return Command::FAILURE;
            }

            $this->warn('Proceeding with configuration update (--force flag specified).');
        }

        $this->updateEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ]);

        $this->call('config:clear');
        $this->info("Successfully switched database connection to MySQL ('{$database}')!");

        if ($this->option('migrate') || ($this->input->isInteractive() && ! $this->option('force') && $this->confirm('Would you like to run migrations on the MySQL database now?', true))) {
            $migrateArgs = [];
            if ($this->option('seed')) {
                $migrateArgs['--seed'] = true;
            }
            $this->call('migrate', $migrateArgs);
        }

        return Command::SUCCESS;
    }

    /**
     * Safely update key-value pairs in the .env file.
     *
     * @param  array<string, string>  $data
     */
    protected function updateEnv(array $data): void
    {
        $envPath = base_path('.env');
        if (! file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $escapedValue = $this->escapeEnvValue($value);

            // If key exists (even if commented out), replace it
            $pattern = "/^#?\s*({$key}\s*=.*)$/m";
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, "{$key}={$escapedValue}", $content);
            } else {
                $content .= "\n{$key}={$escapedValue}";
            }
        }

        file_put_contents($envPath, $content);
    }

    protected function escapeEnvValue(string $value): string
    {
        if (preg_match('/\s/', $value) || str_contains($value, '#') || str_contains($value, '"')) {
            return '"'.addcslashes($value, '"').'"';
        }

        return $value;
    }
}
