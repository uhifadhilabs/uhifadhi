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

use Uhifadhi\Bundle\TeamBundle\Entity\User;

/**
 * The password offered is shorter than the one rule this installation has.
 *
 * Length is the only requirement — no character classes, no rotation — because
 * it is the one that survives contact with a person who has to type it on a
 * handset in the field. The minimum is {@see User::PASSWORD_MIN_LENGTH}, stated
 * on every card that asks for a password so nobody meets the rule only on
 * submit.
 */
final class PasswordTooShortException extends \DomainException
{
    public function __construct(public readonly int $minimum = User::PASSWORD_MIN_LENGTH)
    {
        parent::__construct(\sprintf('A password must be at least %d characters.', $minimum));
    }
}
