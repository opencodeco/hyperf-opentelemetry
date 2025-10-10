<?php

declare(strict_types=1);

error_reporting(E_ALL);

!defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));
!defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', SWOOLE_HOOK_ALL);

Swoole\Runtime::enableCoroutine(1);

require BASE_PATH . '/vendor/autoload.php';

Hyperf\Di\ClassLoader::init();