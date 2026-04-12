<?php

declare(strict_types=1);

namespace Dziurka\LaravelPreset\Console;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

final class UpdateCommand extends Command
{
    protected $signature = 'preset:update';

    protected $description = 'Update selected laravel-preset files in this project';

    use HasPresetHelpers;

    private const FILES = [
        'docker-compose' => 'docker-compose.yml',
        'env-pipelines' => '.env.pipelines',
        'workflow' => '.github/workflows/app.yml',
        'copilot' => '.github/copilot-instructions.md',
        'justfile' => 'justfile',
        'phpstan' => 'phpstan.neon',
        'githooks' => '.githooks/pre-commit',
        'docker' => 'docker/ (directory)',
    ];

    private const NEEDS_DB = ['docker-compose', 'workflow'];

    private const NEEDS_PHP = ['docker-compose', 'justfile', 'docker'];

    public function __construct()
    {
        parent::__construct();
        $this->initStubsPath();
    }

    public function handle(): int
    {
        /** @var string[] $selected */
        $selected = multiselect(
            label: 'Which files do you want to update?',
            options: self::FILES,
            required: true,
        );

        $db = null;
        $phpSail = null;
        $phpProd = null;

        if (array_intersect($selected, self::NEEDS_DB)) {
            $db = $this->choice(
                'Which database driver does this project use?',
                ['PostgreSQL', 'MySQL / MariaDB'],
                0,
            );
        }

        if (array_intersect($selected, self::NEEDS_PHP)) {
            $phpSail = $this->choice(
                'PHP version for local development (Sail)?',
                ['8.4', '8.3'],
                0,
            );
        }

        if (in_array('workflow', $selected)) {
            $phpProd = $this->choice(
                'PHP version for the production server?',
                ['8.4', '8.3'],
                0,
            );
        }

        $this->info('📁 Updating preset files...');

        $base = base_path();
        $isPostgres = $db === 'PostgreSQL';

        if (in_array('docker-compose', $selected)) {
            $driver = $isPostgres ? 'pgsql' : 'mysql';
            $this->copyFile("docker-compose.{$driver}.yml", $base.'/docker-compose.yml');
            $this->patchDockerCompose($base.'/docker-compose.yml', (string) $phpSail);
        }

        if (in_array('env-pipelines', $selected)) {
            $this->copyFile('.env.pipelines', $base.'/.env.pipelines');
        }

        if (in_array('workflow', $selected)) {
            $driver = $isPostgres ? 'pgsql' : 'mysql';
            $this->copyFile(".github/workflows/app.{$driver}.yml", $base.'/.github/workflows/app.yml');
            $this->patchFile($base.'/.github/workflows/app.yml', [
                "/(?<=php-version: ')[0-9]+\.[0-9]+/" => (string) $phpProd,
            ]);
        }

        if (in_array('copilot', $selected)) {
            $this->copyFile('.github/copilot-instructions.md', $base.'/.github/copilot-instructions.md');
        }

        if (in_array('justfile', $selected)) {
            $this->copyFile('justfile', $base.'/justfile');
            $this->patchJustfile($base.'/justfile', (string) $phpSail);
        }

        if (in_array('phpstan', $selected)) {
            $this->copyFile('phpstan.neon', $base.'/phpstan.neon');
        }

        if (in_array('githooks', $selected)) {
            $this->installGitHooks(safe: true);
        }

        if (in_array('docker', $selected)) {
            $this->copyDirectory('docker', $base.'/docker');
        }

        $this->newLine();
        $this->info('✅ Preset files updated successfully!');

        return self::SUCCESS;
    }
}
