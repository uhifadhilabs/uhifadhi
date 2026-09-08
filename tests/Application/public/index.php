<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;
use Uhifadhi\Core\Tests\Application\Kernel;

require dirname(__DIR__, 3).'/vendor/autoload.php';

/*
 * The throwaway application's front controller. It is here so a browser-driven
 * check of the core can be pointed at a real document root; the suite itself
 * goes through the kernel.
 */
$kernel = new Kernel('test', true);
$response = $kernel->handle($request = Request::createFromGlobals());
$response->send();
$kernel->terminate($request, $response);
