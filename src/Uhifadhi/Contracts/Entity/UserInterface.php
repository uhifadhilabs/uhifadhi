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
 * THE PERSON A MODULE'S RECORD POINTS AT — published as an interface so no
 * module has to require the package that defines them.
 *
 * Almost every module keeps records with a person on them: who reported the
 * incident, who led the patrol, whose dashboard layout this is. The account
 * itself belongs to `TeamBundle`, and a module that type-hinted that
 * bundle's `User` would be a module you cannot install without it — and a
 * module that could never be pointed at an installation's own account class.
 * So the module maps its association to this interface, and WHOEVER KNOWS THE
 * ANSWER STATES THE RESOLUTION — here TeamBundle, because it is the
 * package that provides the entity. It prepends this and an installation writes
 * nothing:
 *
 *     doctrine:
 *         orm:
 *             resolve_target_entities:
 *                 Uhifadhi\Contracts\Entity\UserInterface: Uhifadhi\Team\Entity\User
 *
 * An installation writes that line only to DISAGREE, naming its own class, which
 * wins because prepended configuration loses to the application's.
 *
 * IT LIVES IN Entity/ because that is what it is: the stand-in Doctrine maps an
 * association to, resolved to a real entity at compile time. It carries no
 * mapping of its own — the attributes belong on the owning side, in the module
 * that declares the association — and it imports nothing at all, because a
 * package of promises that dragged in an ORM would cost something to depend on.
 *
 * IT LIVES IN THIS PACKAGE, ALONGSIDE {@see AreaInterface}. This one is
 * exchanged between MANY modules and the platform — patrol, incidents and widget
 * preferences all point at a person, and a third-party module written by
 * somebody else will too. The area contract was once kept in the seam on the
 * argument that only the seam needed an area; that argument no longer holds now
 * that departments and other modules point at an area the same way, so it moved
 * here too. That is the rule of thumb in docs/what-is-a-contract.md applied
 * literally: a promise between modules and the platform lives here.
 *
 * THE SURFACE IS MEASURED, NOT COPIED. It is not "the account class, as an
 * interface". Every question below is one the installed modules were found to
 * ask; everything else an account has — its password, its verification tokens,
 * its roles, the position it holds — is the account owner's business and is
 * deliberately absent, because a contract is only as portable as it is narrow.
 *
 * IT IS NOT A SECURITY USER. `Symfony\Component\Security\Core\User\UserInterface`
 * answers "who is signed in"; this answers "who is this record about". A module
 * that needs the signed-in principal takes Symfony's interface from the token
 * storage, exactly as before. The two names collide, so import one aliased when
 * a class genuinely needs both.
 */
interface UserInterface
{
    /**
     * The persistence identity. Present because Doctrine's association is built
     * on it and because a module that has filtered a query down to "mine" has
     * to say which one that is; never put in a URL or an API payload.
     */
    public function getId(): ?int;

    /**
     * THE PUBLIC ADDRESS — a UUIDv7 in RFC 4122 form, or null before the
     * account has ever been stored. This is what crosses a module boundary: a
     * URL, an export column, a foreign-key surface in a record that is not
     * worth a relation.
     *
     * A STRING, NOT A `Symfony\Component\Uid\Uuid`. Asking for the object would
     * put a dependency in a package whose whole claim is that it has none. The
     * caller that wants one builds it; the implementations already had this
     * exact accessor.
     */
    public function getUuidString(): ?string;

    /** The web sign-in identifier, and the only address a module can write to. */
    public function getEmail(): ?string;

    public function getFirstName(): ?string;

    public function getLastName(): ?string;

    /**
     * The name to print. Separate from the two parts above because a module
     * that shows an initial and a surname needs the parts, and one writing an
     * audit line needs the whole thing already joined — and because the joining
     * rule is the account owner's to change, not every module's to reimplement.
     */
    public function getFullName(): string;

    /**
     * The short service number a field worker knows themselves by ("sl-0142")
     * and types into the field app. Null for anyone who was never issued one —
     * office staff never are.
     *
     * It is here, and the rest of the account is not, for one concrete reason:
     * a module with a field API has to turn the code a phone sent into the
     * person who sent it, and there is no other question that answers.
     */
    public function getRangerCode(): ?string;
}
