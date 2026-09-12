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

namespace Uhifadhi\Bundle\TeamBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Uhifadhi\Bundle\TeamBundle\Api\ContractFormat;
use Uhifadhi\Bundle\TeamBundle\ApiResource\Me;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Exception\ApiProblemException;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;

/**
 * Answers `GET /api/me`: the bearer account, and every permission it holds.
 *
 * THE ANSWER IS ABOUT THE BEARER AND NOBODY ELSE. There is no identifier in the
 * address and none is accepted — the token IS the subject, so this endpoint
 * cannot be turned into a way to read somebody else's permissions by guessing at
 * a parameter.
 *
 * IT REFUSES WITH 401 RATHER THAN LETTING A NULL THROUGH. The firewall already
 * refuses an anonymous request, so reaching here without an account is a
 * misconfiguration; answering it as "sign in again" is the safe reading of it,
 * and it is a reading a client already knows how to act on.
 *
 * @implements ProviderInterface<Me>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(
        private TokenStorageInterface $tokens,
        private PermissionCatalogue $permissions,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof User) {
            // 401 AND NOT 403: the caller has not proved who they are, and a
            // client shows a person different things for the two — "sign in
            // again" against "you may not do that".
            throw new ApiProblemException(Response::HTTP_UNAUTHORIZED, 'unauthorized', 'The token is not valid. Sign in again.');
        }

        return new Me(ContractFormat::ranger($user), $this->permissions->heldBy($user));
    }
}
