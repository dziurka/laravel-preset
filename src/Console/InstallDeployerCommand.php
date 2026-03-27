<?php

namespace Dziurka\LaravelPreset\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallDeployerCommand extends Command
{
    protected $signature = 'preset:deployer
                            {--php-version= : PHP version for the production server (skips prompt)}
                            {--db= : Database driver — pgsql or mysql (skips prompt)}';

    protected $description = 'Install Deployer v8 into an existing Laravel project with dziurka/laravel-preset';

    use HasPresetHelpers;

    private string $phpProd;

    private string $db;

    public function __construct()
    {
        parent::__construct();
        $this->initStubsPath();
    }

    public function handle(): int
    {
        $this->info('🚢 Installing Deployer...');
        $this->newLine();

        $this->phpProd = $this->option('php-version') ?? $this->choice(
            'PHP version for the production server?',
            ['8.4', '8.3'],
            0,
        );

        $dbChoice = $this->option('db');

        if ($dbChoice) {
            $this->db = $dbChoice === 'pgsql' ? 'PostgreSQL' : 'MySQL / MariaDB';
        } else {
            $this->db = $this->choice(
                'Which database driver does the production server use?',
                ['PostgreSQL', 'MySQL / MariaDB'],
                0,
            );
        }

        try {
            $this->install();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ Deployer installed.');
        $this->comment('Next: fill in deploy.yaml (repository, hostnames) then run:');
        $this->line('  ./vendor/bin/dep provision staging');
        $this->line('  ./vendor/bin/dep deploy staging');

        return self::SUCCESS;
    }

    private function isPostgres(): bool
    {
        return $this->db === 'PostgreSQL';
    }

    private function install(): void
    {
        $this->runComposer(['require', '--dev', 'deployer/deployer:^8.0']);

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

        $repo        = $this->ask('Git repository URL (e.g. git@github.com:your-org/your-app.git)');
        $prodHost    = $this->ask('Production server IP or hostname');
        $stagingHost = $this->ask('Staging server IP or hostname');

        $replacements = [];

        if ($repo) {
            $replacements['/^(\s*repository:\s*).*$/m'] = '${1}'."'{$repo}'";
        }

        $content = File::get($deployYamlPath);

        if ($prodHost) {
            $content = preg_replace(
                '/^(\s*)(production:\s*\n(?:.*\n)*?\s*hostname:\s*).*$/m',
                '${1}${2}'."'{$prodHost}'",
                $content,
                1,
            );
        }

        if ($stagingHost) {
            $content = preg_replace(
                '/^(\s*)(staging:\s*\n(?:.*\n)*?\s*hostname:\s*).*$/m',
                '${1}${2}'."'{$stagingHost}'",
                $content,
                1,
            );
        }

        File::put($deployYamlPath, $content);

        if ($replacements) {
            $this->patchFile($deployYamlPath, $replacements);
        }

        $this->newLine();
        $this->info('✅ deploy.yaml configured.');

        if (! $repo || ! $prodHost) {
            $this->comment('👉 Remember to fill in any remaining REQUIRED fields in deploy.yaml before deploying.');
        }
    }
}
