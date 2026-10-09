<?php

/*
 * Works both standalone (framework/vendor) and inside an app that installs the
 * framework as a path package (../vendor, the app's autoloader).
 */
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $autoload) {
    if (is_file($autoload)) {
        $loader = require $autoload;
        $loader->addPsr4('Simple\\Tests\\', __DIR__ . '/');
        return;
    }
}

fwrite(STDERR, "Run `composer install` first.\n");
exit(1);
