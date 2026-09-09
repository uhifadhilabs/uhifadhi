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
 * THE IMPORT REFUSED THE FILE, AND THE MESSAGE IS FOR THE PERSON HOLDING IT.
 *
 * Every message this carries is printed on the form the upload came from, so it
 * is written as a sentence somebody importing a boundary can act on — "no
 * polygon boundary found in the file" rather than a type name and a line number.
 * That is why it is a type of its own rather than a bare \RuntimeException: the
 * screen catches THIS and shows it, and lets anything else become a 500, because
 * a bug in the geometry stack is not advice.
 */
final class BoundaryImportException extends \RuntimeException
{
}
