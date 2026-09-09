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

namespace Uhifadhi\Bundle\TeamBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager;

/**
 * AUTHENTICATES A FIELD CLIENT: `Authorization: Bearer <token>`.
 *
 * IT LIVES BESIDE THE CREDENTIAL IT READS. A bearer token is a credential OF A
 * PERSON — issued, rotated and withdrawn beside the account, exactly as a
 * password is — so the thing that authenticates one belongs in the same bundle
 * as the store that keeps it. It still knows nothing about a row: who a
 * presented string names is asked of {@see ApiTokenManager}, and no hash, no
 * expiry and no device reaches this class.
 *
 * STATELESS BY THE FIREWALL THAT NAMES IT. No session is started and none is
 * read, so every request stands on its own — which is what a client that syncs
 * in bursts after hours offline actually needs.
 *
 * IT IS ALSO THE ENTRY POINT, and that is what makes 401 different from 403.
 * Without one, a request carrying NO token would be refused as 403 by the
 * access listener. A client shows a person different things for the two — "sign
 * in again" against "you may not do that" — so {@see supports()} claims only
 * requests that actually present a bearer token, and everything else falls
 * through to {@see start()}.
 *
 * @see https://symfony.com/doc/current/security/custom_authenticator.html
 * @see vendor/symfony/security-http/Authenticator/AbstractAuthenticator.php
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string SCHEME = 'Bearer ';

    /**
     * @param ApiTokenManager|null $tokens the credential store, absent where this
     *                                     installation keeps no API tokens — in which case nothing
     *                                     authenticates and every request answers 401, the safe reading of
     *                                     "nobody can say who this is"
     */
    public function __construct(
        private readonly ?ApiTokenManager $tokens,
    ) {
    }

    public function supports(Request $request): bool
    {
        return null !== $this->tokens && null !== self::bearer($request);
    }

    public function authenticate(Request $request): Passport
    {
        $presented = self::bearer($request)
            ?? throw new CustomUserMessageAuthenticationException('No bearer token.');

        $user = $this->tokens?->find($presented)
            // Unknown, withdrawn and expired are one message on purpose: which
            // of the three it was would tell whoever is guessing whether their
            // guess exists. A client's reaction is the same for all three —
            // stop, and ask the person to sign in again.
            ?? throw new CustomUserMessageAuthenticationException('The token is not valid. Sign in again.');

        $identifier = $user->getEmail()
            ?? throw new CustomUserMessageAuthenticationException('The token is not valid. Sign in again.');

        $this->tokens->touch($presented);

        // SELF-VALIDATING: the bearer token IS the proof, so there is no
        // credential left to check. The badge carries the identifier and NO
        // loader, which hands the lookup to the firewall's own user provider —
        // and therefore runs the user checker that firewall names, which a
        // loader returning the object straight back would step over.
        return new SelfValidatingPassport(new UserBadge($identifier));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response(null, Response::HTTP_UNAUTHORIZED);
    }

    /** No credentials at all — the same answer, so a client can tell it from a refusal to act. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new Response(null, Response::HTTP_UNAUTHORIZED);
    }

    /** The token from `Authorization: Bearer <token>`, or null if absent or malformed. */
    private static function bearer(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');

        if (null === $header || !str_starts_with($header, self::SCHEME)) {
            return null;
        }

        $token = trim(substr($header, \strlen(self::SCHEME)));

        return '' === $token ? null : $token;
    }
}
