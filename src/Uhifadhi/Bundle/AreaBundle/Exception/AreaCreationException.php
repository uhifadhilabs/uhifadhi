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
 * THE AREA COULD NOT BE CREATED, AND THE MESSAGE IS FOR THE PERSON CREATING IT.
 *
 * The one thing an area cannot be created without is a name, so this is what a
 * blank one raises. Like {@see BoundaryImportException} it carries a sentence
 * the create screen prints back on the form the name was typed on, and it is a
 * type of its own for the same reason: the screen catches THIS and shows it, and
 * lets anything else become a 500, because a bug is not advice.
 */
final class AreaCreationException extends \RuntimeException
{
}
