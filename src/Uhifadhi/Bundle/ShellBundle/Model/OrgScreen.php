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

namespace Uhifadhi\Bundle\ShellBundle\Model;

use Uhifadhi\Contracts\Shell\OrgPage;

/**
 * ONE SCREEN OF A MODULE'S ORGANISATION-LEVEL SET, AS MOUNTED — the module's
 * declaration plus the two things only the application can say: the address
 * the route resolved to, and whether this is the screen the viewer is on.
 *
 * A SCREEN THAT IS NOT MOUNTED IS NOT ONE OF THESE. The url is non-nullable
 * for the same reason a tab's is: there is no inert form to draw by accident.
 */
final readonly class OrgScreen
{
    public function __construct(
        public OrgPage $page,
        public string $url,
        public bool $current,
    ) {
    }
}
