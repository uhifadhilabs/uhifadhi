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

namespace Uhifadhi\Bundle\AreaBundle\Access;

use Uhifadhi\Contracts\ModulePermission;
use Uhifadhi\Contracts\PermissionDeclarationInterface;

/**
 * WHAT THE AREA ENFORCES THAT THE HOST'S OWN ENUM DOES NOT NAME.
 *
 * Reading an area, editing it, creating and deleting one are the
 * platform's own words and live in the host's catalogue. CHECKING IN is
 * the area's: the ground, the posts and the day a ranger stands on one
 * are this bundle's, the endpoints are this bundle's, and whoever
 * enforces a permission is who declares it.
 *
 * IT IS A HANDSET PERMISSION. Nobody checks in from a desk: the value
 * reaches the phone in the token, and the phone shows the Duty tab
 * because of it. An account without it is an account that reads the
 * park and does not report a day.
 */
final readonly class AreaPermissions implements PermissionDeclarationInterface
{
    /** The one value, spelt once, so a gate and a test cannot disagree. */
    public const string CHECK_IN = 'duty.checkin';

    public function permissions(): array
    {
        return [
            new ModulePermission(
                self::CHECK_IN,
                'Duty',
                'Check in',
                'Report the day from a handset — the status, the post and the positions that go with it.',
            ),
        ];
    }
}
