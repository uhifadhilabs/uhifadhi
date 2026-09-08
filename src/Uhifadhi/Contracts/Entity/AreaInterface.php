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

namespace Uhifadhi\Contracts\Entity;

/**
 * THE AREA A MODULE'S RECORD POINTS AT — published as an interface so no module
 * has to require the package that defines an area.
 *
 * Several modules keep records that belong to an area: the seam's record of
 * which modules an area has switched on, a department confined to one area, a
 * patrol walked inside one. The area entity itself belongs to
 * `AreaBundle`, and a module that type-hinted that bundle's
 * `AreaOfInterest` would be a module you cannot install without it — and a
 * module that could never be pointed at an installation's own area class. So the
 * module maps its association to this interface, and WHOEVER KNOWS THE ANSWER
 * STATES THE RESOLUTION — here AreaBundle, because it is the package
 * that provides the entity. It prepends this and an installation writes nothing:
 *
 *     doctrine:
 *         orm:
 *             resolve_target_entities:
 *                 Uhifadhi\Contracts\Entity\AreaInterface: Uhifadhi\Area\Entity\AreaOfInterest
 *
 * An installation writes that line only to DISAGREE, naming its own class, which
 * wins because prepended configuration loses to the application's.
 *
 * IT LIVES HERE, ALONGSIDE THE USER CONTRACT. It began as the seam's own
 * promise — `Uhifadhi\Seam\Entity\AreaInterface` — on the argument that only the
 * seam needed an area and one package answered it. That argument no longer
 * holds: departments in TeamBundle, and records in other modules, point at an
 * area the same way they point at a person. A promise exchanged between MANY
 * modules and the platform belongs in the contracts package, by the same rule
 * that puts {@see UserInterface} here — so the seam's name is now a deprecated
 * alias of this one, and this is where an area is asked for.
 *
 * IT LIVES IN Entity/ because that is what it is: the stand-in Doctrine maps an
 * association to, resolved to a real entity at compile time. It carries no
 * mapping of its own — the attributes belong on the owning side, in the module
 * that declares the association — and it imports nothing at all, because a
 * package of promises that dragged in an ORM would cost something to depend on.
 *
 * IT ASKS FOR IDENTITY, A NAME AND A PUBLIC ADDRESS — the same three questions,
 * and for the same reasons, that {@see UserInterface} asks of a person. An area
 * is whatever the installation ends up with; a module that points at one needs
 * to tell two of them apart (the id), to print which one a record belongs to
 * (the name), and to address it across a module boundary without a relation
 * (the uuid string). Everything else an area has — its boundary geometry, its
 * IUCN category, its gazettement year — is the area owner's business and is
 * deliberately absent, because a contract is only as portable as it is narrow.
 */
interface AreaInterface
{
    /**
     * The persistence identity. Present because Doctrine's association is built
     * on it, and because it is the one thing the seam needs of an area — to tell
     * two of them apart. Null before the area has ever been stored; never put in
     * a URL or an API payload.
     */
    public function getId(): ?int;

    /**
     * The name to print — what a module shows to say which area a record belongs
     * to. Null before the area has been given one.
     */
    public function getName(): ?string;

    /**
     * THE PUBLIC ADDRESS — a UUIDv7 in RFC 4122 form, or null before the area
     * has ever been stored. This is what crosses a module boundary: a URL, an
     * export column, a foreign-key surface in a record that is not worth a
     * relation.
     *
     * A STRING, NOT A `Symfony\Component\Uid\Uuid`. Asking for the object would
     * put a dependency in a package whose whole claim is that it has none. The
     * caller that wants one builds it; the implementations already had this
     * exact accessor.
     */
    public function getUuidString(): ?string;
}
