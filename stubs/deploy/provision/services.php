<?php

namespace Deployer;

// ── Mailpit ───────────────────────────────────────────────────────────────────

task('provision:mailpit', function () {
    if (! get('mailpit_enabled', false)) {
        info('Mailpit disabled for this host — skipping. Configure a real mail provider in .env.');

        return;
    }

    $mailpitBin = '/usr/local/bin/mailpit';
    $supervisorConfPath = '/etc/supervisor/conf.d/mailpit.conf';
    $caddyFilePath = '/etc/caddy/mailpit.Caddyfile';

    // Install binary
    if (! test("[ -f $mailpitBin ]")) {
        info('Installing Mailpit...');
        run('sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)');
    } else {
        info('Mailpit already installed — skipping binary install.');
    }

    // Supervisor config
    if (! test("[ -f $supervisorConfPath ]")) {
        info('Creating Mailpit Supervisor config...');
        writeSupervisorConf($supervisorConfPath, 'mailpit', '/usr/local/bin/mailpit');
    }

    // Caddy reverse proxy with basic auth
    if (! test("grep -q 'import $caddyFilePath' /etc/caddy/Caddyfile")) {
        info('Configuring Caddy reverse proxy for Mailpit...');

        $basicAuthUser = get('mailpit_user', null) ?? ask('Mailpit basic auth username:');
        $basicAuthPassword = get('mailpit_password', null) ?? askHiddenResponse('Mailpit basic auth password:');
        $hashedPassword = runMailpitHashPassword($basicAuthPassword);

        $hostname = get('hostname');
        writeCaddyConf($caddyFilePath, "mailpit.$hostname", get('mailpit_port', 8025), $basicAuthUser, $hashedPassword);

        run("sudo sh -c 'echo \"import $caddyFilePath\" >> /etc/caddy/Caddyfile'");
        run('sudo service caddy reload');

        info("Mailpit available at https://mailpit.$hostname");
    } else {
        info('Mailpit Caddy config already present — skipping.');
    }
})->verbose();

// ── Basic auth (staging) ──────────────────────────────────────────────────────

task('provision:basic-auth', function () {
    if (! get('basic_auth_enabled', false)) {
        info('Basic auth disabled for this host — skipping.');

        return;
    }

    $hostname = get('hostname');
    $alias = currentHost()->getAlias();
    $caddyFilePath = "/etc/caddy/app-{$alias}.Caddyfile";

    if (! test("grep -q 'import $caddyFilePath' /etc/caddy/Caddyfile")) {
        info('Configuring Caddy basic auth for app...');

        $user = get('basic_auth_user', null) ?? ask('Basic auth username:');
        $password = get('basic_auth_password', null) ?? askHiddenResponse('Basic auth password:');
        $hashed = run('caddy hash-password -p '.escapeshellarg($password));

        $phpVersion = get('php_version', '8.3');
        $deployPath = get('deploy_path');

        writeAppCaddyConf($caddyFilePath, $hostname, $user, $hashed, $phpVersion, $deployPath);

        run("sudo sh -c 'echo \"import $caddyFilePath\" >> /etc/caddy/Caddyfile'");
        run('sudo service caddy reload');

        info("Basic auth configured for https://$hostname");
    } else {
        info('Basic auth Caddy config already present — skipping.');
    }
})->verbose();

// ── Horizon ───────────────────────────────────────────────────────────────────

task('provision:horizon', function () {
    // Each environment gets its own supervisor program so staging and production
    // can coexist on the same server without stomping each other's config.
    $alias = currentHost()->getAlias();
    $programName = "horizon-{$alias}";
    $supervisorConfPath = "/etc/supervisor/conf.d/{$programName}.conf";

    run('sudo apt-get install -y supervisor');

    if (! test("[ -f $supervisorConfPath ]")) {
        info("Creating Horizon Supervisor config ({$programName})...");
        $command = 'php {{current_path}}/artisan horizon';
        writeSupervisorConf($supervisorConfPath, $programName, $command);
    } else {
        info("Horizon Supervisor config ({$programName}) already present — skipping.");
    }

    info("Horizon configured as '{$programName}'. Make sure laravel/horizon is in your composer.json.");
})->verbose();

// ── Helpers ───────────────────────────────────────────────────────────────────

function writeSupervisorConf(string $path, string $name, string $command): void
{
    $conf = <<<CONF
        [program:$name]
        process_name=%(program_name)s
        command=$command
        autostart=true
        autorestart=true
        user=deployer
        redirect_stderr=true
        stdout_logfile=/var/log/supervisor/$name.log
        stopwaitsecs=3600
        CONF;

    writeRemoteFile($path, $conf, sudo: true);
}

function writeCaddyConf(string $path, string $domain, int $port, string $user, string $hashedPassword): void
{
    $conf = <<<CADDY
        $domain {
            reverse_proxy localhost:$port

            basicauth * {
                $user $hashedPassword
            }
        }
        CADDY;

    writeRemoteFile($path, $conf, sudo: false);
}

function writeAppCaddyConf(
    string $path,
    string $hostname,
    string $user,
    string $hashedPassword,
    string $phpVersion,
    string $deployPath,
): void {
    $socket = "unix//run/php/php{$phpVersion}-fpm.sock";

    $conf = <<<CADDY
        $hostname {
            basicauth * {
                $user $hashedPassword
            }

            encode gzip

            root * $deployPath/current/public
            php_fastcgi $socket
            file_server
        }
        CADDY;

    writeRemoteFile($path, $conf, sudo: false);
}

function writeRemoteFile(string $path, string $content, bool $sudo): void
{
    $escaped = escapeshellarg($content);
    $prefix = $sudo ? 'sudo ' : '';

    run("{$prefix}sh -c \"printf '%s' $escaped > $path\"");
}

function runMailpitHashPassword(string $password): string
{
    return run('caddy hash-password -p '.escapeshellarg($password));
}
