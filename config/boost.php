<?php

$isLocalEnvironment = env('APP_ENV', 'production') === 'local';
$hasProductionBuild = is_file(public_path('build/.production'));

return [
    'enabled' => $isLocalEnvironment
        && ! $hasProductionBuild
        && filter_var(env('BOOST_ENABLED', true), FILTER_VALIDATE_BOOL),
    'browser_logs_watcher' => $isLocalEnvironment
        && ! $hasProductionBuild
        && filter_var(env('BOOST_BROWSER_LOGS_WATCHER', true), FILTER_VALIDATE_BOOL),
];
