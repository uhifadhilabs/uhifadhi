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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Integration\Fixtures;

use Symfony\Component\HttpFoundation\Response;

/**
 * A PAGE THAT ALWAYS ANSWERS 200 — every route in {@see RoutedHostKernel} ends
 * here, so a 404 in that suite can only have come from the gate.
 *
 * It is a stand-in for a module's controller and for a host's, both: the gate's
 * whole claim is that it decides before the controller runs, and the cheapest
 * way to hold it to that is a controller that cannot fail on its own.
 */
final class GatedPage
{
    public function __invoke(): Response
    {
        return new Response('the page rendered');
    }
}
