<?php

namespace Deployer;

require_once __DIR__.'/provision.php';
require_once 'app.php';

before('provision', 'app:before_provision');
after('provision', 'app:after_provision');

task('app:before_provision', [
    'app:no_passwd',
]);

task('app:after_provision', [
    'app:install:packages',
    'app:install:yarn',
    'app:install:redis',
    'app:install:mailpit',
    'app:setup-permissions',
    'app:configure:horizon',
    'app:prepare-for-github',

    // optional
    // 'app:mysql-native-password',
]);

task('app:no_passwd', function () {
    $sudoersFile = '/etc/sudoers.d/100-deployer-users';

    run("[ -f $sudoersFile ] || sudo touch $sudoersFile");
    run("sudo sh -c 'echo \"deployer ALL=(ALL) NOPASSWD:ALL\" > $sudoersFile'");
});

task('app:install:packages', function () {
    $packages = [
        'php8.2-tidy',
        'php8.2-redis',
        'xvfb unzip',
        'micro',
    ];
    run(sprintf('sudo apt-get install -y %s', implode(' ', $packages)));
});

task('app:install:yarn', function () {
    run(implode(' && ', [
        'curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | sudo apt-key add -',
        'echo "deb https://dl.yarnpkg.com/debian/ stable main" | sudo tee /etc/apt/sources.list.d/yarn.list',
        'sudo apt update',
        'sudo apt install -y yarn',
    ]));
});

task('app:install:redis', function () {
    run(implode(' && ', [
        'sudo apt install -y lsb-release curl gpg',
        'curl -fsSL https://packages.redis.io/gpg | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg',
        'echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] https://packages.redis.io/deb $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/redis.list',
        'sudo apt update',
        'sudo apt install -y redis',
    ]));
});

task('app:install:mailpit', function () {
    $mailpitPath = '/usr/local/bin/mailpit';
    if (! test("[ -f $mailpitPath ]")) {
        run('sudo bash < <(curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh)');
    }
    $supervisorConfPath = '/etc/supervisor/conf.d/mailpit.conf';
    $supervisorConf = '[program:mailpit]
process_name=%(program_name)s
command=/usr/local/bin/mailpit
autostart=true
autorestart=true
user=deployer
redirect_stderr=true
stdout_logfile={{current_path}}/mailpit.log
stopwaitsecs=3600';
    if (! test("[ -f $supervisorConfPath ]")) {
        run("sudo sh -c \"echo '$supervisorConf' > $supervisorConfPath\"");
    }
    $caddyFilePath = '/etc/caddy/mailpit.Caddyfile';
    set('remote_user', 'root');
    if (! test("grep -q 'import $caddyFilePath' /etc/caddy/Caddyfile")) {
        $basicAuthUser = ask('[MAILPIT] Basic auth user:');
        $basicAuthPassword = ask('[MAILPIT] Basic auth password:');
        $password = run("caddy hash-password -p '$basicAuthPassword'");
        $caddyFile = "mailpit.{{hostname}} {
	reverse_proxy localhost:8025

	basicauth * {
        $basicAuthUser $password
    }
}";
        run("echo '$caddyFile' > $caddyFilePath");
        run("echo 'import $caddyFilePath' >> /etc/caddy/Caddyfile");
    }
    run('service caddy reload');
    set('remote_user', 'deployer');
})->verbose();

task('app:configure:horizon', function () {
    run('sudo apt install supervisor -y');
    $supervisorConfPath = '/etc/supervisor/conf.d/horizon.conf';
    $supervisorConf = '[program:horizon]
process_name=%(program_name)s
command=php {{current_path}}/artisan horizon
autostart=true
autorestart=true
user=deployer
redirect_stderr=true
stdout_logfile={{current_path}}/horizon.log
stopwaitsecs=3600';
    if (! test("[ -f $supervisorConfPath ]")) {
        run("sudo sh -c \"echo '$supervisorConf' > $supervisorConfPath\"");
    }
})->verbose();

task('app:prepare-for-github', function () {
    $deployerSshPath = '/home/deployer/.ssh/deployer_rsa';
    $deployerSshPubPath = $deployerSshPath.'.pub';
    $authorizedKeysPath = '/home/deployer/.ssh/authorized_keys';
    // generate deployer_rsa if not exists
    run("[ -f $deployerSshPath ] || ssh-keygen -t rsa -N '' -f $deployerSshPath <<< y");
    run('sudo chown -R deployer:deployer /home/deployer/.ssh/*');
    run('sudo chmod -R 0700 /home/deployer/.ssh/*');
    // add deployer_rsa.pub to authorized_keys
    $deployerSshPubContent = run("cat $deployerSshPubPath");
    $authorizedKeysContent = run("cat $authorizedKeysPath");
    if (! str_contains($authorizedKeysContent, $deployerSshPubContent)) {
        run("cat $deployerSshPubPath >> $authorizedKeysPath");
    }

    warning('*** GITHUB CONFIGURATION ***');
    warning('Copy the below public key to deploys keys:');
    run('cat /home/deployer/.ssh/id_rsa.pub');
    warning('Copy the below private key to secrets.SSH_KEY_XYZ');
    run("echo 'cat $deployerSshPath'");
    warning('Copy the below text to secrets.KNOWN_HOSTS_XYZ');
    run('ssh-keyscan -t rsa {{hostname}}');
    warning('*** END OF GITHUB CONFIGURATION ***');
})->verbose();

task('app:mysql-native-password', function () {
    run("mysql --user=\"root\" -e \"CREATE USER IF NOT EXISTS '{{db_user}}'@'localhost' IDENTIFIED BY '%secret%';\"", ['secret' => get('db_password')]);
    run("mysql --user=\"root\" -e \"GRANT ALL PRIVILEGES ON *.* TO '{{db_user}}'@'localhost' WITH GRANT OPTION;\"");
    run("mysql --user=\"root\" -e \"ALTER USER '{{db_user}}'@'0.0.0.0' IDENTIFIED WITH mysql_native_password;\"");
    run("mysql --user=\"root\" -e \"ALTER USER '{{db_user}}'@'%' IDENTIFIED WITH mysql_native_password;\"");
    run("mysql --user=\"root\" -e \"ALTER USER '{{db_user}}'@'localhost' IDENTIFIED WITH mysql_native_password;\"");
    run('mysql --user="root" -e "FLUSH PRIVILEGES;"');
});

task('app:setup-permissions', function () {
    run('sudo chown -R deployer:deployer {{deploy_path}}');
});
