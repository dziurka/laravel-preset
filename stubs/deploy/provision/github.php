<?php

namespace Deployer;

// ── GitHub SSH setup ──────────────────────────────────────────────────────────

task('provision:github', function () {
    $sshDir = '/home/deployer/.ssh';
    $deployKeyPath = "$sshDir/deployer_rsa";
    $deployKeyPubPath = "$deployKeyPath.pub";
    $authorizedKeysPath = "$sshDir/authorized_keys";

    // Generate deploy key if missing
    if (! test("[ -f $deployKeyPath ]")) {
        info('Generating deploy SSH key...');
        run("ssh-keygen -t ed25519 -N '' -f $deployKeyPath -C 'deployer@{{hostname}}'");
    }

    // Fix permissions
    run("sudo chown -R deployer:deployer $sshDir");
    run("sudo chmod 700 $sshDir");
    run("sudo chmod 600 $sshDir/*");

    // Add deploy key to authorized_keys (enables Deployer to SSH to itself for rollbacks)
    $pubKey = run("cat $deployKeyPubPath");
    $authKeys = test("[ -f $authorizedKeysPath ]") ? run("cat $authorizedKeysPath") : '';

    if (! str_contains($authKeys, $pubKey)) {
        run("echo '$pubKey' >> $authorizedKeysPath");
        info('Deploy key added to authorized_keys.');
    }

    // Print keys for manual configuration (fallback if gh CLI is not available)
    writeln('');
    writeln('<comment>════════════════════════════════════════════════</comment>');
    writeln('<comment>  GITHUB CONFIGURATION — copy these values</comment>');
    writeln('<comment>════════════════════════════════════════════════</comment>');

    writeln('');
    writeln('<info>1. Deploy Key</info> → GitHub → Settings → Deploy keys → Add deploy key');
    writeln(run("cat $deployKeyPubPath"));

    $alias = currentHost()->getAlias();
    $suffix = strtoupper($alias);

    writeln('');
    writeln("<info>2. Private Key</info> → GitHub → Settings → Secrets → Actions → SSH_KEY_{$suffix}");
    writeln(run("cat $deployKeyPath"));

    writeln('');
    writeln("<info>3. Known Hosts</info> → GitHub → Settings → Secrets → Actions → KNOWN_HOSTS_{$suffix}");
    writeln(run('ssh-keyscan -t ed25519 {{hostname}} 2>/dev/null'));

    writeln('');
    writeln('<comment>💡 Run  dep provision:github-secrets '.$alias.'  to set these automatically via gh CLI.</comment>');
    writeln('<comment>════════════════════════════════════════════════</comment>');
})->verbose();

// ── Automated GitHub Secrets via gh CLI ───────────────────────────────────────

/**
 * Reads SSH keys from the remote server and sets GitHub Secrets + Deploy Key
 * automatically using the gh CLI. Requires `gh auth login` to have been run first.
 *
 * Usage: dep provision:github-secrets staging
 *        dep provision:github-secrets production
 */
task('provision:github-secrets', function () {
    $sshDir = '/home/deployer/.ssh';
    $deployKeyPath = "$sshDir/deployer_rsa";
    $deployKeyPubPath = "$deployKeyPath.pub";

    if (! test("[ -f $deployKeyPath ]")) {
        throw new \RuntimeException('Deploy key not found — run  dep provision  first.');
    }

    $alias = currentHost()->getAlias();
    $suffix = strtoupper($alias);
    $hostname = get('hostname');

    // Ask before touching anything — keys are sensitive
    if (! askConfirmation("Configure GitHub for [{$alias}] (set SSH_KEY_{$suffix}, KNOWN_HOSTS_{$suffix} and deploy key)?")) {
        info('Skipped.');

        return;
    }

    $privateKey = run("cat $deployKeyPath");
    $pubKey = run("cat $deployKeyPubPath");
    $knownHosts = run("ssh-keyscan -t ed25519 $hostname 2>/dev/null");

    // Check if gh CLI is available locally
    $ghPath = trim(runLocally('command -v gh 2>/dev/null || echo ""'));

    if (! $ghPath) {
        writeln('');
        writeln('<comment>gh CLI not found.</comment>');

        if (askConfirmation('Install gh CLI now via webi.sh?')) {
            runLocally('curl -sS https://webi.sh/gh | bash');
            // Reload PATH so the freshly installed binary is visible
            $ghPath = trim(runLocally('source ~/.config/envman/PATH.env 2>/dev/null; command -v gh 2>/dev/null || echo ""'));
        }
    }

    if ($ghPath) {
        writeln('');
        writeln('<comment>Checking gh authentication status...</comment>');
        $authCheck = runLocally('gh auth status 2>&1 || true');

        if (! str_contains($authCheck, 'Logged in')) {
            writeln('');
            writeln('<comment>⚠️  gh CLI is installed but not authenticated.</comment>');
            writeln('<info>Please run the following command in your terminal and follow the prompts:</info>');
            writeln('');
            writeln('  gh auth login');
            writeln('');
            askConfirmation('Press Enter once you have successfully authenticated with gh…');
        }

        applySecretsViaGhCli($privateKey, $pubKey, $knownHosts, $suffix, $hostname, $alias);
    } else {
        printSecretsForManualSetup($privateKey, $pubKey, $knownHosts, $suffix);
    }
})->verbose();

function applySecretsViaGhCli(
    string $privateKey,
    string $pubKey,
    string $knownHosts,
    string $suffix,
    string $hostname,
    string $alias,
): void {
    info("Setting GitHub Secrets for {$alias} via gh CLI...");

    $tmpKey = tempnam(sys_get_temp_dir(), 'dep_key_');
    file_put_contents($tmpKey, $privateKey);
    runLocally("gh secret set SSH_KEY_{$suffix} < ".escapeshellarg($tmpKey));
    unlink($tmpKey);
    info("✓ SSH_KEY_{$suffix} set.");

    $tmpHosts = tempnam(sys_get_temp_dir(), 'dep_hosts_');
    file_put_contents($tmpHosts, $knownHosts);
    runLocally("gh secret set KNOWN_HOSTS_{$suffix} < ".escapeshellarg($tmpHosts));
    unlink($tmpHosts);
    info("✓ KNOWN_HOSTS_{$suffix} set.");

    $tmpPub = tempnam(sys_get_temp_dir(), 'dep_pub_');
    file_put_contents($tmpPub, $pubKey);
    $title = "deployer@{$hostname} ({$alias})";

    try {
        runLocally('gh repo deploy-key add '.escapeshellarg($tmpPub).' --title '.escapeshellarg($title));
        info('✓ Deploy key added to repository.');
    } catch (\Throwable $e) {
        warning("Deploy key may already exist: {$e->getMessage()}");
    } finally {
        unlink($tmpPub);
    }

    writeln('');
    writeln('<info>✅ GitHub configured for '.$alias.'! The pipeline is ready to deploy.</info>');
}

function printSecretsForManualSetup(
    string $privateKey,
    string $pubKey,
    string $knownHosts,
    string $suffix,
): void {
    if (! askConfirmation('gh CLI not available. Display secret values here for manual copy-paste?')) {
        writeln('<comment>Skipped. Run this task again when gh CLI is installed.</comment>');

        return;
    }

    writeln('');
    writeln('<comment>════════════════════════════════════════════════════════</comment>');
    writeln('<comment>  MANUAL SETUP — copy to GitHub Settings → Secrets → Actions</comment>');
    writeln('<comment>════════════════════════════════════════════════════════</comment>');
    writeln('');
    writeln("<info>SSH_KEY_{$suffix}</info> (Settings → Secrets → Actions → New secret):");
    writeln($privateKey);
    writeln('');
    writeln("<info>KNOWN_HOSTS_{$suffix}</info> (Settings → Secrets → Actions → New secret):");
    writeln($knownHosts);
    writeln('');
    writeln('<info>Deploy key</info> (Settings → Deploy keys → Add deploy key — read-only):');
    writeln($pubKey);
    writeln('');
    writeln('<comment>════════════════════════════════════════════════════════</comment>');
}

// ── MySQL native password (optional) ─────────────────────────────────────────

/**
 * Required config keys: db_user, db_password
 * Enables mysql_native_password auth — needed for some older clients.
 * Activate by uncommenting in provision.php.
 */
task('provision:mysql', function () {
    $user = get('db_user');
    $password = get('db_password');

    $queries = [
        "CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY '$password'",
        "GRANT ALL PRIVILEGES ON *.* TO '{$user}'@'localhost' WITH GRANT OPTION",
        "ALTER USER '{$user}'@'%'         IDENTIFIED WITH mysql_native_password BY '$password'",
        "ALTER USER '{$user}'@'localhost'  IDENTIFIED WITH mysql_native_password BY '$password'",
        'FLUSH PRIVILEGES',
    ];

    foreach ($queries as $query) {
        run("mysql --user=\"root\" -e \"$query\"");
    }

    info("MySQL native password configured for user '$user'.");
});
