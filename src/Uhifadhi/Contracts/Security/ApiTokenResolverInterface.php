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

namespace Uhifadhi\Contracts\Security;

use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * WHO A PRESENTED API TOKEN BELONGS TO — the cut between authenticating a
 * request and holding the credential it presented.
 *
 * A field client signs in once and then carries a bearer token for months,
 * because a person working out of signal cannot re-authenticate on demand. Two
 * different things follow from that, and they belong to two different packages:
 * the token is a CREDENTIAL OF A PERSON, kept, rotated and withdrawn beside the
 * account it belongs to; deciding whether a request is authenticated is
 * MECHANISM, and mechanism knows nothing about people. This interface is where
 * the two meet, so neither has to import the other.
 *
 * WHAT IS DELIBERATELY NOT HERE: issuing. Minting a credential is an act of the
 * package that owns the account — it needs the person, the device and the
 * password behind them — and nothing authenticating a request has any business
 * being able to do it. An implementation will have an issue method; the
 * contract does not.
 *
 * NOR IS THE TOKEN RECORD. Both questions are asked with the presented string
 * and nothing else, so an implementation's own row — its hash, its expiry, the
 * device it was minted for — never crosses this line.
 */
interface ApiTokenResolverInterface
{
    /**
     * The person a presented token names, or null when it names nobody.
     *
     * UNKNOWN, WITHDRAWN AND EXPIRED ALL ANSWER null, and that is the whole
     * point: telling a caller which of the three it was tells whoever is
     * guessing whether their guess exists.
     */
    public function find(string $presented): ?UserInterface;

    /**
     * Record that a token was seen. Called on every authenticated request, so
     * an implementation is expected to write far less often than it is asked —
     * a timestamp per request would turn every authenticated read into a write
     * for a field nobody reads to the minute.
     *
     * A string that names nobody is not an error here: it is a token that was
     * already refused, and refusing it twice is not this method's job.
     */
    public function touch(string $presented): void;
}
