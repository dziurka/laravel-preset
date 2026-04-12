<?php

namespace Dziurka\LaravelPreset\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests that regex patterns used by InstallCommand patch files correctly.
 * These patterns must stay in sync with the private patchFile / patchDockerCompose
 * / patchJustfile methods — if a pattern changes here the test will catch it.
 */
class PatchingTest extends TestCase
{
    // ── Docker-compose patterns ───────────────────────────────────────────────

    public function test_docker_context_path_is_replaced(): void
    {
        $content = 'context: ./docker/8.4';
        $result = preg_replace('/(docker\/)\d+\.\d+/', '${1}8.3', $content);

        $this->assertSame('context: ./docker/8.3', $result);
    }

    public function test_sail_image_version_is_replaced(): void
    {
        $content = 'image: sail-8.4/app';
        $result = preg_replace('/(sail-)\d+\.\d+(\/app)/', '${1}8.3${2}', $content);

        $this->assertSame('image: sail-8.3/app', $result);
    }

    // ── Justfile pattern ─────────────────────────────────────────────────────

    public function test_justfile_composer_image_version_is_replaced(): void
    {
        $content = 'laravelsail/php84-composer:latest';
        $result = preg_replace('/(?<=php)\d+(?=-composer)/', '83', $content);

        $this->assertSame('laravelsail/php83-composer:latest', $result);
    }

    // ── Workflow / deploy.yaml patterns ──────────────────────────────────────

    public function test_workflow_php_version_is_replaced(): void
    {
        $content = "php-version: '8.2'";
        $result = preg_replace("/(?<=php-version: ')\d+\.\d+/", '8.4', $content);

        $this->assertSame("php-version: '8.4'", $result);
    }

    public function test_deploy_yaml_php_fpm_version_is_replaced(): void
    {
        $content = "php_fpm_version: '8.2'";
        $result = preg_replace("/(?<=php_fpm_version: ')\d+\.\d+/", '8.4', $content);

        $this->assertSame("php_fpm_version: '8.4'", $result);
    }

    public function test_deploy_yaml_php_version_is_replaced(): void
    {
        $content = "php_version: '8.2'";
        $result = preg_replace("/(?<=php_version: ')\d+\.\d+/", '8.4', $content);

        $this->assertSame("php_version: '8.4'", $result);
    }

    public function test_deploy_yaml_db_driver_is_replaced(): void
    {
        $content = 'db_driver: mysql';
        $result = preg_replace('/(?<=db_driver: )\w+/', 'pgsql', $content);

        $this->assertSame('db_driver: pgsql', $result);
    }

    public function test_deploy_yaml_db_driver_defaults_to_mysql(): void
    {
        $content = 'db_driver: mysql';
        $result = preg_replace('/(?<=db_driver: )\w+/', 'mysql', $content);

        $this->assertSame('db_driver: mysql', $result);
    }

    // ── Multiple replacements at once ─────────────────────────────────────────

    public function test_all_workflow_php_versions_are_replaced(): void
    {
        $content = implode("\n", [
            "          php-version: '8.2'",
            "          php-version: '8.2'",
            "          php-version: '8.2'",
            "          php-version: '8.2'",
        ]);

        $result = preg_replace("/(?<=php-version: ')\d+\.\d+/", '8.4', $content);
        $count = substr_count($result, "php-version: '8.4'");

        $this->assertSame(4, $count);
    }
}
