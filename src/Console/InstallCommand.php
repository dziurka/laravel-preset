<?php

namespace Dziurka\LaravelPreset\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'preset:install';

    protected $description = 'Install the dziurka/laravel-preset scaffolding into a fresh Laravel project';

    use HasPresetHelpers;

    private string $db;

    private string $phpSail;

    private string $phpProd;

    public function __construct()
    {
        parent::__construct();
        $this->initStubsPath();
    }

    public function handle(): int
    {
        $this->info('🚀 Installing laravel-preset...');
        $this->newLine();

        $this->db = $this->choice(
            'Which database driver do you want to use?',
            ['PostgreSQL', 'MySQL / MariaDB'],
            0,
        );

        $this->phpSail = $this->choice(
            'PHP version for local development (Sail)?',
            ['8.4', '8.3'],
            0,
        );

        $this->phpProd = $this->choice(
            'PHP version for the production server?',
            ['8.4', '8.3'],
            0,
        );

        try {
            $this->installComposerDependencies();
            $this->copyStubs();
            $this->updateEnvFiles();

            if ($this->confirm('Install Deployer for deployment automation?', false)) {
                $this->call('preset:deployer', [
                    '--php-version' => $this->phpProd,
                    '--db' => $this->isPostgres() ? 'pgsql' : 'mysql',
                ]);
            }

            $this->installBoost();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ laravel-preset installed successfully!');
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  1. cp .env.example .env');
        $this->line('  2. just build');
        $this->line('  3. just install <project-name>');

        return self::SUCCESS;
    }

    private function isPostgres(): bool
    {
        return $this->db === 'PostgreSQL';
    }

    private function installComposerDependencies(): void
    {
        $this->info('📦 Installing Composer dependencies...');

        $this->runComposer([
            'require', '--dev',
            'barryvdh/laravel-ide-helper:^3.6',
            'brianium/paratest:^7.8',
            'larastan/larastan:^3.0',
            'laravel/pail:^1.2',
            'laravel/pint:^1.18',
            'laravel/sail:^1.46',
        ]);
    }

    private function copyStubs(): void
    {
        $this->info('📁 Copying configuration files...');

        $db = $this->isPostgres() ? 'pgsql' : 'mysql';
        $base = base_path();

        $this->copyFile("docker-compose.{$db}.yml", $base.'/docker-compose.yml');
        $this->copyFile('.env.pipelines', $base.'/.env.pipelines');
        $this->copyFile(".github/workflows/app.{$db}.yml", $base.'/.github/workflows/app.yml');
        $this->copyFile('.github/copilot-instructions.md', $base.'/.github/copilot-instructions.md');
        $this->copyFile('justfile', $base.'/justfile');

        $this->copyDirectory('docker', $base.'/docker');

        $this->patchDockerCompose($base.'/docker-compose.yml', $this->phpSail);
        $this->patchJustfile($base.'/justfile', $this->phpSail);

        // CI workflow mirrors production PHP version
        $this->patchFile($base.'/.github/workflows/app.yml', [
            "/(?<=php-version: ')[0-9]+\.[0-9]+/" => $this->phpProd,
        ]);
    }

    private function updateEnvFiles(): void
    {
        $this->info('⚙️  Updating .env files...');

        $dbReplacements = $this->isPostgres()
            ? [
                '/^DB_CONNECTION=.*/m' => 'DB_CONNECTION=pgsql',
                '/^DB_HOST=.*/m' => 'DB_HOST=pgsql',
                '/^DB_PORT=.*/m' => 'DB_PORT=5432',
            ]
            : [
                '/^DB_CONNECTION=.*/m' => 'DB_CONNECTION=mysql',
                '/^DB_HOST=.*/m' => 'DB_HOST=mariadb',
                '/^DB_PORT=.*/m' => 'DB_PORT=3306',
            ];

        // Common patches for local dev (.env / .env.example)
        $localReplacements = array_merge($dbReplacements, [
            '/^DB_USERNAME=.*/m' => 'DB_USERNAME=sail',
            '/^DB_PASSWORD=.*/m' => 'DB_PASSWORD=password',
            '/^SESSION_DRIVER=.*/m' => 'SESSION_DRIVER=redis',
            '/^QUEUE_CONNECTION=.*/m' => 'QUEUE_CONNECTION=horizon',
            '/^CACHE_STORE=.*/m' => 'CACHE_STORE=redis',
            '/^REDIS_HOST=.*/m' => 'REDIS_HOST=valkey',
            '/^MAIL_MAILER=.*/m' => 'MAIL_MAILER=smtp',
            '/^MAIL_HOST=.*/m' => 'MAIL_HOST=mailpit',
            '/^MAIL_PORT=.*/m' => 'MAIL_PORT=1025',
        ]);

        // Pipeline patches: same DB driver, but CI-specific values
        $pipelineReplacements = array_merge($dbReplacements, [
            '/^DB_HOST=.*/m' => 'DB_HOST=127.0.0.1',
        ]);

        $this->applyEnvReplacements(['.env', '.env.example'], $localReplacements);
        $this->applyEnvReplacements(['.env.pipelines'], $pipelineReplacements);
    }

    private function applyEnvReplacements(array $filenames, array $replacements): void
    {
        foreach ($filenames as $filename) {
            $path = base_path($filename);

            if (! File::exists($path)) {
                continue;
            }

            $contents = File::get($path);

            foreach ($replacements as $pattern => $replacement) {
                $contents = preg_replace($pattern, $replacement, $contents);
            }

            File::put($path, $contents);
        }
    }

    private function installBoost(): void
    {
        $this->info('🤖 Installing Laravel Boost (AI agent integration)...');

        $this->runComposer(['require', '--dev', 'laravel/boost']);

        $this->newLine();
        $this->comment('Running boost:install wizard — choose your AI editor/agent below:');
        $this->newLine();

        $this->runProcess(['php', 'artisan', 'boost:install']);
    }
}
