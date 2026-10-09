<?php

/*
 * A minimal app used by the framework's own tests.
 */

$app = new Simple\Application(dirname(__DIR__));
$app->loadRoutes(dirname(__DIR__) . '/routes/api.php', ['prefix' => config('app.route_prefix', '')]);

return $app;
