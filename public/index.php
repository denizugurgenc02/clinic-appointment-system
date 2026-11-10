<?php

declare(strict_types=1);

/*
 * Tek giriş noktası (Front Controller). Tüm istekler .htaccess ile buraya gelir.
 */

use App\Core\Application;
use App\Core\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Application(dirname(__DIR__)))
    ->handle(Request::fromGlobals())
    ->send();
