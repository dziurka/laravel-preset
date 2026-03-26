<?php

namespace Dziurka\LaravelPreset\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'preset:install';

    protected $description = 'Install the dziurka/laravel-preset scaffolding into a fresh Laravel project';

    private string $stubsPath;

    private string $db;

    private string $phpSail;

    private string $phpProd;

    public function __construct()
    {
        parent::__construct();
        $this->stubsPath = realpath(__DIR__.'/../../stubs');
    }

    public function handle(): int
    {
        $this->info('🚀 Installing laravel-preset...');
        $this->newLine();

        $this->db = $this->choice(
            'Which database driver do you want to use?',
            ['MySQL / MariaDB', 'PostgreSQL'],
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
            $this->installFrontendDependencies();

            if ($this->confirm('Install Deployer for deployment automation?', false)) {
                $this->installDeployer();
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

        $db   = $this->isPostgres() ? 'pgsql' : 'mysql';
        $base = base_path();

        $this->copyFile("docker-compose.{$db}.yml", $base.'/docker-compose.yml');
        $this->copyFile(".env.example.{$db}", $base.'/.env.example');
        $this->copyFile(".env.pipelines.{$db}", $base.'/.env.pipelines');
        $this->copyFile(".github/workflows/app.{$db}.yml", $base.'/.github/workflows/app.yml');
        $this->copyFile('.github/copilot-instructions.md', $base.'/.github/copilot-instructions.md');
        $this->copyFile('justfile', $base.'/justfile');

        $this->copyDirectory('docker', $base.'/docker');

        $this->patchDockerCompose($base.'/docker-compose.yml');
        $this->patchJustfile($base.'/justfile');

        // CI workflow mirrors production PHP version
        $this->patchFile($base.'/.github/workflows/app.yml', [
            "/(?<=php-version: ')[0-9]+\.[0-9]+/" => $this->phpProd,
        ]);

        // Merge our .env.example values into the existing .env (preserves APP_KEY etc.)
        $this->mergeEnv($base.'/.env', $this->stubsPath."/.env.example.{$db}");
    }

    private function patchDockerCompose(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $nodots  = str_replace('.', '', $this->phpSail);
        $content = File::get($path);

        $patched = preg_replace(
            ["/(?<=docker\/)[0-9]+\.[0-9]+/", "/(?<=sail-)[0-9]+\.[0-9]+(?=\/app)/"],
            [$this->phpSail, $nodots],
            $content,
        );

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }

    private function patchJustfile(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $nodots  = str_replace('.', '', $this->phpSail);
        $content = File::get($path);

        // e.g. laravelsail/php84-composer → laravelsail/php83-composer
        $patched = preg_replace('/(?<=php)\d+(?=-composer)/', $nodots, $content);

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }

    private function updateEnvFiles(): void
    {
        $this->info('⚙️  Updating .env files...');

        $replacements = $this->isPostgres()
            ? [
                '/^DB_CONNECTION=.*/m' => 'DB_CONNECTION=pgsql',
                '/^DB_HOST=.*/m'       => 'DB_HOST=pgsql',
                '/^DB_PORT=.*/m'       => 'DB_PORT=5432',
            ]
            : [
                '/^DB_CONNECTION=.*/m' => 'DB_CONNECTION=mysql',
                '/^DB_HOST=.*/m'       => 'DB_HOST=mariadb',
                '/^DB_PORT=.*/m'       => 'DB_PORT=3306',
            ];

        $replacements = array_merge($replacements, [
            '/^SESSION_DRIVER=.*/m'   => 'SESSION_DRIVER=database',
            '/^QUEUE_CONNECTION=.*/m' => 'QUEUE_CONNECTION=database',
            '/^CACHE_STORE=.*/m'      => 'CACHE_STORE=redis',
            '/^REDIS_HOST=.*/m'       => 'REDIS_HOST=redis',
            '/^MAIL_HOST=.*/m'        => 'MAIL_HOST=mailpit',
        ]);

        foreach (['.env', '.env.example'] as $filename) {
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

    private function installFrontendDependencies(): void
    {
        $this->info('🎨 Adding frontend dependencies (Inertia + Vue)...');

        $packageJsonPath = base_path('package.json');

        if (! File::exists($packageJsonPath)) {
            $this->warn('package.json not found, skipping frontend dependencies.');

            return;
        }

        $package = json_decode(File::get($packageJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn('package.json is invalid ('.json_last_error_msg().'), skipping frontend dependencies.');

            return;
        }

        $package['dependencies'] = array_merge($package['dependencies'] ?? [], [
            '@inertiajs/vue3' => '^2.0',
            'vue' => '^3.5',
        ]);

        $package['devDependencies'] = array_merge($package['devDependencies'] ?? [], [
            '@vitejs/plugin-vue' => '^5.2',
            'vue-tsc' => '^2.2',
        ]);

        File::put(
            $packageJsonPath,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        $this->info('📦 Running yarn install...');
        $this->runProcess(['yarn', 'install']);
    }

    private function installDeployer(): void
    {
        $this->info('🚢 Installing Deployer...');

        $this->runComposer(['require', '--dev', 'deployer/deployer:^7.4']);

        $base = base_path();
        $this->copyFile('deploy.yaml', $base.'/deploy.yaml');
        $this->copyDirectory('deploy', $base.'/deploy');

        $this->patchFile($base.'/deploy.yaml', [
            "/(?<=php_fpm_version: ')[0-9]+\.[0-9]+/" => $this->phpProd,
            "/(?<=php_version: ')[0-9]+\.[0-9]+/"     => $this->phpProd,
            '/(?<=db_driver: )\w+/'                    => $this->isPostgres() ? 'pgsql' : 'mysql',
        ]);

        $this->configureDeployYaml($base.'/deploy.yaml');
    }

    private function configureDeployYaml(string $deployYamlPath): void
    {
        $this->newLine();
        $this->info('⚙️  Configuring deploy.yaml...');
        $this->comment('You can leave any field empty and fill it in manually later.');
        $this->newLine();

        $repo = $this->ask('Git repository URL (e.g. git@github.com:your-org/your-app.git)');
        $prodHost = $this->ask('Production server IP or hostname');
        $stagingHost = $this->ask('Staging server IP or hostname');

        $replacements = [];

        if ($repo) {
            $replacements['/^(\s*repository:\s*).*$/m'] = '${1}'."'{$repo}'";
        }

        if ($prodHost || $stagingHost) {
            $content = File::get($deployYamlPath);

            if ($prodHost) {
                // Replace the production hostname line (first occurrence after "production:")
                $content = preg_replace(
                    '/^(\s*)(production:\s*\n(?:.*\n)*?\s*hostname:\s*).*$/m',
                    '${1}${2}'."'{$prodHost}'",
                    $content,
                    1,
                );
            }

            if ($stagingHost) {
                // Replace the staging hostname line (second occurrence / after "staging:")
                $content = preg_replace(
                    '/^(\s*)(staging:\s*\n(?:.*\n)*?\s*hostname:\s*).*$/m',
                    '${1}${2}'."'{$stagingHost}'",
                    $content,
                    1,
                );
            }

            File::put($deployYamlPath, $content);
        }

        if ($replacements) {
            $this->patchFile($deployYamlPath, $replacements);
        }

        $this->newLine();
        $this->info('✅ deploy.yaml configured.');

        if (! $repo || ! $prodHost) {
            $this->comment('👉 Remember to fill in any remaining REQUIRED fields in deploy.yaml before deploying.');
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

    private function patchFile(string $path, array $replacements): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $patched = preg_replace(array_keys($replacements), array_values($replacements), $content);

        if ($patched !== $content) {
            File::put($path, $patched);
        }
    }

    private function runComposer(array $arguments): void
    {
        $this->runProcess(array_merge(['composer'], $arguments));
    }

    private function runProcess(array $command): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout(null);

        $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Command failed: '.implode(' ', $command));
        }
    }

    /**
     * Merge variables from our .env.example into the existing .env.
     * Variables already present in .env are overwritten with our values.
     * Variables only in .env (e.g. APP_KEY) are preserved unchanged.
     * Variables in .env.example not yet in .env are appended.
     */
    private function mergeEnv(string $envPath, string $examplePath): void
    {
        if (! File::exists($examplePath)) {
            return;
        }

        if (! File::exists($envPath)) {
            File::copy($examplePath, $envPath);
            $this->line('  <info>+</info> .env');

            return;
        }

        $env     = File::get($envPath);
        $example = File::get($examplePath);

        // Extract KEY=VALUE lines from our .env.example (skip comments and blanks)
        preg_match_all('/^([A-Z][A-Z0-9_]*)=(.*)/m', $example, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$key, $value] = [$match[1], $match[2]];

            if (preg_match('/^'.preg_quote($key, '/').'=/m', $env)) {
                $env = preg_replace('/^'.preg_quote($key, '/').'=.*/m', "{$key}={$value}", $env);
            } else {
                $env .= PHP_EOL."{$key}={$value}";
            }
        }

        File::put($envPath, $env);
        $this->line('  <info>~</info> .env (merged preset values)');
    }

    private function copyFile(string $stub, string $destination): void
    {
        $source = $this->stubsPath.'/'.$stub;

        if (! File::exists($source)) {
            return;
        }

        File::ensureDirectoryExists(dirname($destination));

        $relative = str_replace(base_path().'/', '', $destination);

        if (File::exists($destination) && ! $this->confirm("  {$relative} already exists. Overwrite?", false)) {
            return;
        }

        File::copy($source, $destination);
        $this->line("  <info>+</info> {$relative}");
    }

    private function copyDirectory(string $stub, string $destination): void
    {
        $source = $this->stubsPath.'/'.$stub;

        if (! File::isDirectory($source)) {
            return;
        }

        File::copyDirectory($source, $destination);

        $relative = str_replace(base_path().'/', '', $destination);
        $this->line("  <info>+</info> {$relative}/");
    }
}
