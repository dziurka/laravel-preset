<?php

namespace Deployer;

// ── Sudoers ───────────────────────────────────────────────────────────────────

task('provision:sudoers', function () {
    $sudoersFile = '/etc/sudoers.d/100-deployer-users';

    run("[ -f $sudoersFile ] || sudo touch $sudoersFile");
    run("sudo sh -c 'echo \"deployer ALL=(ALL) NOPASSWD:ALL\" > $sudoersFile'");

    info('Passwordless sudo configured for deployer.');
});

// ── System packages ───────────────────────────────────────────────────────────

task('provision:packages', function () {
    $phpVersion  = get('php_version', '8.3');
    $dbExtension = get('db_driver', 'mysql') === 'pgsql'
        ? "php{$phpVersion}-pgsql"
        : "php{$phpVersion}-mysql";

    $packages = [
        "php{$phpVersion}-tidy",
        "php{$phpVersion}-redis",
        $dbExtension,
        'xvfb',
        'unzip',
        'micro',
    ];

    run('sudo apt-get update -q');
    run('sudo apt-get install -y '.implode(' ', $packages));

    info("System packages installed (PHP {$phpVersion}).");
});

// ── Yarn ──────────────────────────────────────────────────────────────────────

task('provision:yarn', function () {
    if (test('command -v yarn &>/dev/null')) {
        info('Yarn already installed — skipping.');

        return;
    }

    // Modern keyring-based installation (Ubuntu 22.04+)
    run(implode(' && ', [
        'sudo apt-get install -y curl gpg',
        'curl -fsSL https://dl.yarnpkg.com/debian/pubkey.gpg | sudo gpg --dearmor -o /usr/share/keyrings/yarn-archive-keyring.gpg',
        'echo "deb [signed-by=/usr/share/keyrings/yarn-archive-keyring.gpg] https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list',
        'sudo apt-get update -q',
        'sudo apt-get install -y yarn',
    ]));

    info('Yarn installed.');
});

// ── Valkey ────────────────────────────────────────────────────────────────────

task('provision:valkey', function () {
    if (test('command -v valkey-server &>/dev/null')) {
        info('Valkey already installed — skipping.');

        return;
    }

    run(implode(' && ', [
        'sudo apt-get install -y lsb-release curl gpg',
        'curl -fsSL https://packages.valkey.io/valkey/gpg | sudo gpg --dearmor -o /usr/share/keyrings/valkey-archive-keyring.gpg',
        'echo "deb [signed-by=/usr/share/keyrings/valkey-archive-keyring.gpg] https://packages.valkey.io/valkey $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/valkey.list',
        'sudo apt-get update -q',
        'sudo apt-get install -y valkey',
    ]));

    info('Valkey installed.');
});

// ── Permissions ───────────────────────────────────────────────────────────────

task('provision:permissions', function () {
    run('sudo chown -R deployer:deployer {{deploy_path}}');
    info('Permissions set for {{deploy_path}}.');
});

