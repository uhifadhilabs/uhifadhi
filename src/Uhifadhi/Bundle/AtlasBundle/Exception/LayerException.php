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

namespace Uhifadhi\Bundle\AtlasBundle\Exception;

/**
 * A layer that could not draw anything, refused where it was written.
 *
 * The alternative is a plate that renders, mounts, and silently shows nothing —
 * a fault with no message, found by a person looking at a map rather than by
 * the module's own test run.
 */
final class LayerException extends \InvalidArgumentException
{
}
