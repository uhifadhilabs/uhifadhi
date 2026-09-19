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

namespace Uhifadhi\Bundle\TeamBundle\Exception;

/**
 * TWO KINDS BY ONE NAME IS ONE KIND ENTERED TWICE.
 *
 * Thrown rather than returned as a false, because every caller has to handle
 * it and a boolean is the one shape a caller can forget to look at. The name
 * travels with it so the screen can say which word it was.
 */
final class DuplicateDepartmentKindException extends \RuntimeException
{
    public function __construct(public readonly string $name)
    {
        parent::__construct(\sprintf('There is already a department kind called "%s".', $name));
    }
}
