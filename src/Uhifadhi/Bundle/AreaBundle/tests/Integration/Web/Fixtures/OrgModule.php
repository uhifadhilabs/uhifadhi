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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures;

use Uhifadhi\Contracts\Shell\OrgPage;
use Uhifadhi\Contracts\Shell\OrgPagesInterface;

/**
 * A MODULE THAT ANSWERS AT ORGANISATION LEVEL — the stand-in contributor.
 *
 * It impersonates nobody: it is an ordinary implementation of a published
 * interface, here because what is under test is that an installation gets
 * the scope control on a contributed organisation page WITHOUT the host
 * wiring anything. The slug is invented on purpose — a default that only
 * worked for the modules that exist today would be a hardcoded list with
 * extra steps.
 */
final class OrgModule implements OrgPagesInterface
{
    public function orgPages(): array
    {
        return [
            new OrgPage('overview', 'Overview', 'test_org_overview'),
            new OrgPage('today', 'Today', 'test_org_today'),
        ];
    }

    public function name(): string
    {
        return 'Sightings';
    }

    public function icon(): string
    {
        return 'shell:map';
    }
}
