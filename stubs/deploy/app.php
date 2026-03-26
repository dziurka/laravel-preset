<?php

namespace Deployer;

// ── After vendors ─────────────────────────────────────────────────────────────

/**
 * Overrides the built-in deploy:env (recipe/deploy/env.php).
 * Copies .env.example → shared/.env only when .env is absent/empty,
 * then generates APP_KEY if one is not already set.
 */
task('deploy:env', [
    'deploy:env:copy',
    'deploy:env:key',
]);

task('deploy:env:copy', function () {
    $envExample = '{{release_or_current_path}}/.env.example';
    $sharedEnv  = '{{deploy_path}}/shared/.env';

    if (! test("[[ -f $envExample ]]")) {
        warning('.env.example not found — skipping env copy.');

        return;
    }

    if (test("[[ -s $sharedEnv ]]")) {
        info('.env already exists and is non-empty — skipping copy.');

        return;
    }

    run("cp $envExample $sharedEnv");
    info('.env created from .env.example. Remember to fill in the secrets!');
});

task('deploy:env:key', function () {
    $sharedEnv = '{{deploy_path}}/shared/.env';

    if (! test("[[ -f $sharedEnv ]]")) {
        warning('.env not found — skipping key:generate.');

        return;
    }

    $hasKey = test("[[ $(awk -F= '/^APP_KEY=(.+)$/ {print \$2}' $sharedEnv) ]]");

    if ($hasKey) {
        info('APP_KEY already set — skipping key:generate.');

        return;
    }

    run('cd {{release_or_current_path}} && php artisan key:generate --force');
    info('APP_KEY generated.');
});

// ── Frontend ──────────────────────────────────────────────────────────────────

task('app:frontend', function () {
    run('cd {{release_or_current_path}} && npm run build');
});

// ── Post-deploy services ──────────────────────────────────────────────────────

task('deploy:horizon', function () {
    run('cd {{release_or_current_path}} && php artisan horizon:install');
    run('cd {{release_or_current_path}} && php artisan horizon:terminate');
    info('Horizon reloaded.');
});

task('deploy:supervisor', function () {
    $services = implode(' ', get('supervisor_services', ['horizon', 'mailpit']));
    run('sudo supervisorctl reread');
    run('sudo supervisorctl update');
    run("sudo supervisorctl restart $services");
    info('Supervisor services restarted.');
});


