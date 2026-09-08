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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit\Security\Fixtures;

use Uhifadhi\Contracts\Entity\UserInterface;
use Uhifadhi\Contracts\Security\ApiTokenResolverInterface;

/**
 * A credential store that knows exactly one token.
 *
 * It records what it was asked to touch, because "the token was noted as seen"
 * is a promise the authenticator makes and the only way to see it kept.
 */
final class FixedTokenResolver implements ApiTokenResolverInterface
{
    public const string EMAIL = 'w.mbise@example.test';

    /** @var list<string> every string touch() was asked about, in order */
    public array $touched = [];

    public function __construct(
        private readonly string $known,
        private readonly ContractPerson $person = new ContractPerson(),
    ) {
    }

    public function find(string $presented): ?UserInterface
    {
        return $presented === $this->known ? $this->person : null;
    }

    public function touch(string $presented): void
    {
        $this->touched[] = $presented;
    }
}
