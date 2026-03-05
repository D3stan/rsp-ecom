<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

set('application', 'rsp-ecommerce');
set('repository', 'https://github.com/D3stan/rsp-ecom.git');
set('git_tty', true);

add('shared_files', ['.env']);
add('shared_dirs', ['storage']);
add('writable_dirs', ['storage', 'bootstrap/cache']);

// Hosts

host('production')
    ->set('hostname', '37.27.26.211')
    ->set('remote_user', 'deploy')
    ->set('deploy_path', '~/ecommerce')
    ->set('branch', 'main');

// Hooks

after('deploy:failed', 'deploy:unlock');

// Tasks

task('build:local', function () {
    runLocally('npm ci');
    runLocally('npm run build');
});

task('upload:build', function () {
    upload('public/build', '{{release_path}}/public');
});
before('upload:build', 'build:local');
before('deploy:symlink', 'upload:build');

task('laravel:restart', function () {
    run('sudo systemctl restart laravel.service');
});

task('laravel:optimize', function () {
    run('cd {{release_path}} && php artisan config:cache');
    run('cd {{release_path}} && php artisan route:cache');
    run('cd {{release_path}} && php artisan view:cache');
});

task('laravel:migrate', function () {
    run('cd {{release_path}} && php artisan migrate --force');
});

task('laravel:generate-sitemap', function () {
    run('cd {{release_path}} && php artisan generate:sitemap-index');
});

task('laravel:clear', function () {
    run('cd {{release_path}} && php artisan app:clear-all-cache');
});

after('deploy:shared', 'deploy:vendors');         // Install composer dependencies
after('deploy:vendors', 'laravel:optimize');      // Run Laravel cache commands
after('deploy:vendors', 'laravel:migrate');       // Run migrations (optional)
after('deploy:unlock', 'laravel:restart');        // Restart app
after('laravel:restart', 'laravel:generate-sitemap'); // Generate sitemap after deployment
after('laravel:restart', 'laravel:clear'); // Clear Laravel cache after deployment

// Laravel options

set('keep_releases', 3);
set('default_stage', 'production');