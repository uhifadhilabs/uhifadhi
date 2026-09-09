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

namespace Uhifadhi\Bundle\TeamBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\ApiToken;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * MINTS AND CHECKS A FIELD CLIENT'S BEARER TOKENS — the only place a token
 * string exists in plaintext.
 *
 * A token is measured in months and there is no refresh call, because somebody
 * working out of signal cannot re-authenticate on demand and an expiry
 * mid-shift must never cost recorded work. That is exactly why the token is a
 * database row rather than a signed blob: months of validity is only safe if it
 * can be withdrawn.
 *
 * IT ANSWERS THE RESOLVER CONTRACT, which is how a request is authenticated
 * without anything outside this package learning what a token is. Issuing is
 * NOT part of that contract and is deliberately wider than it: minting a
 * credential needs the person and their password, and belongs to the package
 * that owns the account.
 */
final class ApiTokenManager
{
    /**
     * Six months. Long enough to cover a posting; short enough that a handset
     * which quietly left service stops working within one.
     */
    public const string LIFETIME = 'P180D';

    /** How long a use has to be un-recorded before recording it is worth a write. */
    private const string TOUCH_INTERVAL = 'P1D';

    /** 32 bytes of CSPRNG output in hex — 256 bits, well past guessing. */
    private const int TOKEN_BYTES = 32;

    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Issue a token for one device, returning the plaintext ONCE — it is not
     * recoverable afterwards, by this installation or by anyone reading its
     * database.
     *
     * Signing in again on a handset that already has a token ROTATES that row
     * instead of adding another, so "sign in again" after a wipe cannot leave a
     * trail of live credentials behind it.
     *
     * @return array{0: string, 1: ApiToken} [the plaintext token, its record]
     */
    public function issue(User $user, ?string $deviceId = null, ?string $deviceName = null): array
    {
        $plaintext = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash = self::hash($plaintext);
        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(self::LIFETIME));

        $token = null !== $deviceId ? $this->tokens->findOneByDevice($user, $deviceId) : null;

        if ($token instanceof ApiToken) {
            $token->reissue($hash, $expiresAt)->setDeviceName($deviceName);
        } else {
            $token = new ApiToken($user, $hash, $expiresAt)
                ->setDeviceId($deviceId)
                ->setDeviceName($deviceName);
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();

        return [$plaintext, $token];
    }

    /**
     * The person a presented token names, or null when it names nobody.
     *
     * Unknown, withdrawn and expired all answer null on purpose — which of the
     * three it was would tell whoever is guessing whether their guess exists.
     */
    public function find(string $presented): ?UserInterface
    {
        return $this->record($presented)?->getOwner();
    }

    /**
     * Record that a token was seen, at most once a day. Every request writing a
     * timestamp would make each authenticated READ a database WRITE — a real
     * cost on a sync burst of hundreds — for a field nobody reads to the
     * minute.
     */
    public function touch(string $presented): void
    {
        $token = $this->record($presented);
        if (!$token instanceof ApiToken) {
            return;
        }

        $now = new \DateTimeImmutable();
        $last = $token->getLastUsedAt();

        if (null !== $last && $last > $now->sub(new \DateInterval(self::TOUCH_INTERVAL))) {
            return;
        }

        $token->setLastUsedAt($now);
        $this->entityManager->flush();
    }

    /** Withdraw a token. It stops authenticating on the next request. */
    public function revoke(ApiToken $token): void
    {
        $token->revoke();
        $this->entityManager->flush();
    }

    /** The row behind a presented token, but only while it still authenticates. */
    private function record(string $presented): ?ApiToken
    {
        $token = $this->tokens->findOneByHash(self::hash($presented));

        return $token?->isUsableAt(new \DateTimeImmutable()) ? $token : null;
    }

    /**
     * SHA-256, not a password hash: this is a 256-bit random string, not a
     * guessable secret, so there is nothing for a work factor to defend
     * against — and a per-request verify with one would be a self-inflicted
     * denial of service on a sync burst. Unsalted is required rather than an
     * oversight: the hash IS the lookup key.
     */
    private static function hash(string $presented): string
    {
        return hash('sha256', $presented);
    }
}
