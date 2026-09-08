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

namespace Uhifadhi\Bundle\TeamBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\TeamBundle\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\TeamBundle\Repository\ApiTokenRepository;

/**
 * A BEARER TOKEN ISSUED TO ONE DEVICE FOR ONE ACCOUNT — a field client's only
 * credential after it signs in.
 *
 * Opaque, not a signed blob, and the field is the reason: the token is measured
 * in months and there is no refresh call, because somebody working out of
 * signal cannot re-authenticate on demand. A months-long SELF-CONTAINED token
 * cannot be withdrawn — a lost handset would stay authorised until it expired.
 * This row is the withdrawal, and {@see $revokedAt} takes effect on the next
 * request.
 *
 * THE TOKEN STRING IS NEVER STORED. Only its SHA-256 hash is, so a leaked
 * database yields nothing a handset could present; the plaintext exists once,
 * in the sign-in response. Lookup is BY hash, which is why the column is unique
 * rather than searched.
 */
#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'team_api_token')]
#[ORM\HasLifecycleCallbacks]
class ApiToken
{
    use TimestampableTrait;
    use UuidTrait;

    /** SHA-256 in hex — 64 characters, fixed. */
    public const int HASH_LENGTH = 64;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** SHA-256 hex of the presented token — the lookup key, never the token itself. */
    #[ORM\Column(length: self::HASH_LENGTH, unique: true)]
    private string $tokenHash;

    /**
     * The stable per-install identifier the token was minted for. Kept so a
     * single lost handset can be withdrawn without signing its owner out of a
     * replacement, and so re-signing in on the same one rotates its token
     * rather than accumulating one per attempt.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $deviceId = null;

    /** The label the client gives itself — what the withdrawal screen prints. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    /** Set when an administrator withdraws the token; a withdrawn token authenticates nobody. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * The last time this token authenticated a request, written at most once a
     * day: an every-request UPDATE would turn each authenticated read into a
     * write, on a sync burst of hundreds, for a field nobody reads to the
     * minute.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(User $owner, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->owner = $owner;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->initTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = $deviceId;

        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): static
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    /** Withdrawing an already-withdrawn token keeps the first moment: when it stopped is a fact. */
    public function revoke(?\DateTimeImmutable $at = null): static
    {
        $this->revokedAt ??= $at ?? new \DateTimeImmutable();

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    /** Neither withdrawn nor past its expiry — the only state that authenticates. */
    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $this->expiresAt > $now;
    }

    /** Rotate this device's token in place rather than minting a second row. */
    public function reissue(string $tokenHash, \DateTimeImmutable $expiresAt): static
    {
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->revokedAt = null;
        $this->lastUsedAt = null;

        return $this;
    }
}
