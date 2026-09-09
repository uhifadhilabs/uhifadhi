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

namespace Uhifadhi\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Contracts\Entity\AreaInterface;

/**
 * THE HOST'S AREA, PLAYED BY A STAND-IN — and the reason the registry needs a
 * published interface here at all.
 *
 * An area belongs to the host application: the host owns areas, team and
 * nothing else (that is the lean-flat-host ruling). The registry owns the record
 * of which modules an area has, which means its AreaModule row has to point at
 * a class the registry does not define and must not require.
 *
 * The answer is Doctrine's own: the registry maps the association to
 * {@see AreaInterface} — the platform's area contract, now published by
 * uhifadhi/contracts — and the host resolves that interface to its real
 * entity with `doctrine.orm.resolve_target_entities`. This fixture is a host,
 * minimally — a real entity in the host's namespace, implementing the contract,
 * autoloaded in this suite alone (see composer.json autoload-dev).
 *
 * ITS TABLE IS THE SUITE'S, NOT A BUNDLE'S. The one database the core's suites
 * share has exactly one owner per table name, and `area_of_interest` belongs to
 * AreaBundle. What this fixture has to impersonate is the CLASS NAME an
 * installation resolves the contract to; where its own rows are kept is nobody's
 * business but this suite's, so it keeps them under a name no package claims.
 *
 * A STAND-IN THAT IMPERSONATES A REAL FQCN. `Uhifadhi\Entity\AreaOfInterest` is
 * the uhifadhi host application's own class, spelled here byte-for-byte so the
 * suite exercises the registry a real installation exercises. It is marked as a
 * stand-in three ways: by location (tests/Integration/Fixtures/ under the
 * impersonated tree), by the autoload-dev mapping that scopes it to the dev
 * autoloader, and by this paragraph. Its namespace belongs to the host, not to
 * this bundle, so it stays fixed whatever this bundle's own namespace is; only
 * the `use` statement above tracks the platform's area contract. It carries
 * exactly the fields the registry reads — the identity it maps its AreaModule
 * association to, the uuid its route gate resolves an area from, and a name —
 * and nothing else the host may happen to hold. A project installed from the
 * skeleton names its area `App\Entity\AreaOfInterest` instead; either spelling
 * resolves through the same interface, which is the whole point.
 *
 * The alternative — storing a bare area id or uuid on the row — was considered
 * and rejected: it would make every "the modules of this area" query a manual
 * join the registry writes by hand, and it would let a row point at an area that
 * no longer exists, which is exactly the kind of orphan the ON DELETE CASCADE
 * on this association prevents today.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_area_of_interest')]
#[ORM\HasLifecycleCallbacks]
class AreaOfInterest implements AreaInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /**
     * ADDRESSED PUBLICLY BY UUID, like the class it impersonates — the host's
     * area carries a UUIDv7 and its routes are `/areas/{uuid}/…`, never the
     * sequential id. The registry's gate on a parked module's routes reads an
     * area out of a URL, so the field the URL carries is part of what this
     * suite has to exercise.
     */
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    #[ORM\Column(length: 120)]
    private string $name = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function getUuidString(): ?string
    {
        return $this->uuid?->toRfc4122();
    }

    #[ORM\PrePersist]
    public function generateUuid(): void
    {
        $this->uuid ??= Uuid::v7();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }
}
