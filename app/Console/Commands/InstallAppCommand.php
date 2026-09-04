<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class InstallAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install
                            {--database=sqlite : Database driver to use (sqlite or mysql)}
                            {--admin-name=System Administrator : Full name of the first administrator}
                            {--admin-username=admin : Username for the first administrator}
                            {--admin-password=password : Password for the first administrator}
                            {--company=My Store POS : Company or store name}
                            {--title=CASH REGISTER : System terminal title}
                            {--force : Force installation even if already installed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run headless production installation and provision first administrator';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (file_exists(storage_path('installed')) && ! $this->option('force')) {
            $this->warn('Application is already marked as installed. Use --force to reinstall.');

            return Command::SUCCESS;
        }

        $this->info('Starting Cash Register POS Production Installation...');

        $dbDriver = strtolower($this->option('database'));
        if ($dbDriver === 'sqlite') {
            $sqliteFile = database_path('database.sqlite');
            if (! file_exists($sqliteFile)) {
                touch($sqliteFile);
            }
        }

        // 1. Run migrations
        $this->info('Running database migrations...');
        $this->call('migrate', ['--force' => true]);

        // 1b. Compile production frontend assets if npm is available
        if (function_exists('shell_exec')) {
            $this->info('Compiling production frontend assets with npm...');
            @shell_exec('npm run build 2>&1');
        }

        // 2. Create the first Administrator account (No email required, no default cashier)
        $adminName = (string) $this->option('admin-name');
        $adminUsername = (string) $this->option('admin-username');
        $adminPassword = (string) $this->option('admin-password');

        $this->info("Creating administrator account '{$adminUsername}'...");
        User::updateOrCreate(
            ['username' => $adminUsername],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        // 3. Configure store branding
        $companyName = (string) $this->option('company');
        $siteTitle = (string) $this->option('title');

        Setting::set('company_name', $companyName);
        Setting::set('site_title', $siteTitle);

        // 4. Lock installation
        file_put_contents(storage_path('installed'), 'INSTALLED_AT='.now()->toIso8601String()."\n");

        $this->info('✓ Installation successfully completed!');
        $this->info("Admin Username: {$adminUsername}");
        $this->info("Database Engine: {$dbDriver}");
        $this->info('Note: You can safely delete the app/Installer directory if desired.');

        return Command::SUCCESS;
    }
}
