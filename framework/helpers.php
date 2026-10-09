<?php

use Simple\Application;
use Simple\Http\HttpException;
use Simple\Support\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('app')) {
    /**
     * app() returns the application (the container); app(Foo::class) builds/fetches a Foo.
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $app = Application::getInstance();

        return $abstract === null ? $app : $app->make($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return Application::getInstance()->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('fake')) {
    function fake(): \Simple\Support\Fake
    {
        return \Simple\Support\Fake::instance();
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = '', mixed $errors = null): never
    {
        throw new HttpException($status, $message, $errors);
    }
}
