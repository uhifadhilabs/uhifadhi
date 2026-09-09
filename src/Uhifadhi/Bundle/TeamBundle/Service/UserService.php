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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Uhifadhi\Bundle\TeamBundle\Entity\Position;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Exception\EmailAlreadyUsedException;
use Uhifadhi\Bundle\TeamBundle\Exception\LastSuperAdminException;
use Uhifadhi\Bundle\TeamBundle\Exception\PasswordTooShortException;

/**
 * EVERY WAY AN ACCOUNT COMES INTO BEING OR CHANGES, in one place.
 *
 * A person's record is written from four directions — the screen that adds
 * somebody with a password, the one that sends an invitation, the record page
 * where a tier or a position is changed, and the console an installation is
 * bootstrapped from. Those are four callers of ONE set of rules: the address is
 * the identifier and is folded to lower case, the credential is hashed and never
 * kept, an account is deactivated and never deleted, and the installation may
 * not run out of Super Admins. Written four times, those rules hold in three
 * places and quietly not in the fourth.
 *
 * IT DECIDES NOTHING ABOUT WHO IS ASKING. Whether the administrator on the other
 * end may reach this person at all is an area-scope question about the SIGNED-IN
 * session ({@see \Uhifadhi\Bundle\TeamBundle\Security\AreaAuthority}), and it is
 * settled before a screen calls in here. So this is callable with no session at
 * all — which is what lets the console create the first administrator, when
 * there is nobody to be.
 *
 * IT REFUSES BY THROWING, and the sentence is the caller's. The same fact —
 * this address is taken — is worded one way beside the create form, another
 * beside the invitation form and another at a console, so the refusal carries
 * the fact and each caller says it in its own voice.
 */
final readonly class UserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $hasher,
        private SuperAdminInvariant $invariant,
    ) {
    }

    /**
     * ADD SOMEBODY WITH A PASSWORD — the path that needs nothing from the
     * deployment: no mailer, no outbound network, no reachable inbox.
     *
     * The account is VERIFIED the moment it exists, because an administrator who
     * typed the password has already proved it is real, which is more than an
     * email round-trip proves. `invitedAt` stays null: nobody invited them, and
     * the roster reads that null as the honest difference between the two paths.
     *
     * @throws EmailAlreadyUsedException when an account already answers to the address
     * @throws PasswordTooShortException when the password is under the one rule
     */
    public function create(
        string $email,
        string $firstName,
        string $lastName,
        #[\SensitiveParameter] string $password,
        TeamRoleEnum $tier = TeamRoleEnum::Staff,
        ?Position $position = null,
        ?string $rangerCode = null,
    ): User {
        if (mb_strlen($password) < User::PASSWORD_MIN_LENGTH) {
            throw new PasswordTooShortException();
        }

        $email = self::normalise($email);

        $user = new User()
            ->setEmail($email)
            ->setFirstName(trim($firstName))
            ->setLastName(trim($lastName))
            ->setRangerCode($rangerCode)
            ->setTeamRole($tier)
            ->setPosition($position)
            ->setVerified(true);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->save($user, $email);

        return $user;
    }

    /**
     * INVITE SOMEBODY — the path where nobody here ever knows the password.
     *
     * The account exists immediately so the roster can show who is expected, and
     * it is unusable until the person accepts: unverified, and carrying a
     * credential of random bytes rather than an empty hash, which is a hash some
     * verifier somewhere will one day accept. It has NO NAME, because an
     * administrator guessing at somebody's own spelling of their own name is a
     * small indignity the product does not need to cause.
     *
     * Sending the letter is the caller's: this returns the account whose
     * verification token addresses it.
     *
     * @throws EmailAlreadyUsedException when an account already answers to the address
     */
    public function invite(string $email, ?Position $position, ?User $invitedBy): User
    {
        $email = self::normalise($email);

        $user = new User()
            ->setEmail($email)
            ->setFirstName('')
            ->setLastName('')
            ->setPosition($position)
            ->setVerified(false)
            ->setVerificationToken(bin2hex(random_bytes(32)));
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));

        if (null !== $invitedBy) {
            $user->markInvitedBy($invitedBy);
        }

        $this->save($user, $email);

        return $user;
    }

    /** The four fields the record page has. */
    public function updateRecord(User $user, string $firstName, string $lastName, string $email, ?string $rangerCode): void
    {
        $user
            ->setFirstName(trim($firstName))
            ->setLastName(trim($lastName))
            ->setEmail(trim($email))
            ->setRangerCode($rangerCode);

        $this->entityManager->flush();
    }

    /**
     * @throws LastSuperAdminException when this would leave nobody who can administer the team
     */
    public function changeTier(User $user, TeamRoleEnum $tier): void
    {
        $this->invariant->assertMayChangeTier($user, $tier);

        $user->setTeamRole($tier);
        $this->entityManager->flush();
    }

    /**
     * SEAT SOMEBODY, OR UNSEAT THEM. A position is the only thing that grants a
     * staff member any capability at all, and null is a real choice: somebody
     * with no position is verified, can sign in, and can do nothing.
     */
    public function assignPosition(User $user, ?Position $position): void
    {
        $user->setPosition($position);
        $this->entityManager->flush();
    }

    /**
     * THE WAY SOMEBODY LEAVES. Not a delete, and there is no delete: the row
     * stays, everything they recorded keeps its author, and reactivating is one
     * call.
     *
     * @throws LastSuperAdminException when this would leave nobody who can administer the team
     */
    public function deactivate(User $user): void
    {
        $this->invariant->assertMayDeactivate($user);

        $user->deactivate();
        $this->entityManager->flush();
    }

    public function reactivate(User $user): void
    {
        $user->reactivate();
        $this->entityManager->flush();
    }

    /**
     * THE ADDRESS IS UNIQUE IN THE TABLE, and that index is what actually
     * refuses a second account on one email. A caller with a screen to draw asks
     * first, so it can word the refusal beside the field somebody typed in;
     * this is the backstop behind that read, and it turns the driver's
     * constraint violation into the fact it stands for.
     *
     * @throws EmailAlreadyUsedException
     */
    private function save(User $user, string $email): void
    {
        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $clash) {
            throw new EmailAlreadyUsedException($email, $clash);
        }
    }

    /**
     * The address is the sign-in identifier, so it is compared in one case only.
     * A different capitalisation is the same person, not a second one.
     */
    private static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
