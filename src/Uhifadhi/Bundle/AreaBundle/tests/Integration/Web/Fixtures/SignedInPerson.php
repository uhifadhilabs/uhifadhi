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

namespace Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\Fixtures;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * THE SUITE'S OWN SIGN-IN, standing in for a firewall — the same escape
 * {@see \Uhifadhi\Bundle\AreaBundle\Tests\Integration\Web\GrantedPermissions} takes
 * for the voter, and for the same reason: what these screens are tested for is
 * what they ASK, not who answers it.
 *
 * IT HAS TO HAPPEN PER REQUEST. Token storage is a resettable service and the
 * kernel empties every one of them on the boot before a second request, so a
 * principal put in place from the test method is gone by the time the second page
 * is handled — which is exactly how a suite comes to prove that a write is
 * refused to nobody. Putting it back on `kernel.request` is what makes a test that
 * walks two pages the same person on both.
 *
 * THE PERSON IS FETCHED, NOT HELD. A layout is stored AGAINST the account, so the
 * principal has to be an entity the entity manager of THIS request manages; the
 * managers are reset alongside everything else, and a held instance would be one
 * the second request's manager has never heard of.
 */
final class SignedInPerson
{
    private ?string $uuid = null;

    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly TokenStorageInterface $tokens,
    ) {
    }

    /** Whose requests these are from now on — null for nobody's. */
    public function is(?HostUser $person): void
    {
        $this->uuid = $person?->getUuidString();
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->uuid) {
            return;
        }

        $person = $this->doctrine->getRepository(HostUser::class)->findOneBy(['uuid' => $this->uuid]);
        if ($person instanceof HostUser) {
            $this->tokens->setToken(new UsernamePasswordToken($person, 'main', ['ROLE_USER']));
        }
    }
}
