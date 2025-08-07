<?php
namespace Deployer;

require 'recipe/laravel.php';

// Config

set('application', 'rsp-ecommerce');
set('repository', 'https://github.com/D3stan/rsp-ecom.git');
set('git_tty', true);

add('shared_files', ['.env']);
add('shared_dirs', ['storage', 'public/build']);
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

task('upload:build', function () {
    upload('public/build', '{{release_path}}/public');
});
before('deploy:symlink', 'upload:build');

// Laravel options

set('keep_releases', 3);
set('default_stage', 'production');