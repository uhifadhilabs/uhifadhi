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
 * An account already answers to that address.
 *
 * The email IS the sign-in identifier, so two accounts sharing one is two
 * people who cannot both sign in. Addresses are folded to lower case before
 * they are compared, which is why a different capitalisation is the same
 * person rather than a second one.
 *
 * The refusal carries the address rather than a sentence: the same fact is
 * worded differently on the screen that adds somebody with a password, on the
 * one that sends an invitation, and at the console, and each of those is
 * entitled to say it its own way.
 */
final class EmailAlreadyUsedException extends \DomainException
{
    public function __construct(public readonly string $email, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('An account with the email %s already exists.', $email), 0, $previous);
    }
}
