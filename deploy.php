<?php

namespace Deployer;

/*
 * Zero-downtime deploys to the side-project droplet (shared with Eat Well Planner, PonderNest, WhatDrainsMe and
 * Next Finish Line), copied from Next Finish Line's.
 *
 * Normally run by GitHub Actions on every push to master (.github/workflows/ci.yml), which runs the tests, builds
 * the release (composer --no-dev, npm run build, then production-only node_modules) and then runs `dep deploy`.
 * The server never runs composer or npm: CI uploads the finished build, so a deploy can't fail halfway on a
 * package download and a Vite build can't eat the memory the other sites on the box need.
 *
 * The server itself is provisioned with Ansible from the eatwellplannerserver repo (inventories/prod). Until
 * November 2026 this site deployed with Capistrano from there (capistrano-spanishclassprintables/, in its git
 * history); Deployer took over the same releases/ and shared/storage layout.
 */

require 'recipe/laravel.php';

set('application', 'spanishclassprintables');
set('deploy_path', '/var/www/spanishclassprintables');
set('keep_releases', 3);

// PHP-FPM, the queue worker and the scheduler run as `web`; deploys log in as `github`, a user with no sudo.
// ACLs let both write to storage/ and bootstrap/cache.
set('http_user', 'web');
set('writable_mode', 'acl');

// storage/ is shared between releases (the recipe's default) but holds nothing durable: the teacher-game PDFs in
// app/private are a render cache rebuilt on demand, and the pin PNGs in app/public are swept after 14 days.
// Everything that matters is in the database, backed up nightly (routes/console.php).
//
// .env isn't shared: each release decrypts its own from the committed .env.production.encrypted (deploy:env).
set('shared_files', []);

host('production')
    ->setHostname(getenv('DEPLOY_HOST') ?: '129.212.132.179')
    ->setRemoteUser('github')
    ->set('labels', ['stage' => 'production']);

desc('Uploads the release CI built (vendor/, node_modules/ and public/build/ included)');
task('deploy:update_code', function () {
    // Each release starts as an empty folder, so without help rsync would send the whole app every time.
    // --copy-dest makes it copy files that are unchanged since the previous release from that release on the
    // server, and send only the rest. --checksum compares contents rather than timestamps, because CI checks out
    // and installs everything fresh, so every timestamp differs. Files are copied, not hard-linked, so nothing
    // written later in one release can change another.
    $reuse = has('previous_release')
        ? ['--checksum', '--copy-dest={{previous_release}}']
        : [];

    // node_modules/ is uploaded, unlike in the other sites: Browsershot needs puppeteer at runtime to drive the
    // Chrome that Ansible installs. CI prunes it to production packages, and .npmrc keeps puppeteer from
    // downloading a Chromium of its own.
    upload(__DIR__.'/', '{{release_path}}', [
        'options' => [
            ...$reuse,
            '--exclude=/.git',
            '--exclude=/.github',
            '--exclude=/.ai',
            '--exclude=/.claude',
            '--exclude=/.cursor',
            '--exclude=/.idea',
            '--exclude=/.junie',
            '--exclude=/docs',
            '--exclude=/tests',
            '--exclude=/.env',
            '--exclude=/.env.backup',
            '--exclude=/.env.production',
            '--exclude=/.env.development',
            '--exclude=/public/hot',
            '--exclude=/public/storage',
            '--exclude=/storage/logs/*.log',
            '--exclude=/database/*.sqlite',
            '--exclude=/.phpunit.*',
        ],
    ]);
});

desc('vendor/ is installed in CI with --no-dev, so there is nothing to do here');
task('deploy:vendors', function () {});

desc('Decrypts the production .env into the release');
task('deploy:env', function () {
    $key = getenv('LARAVEL_ENV_ENCRYPTION_KEY');

    if (! $key) {
        throw new \RuntimeException('LARAVEL_ENV_ENCRYPTION_KEY is not set. Deploying without it would leave the release with no .env.');
    }

    run(
        'cd {{release_path}} && {{bin/php}} artisan env:decrypt --env=production --filename=.env --force --key=%key%',
        secrets: ['key' => $key],
    );
});

desc('Backs up the database to Google Drive when there are migrations to run');
task('deploy:backup_before_migrate', function () {
    // `migrate:status --pending=1` exits with 1 while any migration is pending. The first deploy has no database
    // tables yet, so nothing to back up.
    if (test('{{bin/php}} {{release_path}}/artisan migrate:status --pending=1 > /dev/null')
        || ! test('{{bin/php}} {{release_path}}/artisan migrate:status > /dev/null')) {
        return;
    }
    // The nightly backup can be up to a day old; a copy from right before the change is cheap insurance
    run('{{bin/php}} {{release_path}}/artisan backup:run --only-db --disable-notifications', timeout: 900);
});

// Before the migrations, so nothing has changed when it stops the deploy: the live release keeps running.
before('artisan:migrate', 'deploy:backup_before_migrate');

desc('Starts the queue worker if it is down');
task('workers:start', function () {
    // `artisan reload` only reaches a worker that is running. One that supervisor gave up on (FATAL) stays down
    // through every deploy. `start group:*` skips a running worker, so it never cuts off a render. The server's
    // sudoers allows this command and nothing else (site_deploy_supervisor_groups in the server repo). A failure
    // here warns rather than fails, because the release is already live by now.
    $group = 'spanishclassprintables-laravel-worker';

    if (! test("sudo -n /usr/local/bin/supervisorctl -c /etc/supervisor/supervisord.conf start '{$group}:*'")) {
        warning("Couldn't start {$group}: check `sudo supervisorctl status` on the server");
    }
});

// Laravel's `artisan reload` (part of the recipe's deploy task) restarts the queue worker after the symlink
// switches, so it picks up the new code; supervisor starts it again. The recipe's rollback only moves the symlink,
// so it does the same, or the worker would keep running the bad release.
after('rollback', 'artisan:reload');

after('deploy', 'workers:start');
after('rollback', 'workers:start');

after('deploy:failed', 'deploy:unlock');
