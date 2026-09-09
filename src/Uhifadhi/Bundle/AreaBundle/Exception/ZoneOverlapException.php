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

namespace Uhifadhi\Bundle\AreaBundle\Exception;

use Uhifadhi\Bundle\AreaBundle\Entity\Zone;

/**
 * The zone invariant was broken: the geometry would share interior with a
 * sibling zone. The message NAMES the conflicting zone, because "it overlaps
 * something" is useless to the admin drawing it — one of the two has to be
 * fixed, and they need to know which other one.
 */
final class ZoneOverlapException extends \RuntimeException
{
    public static function between(string $name, Zone $conflicting): self
    {
        return self::betweenNames($name, $conflicting->getName() ?? '(unnamed)');
    }

    /** For an import, where the thing collided with is a feature in the file rather than a row. */
    public static function betweenNames(string $name, string $conflictingName): self
    {
        return new self(\sprintf(
            'Zone "%s" overlaps zone "%s" — zones of one area may touch along an edge or leave gaps, but never share interior.',
            $name,
            $conflictingName,
        ));
    }
}
