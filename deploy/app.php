<?php

namespace Deployer;

task('app:custom-config', function () {
    add('shared_files', ['.env']);
});

task('app:after-vendors', [
    'app:copy-env',
    'app:key-generate',
]);

task('app:copy-env', function () {
    if (! test('[[ -f {{release_or_current_path}}/.env.example ]]')
        || test('[[ -s {{deploy_path}}/shared/.env ]]')
    ) {
        return;
    }

    run('cp {{release_or_current_path}}/.env.example {{deploy_path}}/shared/.env');
});

task('app:key-generate', function () {
    if (! test('[[ -f {{deploy_path}}/shared/.env ]]')
        || test("[[ $(awk -F= '/^APP_KEY=(.*)$/ {print $2}' {{deploy_path}}/shared/.env) ]]")
    ) {
        return;
    }

    run('cd {{release_or_current_path}} && php artisan key:generate');
});
