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

namespace Uhifadhi\Bundle\ShellBundle\Tests\Integration\Fixtures;

use Symfony\Component\HttpFoundation\Response;

/**
 * The address a contributed organization-level page is mounted at — just
 * enough of one for a route to exist, because the sidebar generates a url
 * from a route name and a name nobody mounted is a link to a 404.
 */
final class FixtureOrgController
{
    public function __invoke(): Response
    {
        return new Response('an organization-level page');
    }
}
