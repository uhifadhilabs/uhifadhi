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

namespace Uhifadhi\Bundle\ShellBundle\Widget\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Contracts\Entity\UserInterface;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetPreferenceRepository;

/**
 * One person's layout for one dashboard SURFACE: which widgets they keep, how
 * wide, in what order. Absence is the surface catalogue's default layout, so a
 * user who never opened the widget library has no row at all and "reset" is a
 * delete.
 *
 * A surface is either area-scoped (a module dashboard inside one area) or
 * org-wide (the departments dashboard, which has no area) — hence the nullable
 * area. The area is held as its UUID rather than a relation: a preference is a
 * UI scrap, and deleting an area must never be blocked by one.
 *
 * THE PERSON IS A RELATION, and the two are not inconsistent. A layout is not
 * merely scoped to somebody, it is THEIRS, and when an account goes the layout
 * has no meaning left — which is what `ON DELETE CASCADE` says. So the
 * association is the right shape here where a stored uuid was the right shape
 * for the area. It points at
 * {@see \Uhifadhi\Contracts\Entity\UserInterface} rather than at any
 * bundle's account class, because a module that named one would be a module
 * nobody can install without it. Whoever provides the account class resolves the
 * interface: TeamBundle does it from its own bundle, so an
 * installation running team writes nothing, and an installation with its own
 * class names it under `doctrine.orm.resolve_target_entities` and overrules
 * that. Until somebody answers, nothing here can build a schema.
 *
 * `prefs` is the stored shape {order: [id, …], widgets: {id: {on, cols}}},
 * always written through {@see \Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService}, never trusted
 * on the way out.
 */
#[ORM\Entity(repositoryClass: WidgetPreferenceRepository::class)]
#[ORM\Table(name: 'widget_preference')]
// ONE row per (surface, user, area) — but Postgres treats NULLs as distinct in a
// unique index, so a plain three-column constraint would happily accept two
// org-wide rows for the same person. It therefore takes TWO partial unique
// indexes covering the two disjoint cases: the area-scoped rows keyed by all
// three columns, and the org-wide rows (area IS NULL) keyed by the other two.
// Declared with `fields` so the installation's naming strategy still owns the column
// names; the WHERE clauses name the column because that is what Postgres reads —
// keep them in step with the naming strategy (underscore).
#[ORM\UniqueConstraint(
    name: 'uniq_widget_pref_surface_user_area',
    fields: ['surface', 'user', 'areaUuid'],
    options: ['where' => '(area_uuid IS NOT NULL)'],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_widget_pref_surface_user_org',
    fields: ['surface', 'user'],
    options: ['where' => '(area_uuid IS NULL)'],
)]
#[ORM\HasLifecycleCallbacks]
class WidgetPreference
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null; // @phpstan-ignore property.unusedType (assigned by Doctrine via reflection)

    /** The dashboard this layout belongs to, e.g. 'departments' — see WidgetCatalog::$surface. */
    #[ORM\Column(length: 64)]
    private string $surface;

    /**
     * WHOSE LAYOUT THIS IS. `ON DELETE CASCADE` at the database, not a Doctrine
     * cascade: the row has no meaning without the account, and the guarantee has
     * to hold for a `DELETE` written by hand as well as for one the ORM issues.
     */
    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserInterface $user;

    /** Null on an org-wide surface, which has no area to scope a layout to. */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $areaUuid;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $prefs = [];

    /**
     * WHICH PRESET THIS LAYOUT IS. There is no anonymous layout: a dashboard
     * always renders exactly one preset, so the row names it — 'design' for one
     * the surface ships (the id is a {@see \Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset} id) or
     * 'mine' for one the person saved (the id is a
     * {@see WidgetCustomPreset}'s UUID string).
     *
     * Nullable because a row written before this column existed has a layout and
     * no reference, and because absence is already the answer for a person who
     * never chose. Reading is TOLERANT for the same reason every other read here
     * is: an unreadable or retired reference falls back to the surface's default
     * built-in rather than showing nothing.
     */
    #[ORM\Column(length: 8, nullable: true)]
    private ?string $activeKind = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $activePreset = null;

    /** A built-in the surface ships. */
    public const string KIND_DESIGN = 'design';

    /** One of the person's own saved presets. */
    public const string KIND_MINE = 'mine';

    /** @param array<string, mixed> $prefs */
    public function __construct(string $surface, UserInterface $user, ?Uuid $areaUuid = null, array $prefs = [])
    {
        $this->surface = $surface;
        $this->user = $user;
        $this->areaUuid = $areaUuid;
        $this->prefs = $prefs;
        // Values exist pre-flush; PrePersist keeps them if already set.
        $this->initTimestamps();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurface(): string
    {
        return $this->surface;
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getAreaUuid(): ?Uuid
    {
        return $this->areaUuid;
    }

    /** @return array<string, mixed> */
    public function getPrefs(): array
    {
        return $this->prefs;
    }

    /** @param array<string, mixed> $prefs */
    public function setPrefs(array $prefs): static
    {
        $this->prefs = $prefs;

        return $this;
    }

    /** Null until this person has chosen — see the property's own note. */
    public function getActiveKind(): ?string
    {
        return $this->activeKind;
    }

    public function getActivePreset(): ?string
    {
        return $this->activePreset;
    }

    /**
     * Name the preset this layout is. The pair moves together — a kind without an
     * id is not a reference — so one setter writes both, and null clears it back
     * to "this person has not chosen".
     */
    public function setActive(?string $kind, ?string $presetId): static
    {
        if (null === $kind || null === $presetId || '' === $presetId) {
            $this->activeKind = null;
            $this->activePreset = null;

            return $this;
        }
        if (self::KIND_DESIGN !== $kind && self::KIND_MINE !== $kind) {
            throw new \InvalidArgumentException(\sprintf('A widget preference is active on a "%s" or a "%s" preset, never a "%s" one.', self::KIND_DESIGN, self::KIND_MINE, $kind));
        }

        $this->activeKind = $kind;
        $this->activePreset = $presetId;

        return $this;
    }
}
