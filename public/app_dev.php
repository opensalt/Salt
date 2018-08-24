<?php

use App\Kernel;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$_SERVER['APP_ENV'] = 'dev';

// If you don't want to setup permissions the proper way, just uncomment the following PHP line
// read http://symfony.com/doc/current/book/installation.html#checking-symfony-application-configuration-and-setup
// for more information
umask(0000);

// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
if (isset($_SERVER['HTTP_CLIENT_IP'])
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || !(in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', 'fe80::1', '::1']) || php_sapi_name() === 'cli-server')
) {
    // Check if dev environment allowed
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.99.99.99';
    if (!empty($_ENV['ALLOW_LOCAL_DEV'] ?? null)
        && 'false' !== $_ENV['ALLOW_LOCAL_DEV']
        && (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE))
    ) {
        // Internal network, allow if ALLOW_LOCAL_DEV set
    } elseif (!empty($_ENV['ALLOW_EXTERNAL_DEV_IPS'] ?? null)
        && in_array($ip, explode(',', preg_replace('/ /', '', $_ENV['ALLOW_EXTERNAL_DEV_IPS'])))
    ) {
        // Specific external IPs allowed if ALLOW_EXTERNAL_DEV_IPS set
    } elseif (empty($_COOKIE['dev'] ?? null)
        || empty($_ENV['DEV_COOKIE'] ?? null)
        || $_COOKIE['dev'] !== $_ENV['DEV_COOKIE']
    ) {
        header('HTTP/1.0 403 Forbidden');
        exit('You are not allowed to access this file.');
    }
}

require __DIR__.'/../vendor/autoload.php';

/*
// The check is to ensure we don't use .env in production
if (!isset($_SERVER['APP_ENV'])) {
    if (!class_exists(Dotenv::class)) {
        throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
    }
    (new Dotenv())->load(__DIR__.'/../.env');
}
*/

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? ('prod' !== $env));

if ($debug) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

$kernel = new Kernel($env, $debug);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
