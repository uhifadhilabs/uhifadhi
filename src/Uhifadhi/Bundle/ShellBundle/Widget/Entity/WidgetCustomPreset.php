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
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\Trait\TimestampableTrait;
use Uhifadhi\Bundle\ShellBundle\Widget\Entity\Trait\UuidTrait;
use Uhifadhi\Bundle\ShellBundle\Widget\Repository\WidgetCustomPresetRepository;
use Uhifadhi\Bundle\ShellBundle\Widget\Service\WidgetService;
use Uhifadhi\Contracts\Entity\UserInterface;

/**
 * A layout one person saved under their own name, on one dashboard SURFACE: the
 * other half of presets. A surface SHIPS the design directions it was drawn in;
 * a person KEEPS the arrangements they actually work in ("morning check",
 * "board meeting") and puts one back on in a click.
 *
 * The scoping trio is the one {@see WidgetPreference} uses, and it is scoped the
 * same way for the same reasons: the AREA as a stored uuid, because a saved
 * layout is a UI scrap and deleting an area must never be blocked by one; the
 * PERSON as an association to
 * {@see UserInterface}, cascading at the
 * database, because a layout somebody saved has no meaning once the account is
 * gone. It is addressed externally by its UUID, never by the sequential id.
 *
 * `layout` is a {@see \Uhifadhi\Bundle\ShellBundle\Widget\Model\WidgetPreset} layout — widget id => span,
 * in order, listed meaning on — NOT the stored-preference shape. It is written
 * from a resolved layout and read back tolerantly, so a preset saved before a
 * widget was retired still applies.
 */
#[ORM\Entity(repositoryClass: WidgetCustomPresetRepository::class)]
#[ORM\Table(name: 'widget_custom_preset')]
// ONE preset per name per (surface, user, area) — so saving under a name you
// already used replaces that preset rather than growing a second card with the
// same word on it. Two partial unique indexes for the same reason
// widget_preference needs two: Postgres treats NULLs as distinct, so a single
// four-column constraint would not constrain the org-wide rows at all. The WHERE
// clauses name the column, which is what Postgres reads — keep them in step with
// the naming strategy (underscore).
#[ORM\UniqueConstraint(
    name: 'uniq_widget_preset_surface_user_area_name',
    fields: ['surface', 'user', 'areaUuid', 'name'],
    options: ['where' => '(area_uuid IS NOT NULL)'],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_widget_preset_surface_user_org_name',
    fields: ['surface', 'user', 'name'],
    options: ['where' => '(area_uuid IS NULL)'],
)]
#[ORM\HasLifecycleCallbacks]
class WidgetCustomPreset
{
    use TimestampableTrait;
    use UuidTrait;

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

    /** What the person called it. Trimmed and length-checked by WidgetService before it lands here. */
    #[ORM\Column(length: WidgetService::NAME_MAX)]
    private string $name;

    /** @var array<string, int> */
    #[ORM\Column(type: Types::JSON)]
    private array $layout = [];

    /** @param array<string, int> $layout */
    public function __construct(string $surface, UserInterface $user, ?Uuid $areaUuid, string $name, array $layout = [])
    {
        $this->surface = $surface;
        $this->user = $user;
        $this->areaUuid = $areaUuid;
        $this->name = $name;
        $this->layout = $layout;
        // Values exist pre-flush; the PrePersist callbacks keep what is set.
        $this->initTimestamps();
        $this->generateUuid();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /** @return array<string, int> */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /** @param array<string, int> $layout */
    public function setLayout(array $layout): static
    {
        $this->layout = $layout;

        return $this;
    }
}
