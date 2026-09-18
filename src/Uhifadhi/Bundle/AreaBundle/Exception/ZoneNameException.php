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

/**
 * A ZONE CANNOT BE CALLED THAT.
 *
 * NAMES ARE UNIQUE WITHIN AN AREA and nowhere wider: two areas may each have a
 * "North", because a zone name is read beside the area it belongs to and never
 * alone. The unique index says the same thing in the database; this says it in
 * a sentence somebody can act on, before the index has to.
 *
 * A ZONE WITH NO NAME IS NOT A ZONE. The ground is what a zone is, but the name
 * is how every module refers to it — a blank one would render as a gap in every
 * filter and every legend in the product.
 */
final class ZoneNameException extends \RuntimeException
{
    public static function empty(): self
    {
        return new self('A zone needs a name: it is how every module refers to that ground.');
    }

    public static function alreadyUsed(string $name, string $areaName): self
    {
        return new self(\sprintf(
            '%s already has a zone called "%s". Zone names are unique within an area, so pick another one or rename that zone first.',
            '' === $areaName ? 'This area' : $areaName,
            $name,
        ));
    }
}
