<?php

namespace Dziurka\LaravelPreset\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that all expected stub files are present in the stubs/ directory.
 * This catches cases where a file is referenced in InstallCommand but missing on disk.
 */
class StubFilesTest extends TestCase
{
    private string $stubsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubsPath = realpath(__DIR__.'/../../stubs');
    }

    /** @dataProvider requiredStubsProvider */
    public function test_stub_file_exists(string $path): void
    {
        $this->assertFileExists(
            $this->stubsPath.'/'.$path,
            "Stub file missing: stubs/{$path}",
        );
    }

    /** @return array<string, array{string}> */
    public static function requiredStubsProvider(): array
    {
        return [
            // Database variants
            'docker-compose mysql' => ['docker-compose.mysql.yml'],
            'docker-compose pgsql' => ['docker-compose.pgsql.yml'],
            'workflow mysql' => ['.github/workflows/app.mysql.yml'],
            'workflow pgsql' => ['.github/workflows/app.pgsql.yml'],
            'copilot instructions' => ['.github/copilot-instructions.md'],

            // Shared
            'justfile' => ['justfile'],
            'phpstan.neon' => ['phpstan.neon'],
            'deploy.yaml' => ['deploy.yaml'],
            '.githooks/pre-commit' => ['.githooks/pre-commit'],

            // Deployer scripts
            'deploy/app.php' => ['deploy/app.php'],
            'deploy/provision.php' => ['deploy/provision.php'],
            'deploy/provision/github.php' => ['deploy/provision/github.php'],
            'deploy/provision/services.php' => ['deploy/provision/services.php'],
            'deploy/provision/system.php' => ['deploy/provision/system.php'],

            // Docker images
            'docker/8.3/Dockerfile' => ['docker/8.3/Dockerfile'],
            'docker/8.4/Dockerfile' => ['docker/8.4/Dockerfile'],
            'docker/8.3/php.ini' => ['docker/8.3/php.ini'],
            'docker/8.4/php.ini' => ['docker/8.4/php.ini'],

            // DB init scripts
            'docker/mysql/create-testing-database.sh' => ['docker/mysql/create-testing-database.sh'],
            'docker/mariadb/create-testing-database.sh' => ['docker/mariadb/create-testing-database.sh'],
            'docker/pgsql/create-testing-database.sql' => ['docker/pgsql/create-testing-database.sql'],
        ];
    }

    public function test_stubs_directory_is_readable(): void
    {
        $this->assertIsString($this->stubsPath);
        $this->assertDirectoryExists($this->stubsPath);
        $this->assertDirectoryIsReadable($this->stubsPath);
    }

    public function test_deploy_yaml_contains_required_keys(): void
    {
        $content = file_get_contents($this->stubsPath.'/deploy.yaml');

        $this->assertStringContainsString('repository:', $content);
        $this->assertStringContainsString('php_fpm_version:', $content);
        $this->assertStringContainsString('php_version:', $content);
        $this->assertStringContainsString('db_driver:', $content);
        $this->assertStringContainsString('mailpit_port:', $content);
        $this->assertStringContainsString('supervisor_services:', $content);
    }

    public function test_docker_compose_mysql_uses_mariadb_11(): void
    {
        $content = file_get_contents($this->stubsPath.'/docker-compose.mysql.yml');

        $this->assertStringContainsString('mariadb:11', $content);
        $this->assertStringNotContainsString('mariadb:10', $content);
    }

    public function test_docker_compose_uses_valkey(): void
    {
        foreach (['docker-compose.mysql.yml', 'docker-compose.pgsql.yml'] as $file) {
            $content = file_get_contents($this->stubsPath.'/'.$file);

            $this->assertStringContainsString('valkey/valkey:8-alpine', $content, "Expected valkey image in {$file}");
            $this->assertStringNotContainsString('redis:', $content, "Unexpected redis service in {$file}");
        }
    }

    public function test_docker_compose_mailpit_has_healthcheck(): void
    {
        foreach (['docker-compose.mysql.yml', 'docker-compose.pgsql.yml'] as $file) {
            $content = file_get_contents($this->stubsPath.'/'.$file);

            $this->assertStringContainsString('healthcheck', $content);
            $this->assertStringContainsString('localhost:8025', $content, "Mailpit healthcheck missing in {$file}");
        }
    }

    public function test_dockerfiles_use_python3_not_python2(): void
    {
        foreach (['8.3', '8.4'] as $version) {
            $path = $this->stubsPath."/docker/{$version}/Dockerfile";

            if (! file_exists($path)) {
                continue;
            }

            $content = file_get_contents($path);
            $this->assertStringNotContainsString('python2', $content, "python2 found in docker/{$version}/Dockerfile");
        }
    }
}
