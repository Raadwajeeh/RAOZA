<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$domainRoot = realpath(__DIR__.'/..');
$releasesRoot = realpath(__DIR__.'/../releases');
$applicationRoot = realpath(__DIR__.'/../raoza-app');

if ($domainRoot === false
    || $releasesRoot === false
    || $applicationRoot === false
    || ! str_starts_with($applicationRoot, $releasesRoot.DIRECTORY_SEPARATOR)
    || ! is_file($applicationRoot.'/vendor/autoload.php')
    || ! is_file($applicationRoot.'/bootstrap/app.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Application release is unavailable.');
}

if (is_file($maintenance = $applicationRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $applicationRoot.'/vendor/autoload.php';

(require $applicationRoot.'/bootstrap/app.php')
    ->handleRequest(Request::capture());
