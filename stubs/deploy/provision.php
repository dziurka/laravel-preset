<?php

namespace Deployer;

require_once __DIR__.'/provision/system.php';
require_once __DIR__.'/provision/services.php';
require_once __DIR__.'/provision/github.php';

before('provision', 'provision:before');
after('provision', 'provision:after');

task('provision:before', [
    'provision:sudoers',
]);

task('provision:after', [
    'provision:packages',
    'provision:yarn',
    'provision:redis',
    'provision:mailpit',
    'provision:basic-auth',
    'provision:permissions',
    'provision:horizon',
    'provision:github',
    'provision:github-secrets',
]);

// Uncomment to configure MySQL native password authentication:
// after('provision:after', 'provision:mysql');

