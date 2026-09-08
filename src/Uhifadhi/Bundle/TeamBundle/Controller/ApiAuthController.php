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

namespace Uhifadhi\Bundle\TeamBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager;
use Uhifadhi\Bundle\TeamBundle\Service\FieldSignIn;
use Uhifadhi\Bundle\TeamBundle\Service\PermissionCatalogue;

/**
 * WHERE A FIELD CLIENT SIGNS IN — the one endpoint reachable without a token.
 *
 * IT HAS NO FIREWALL, and that is deliberate rather than an omission. A handset
 * whose token has expired still holds it and still sends it, and that stale
 * header must never be what stops somebody signing in again; so the
 * installation's security file leaves this one path unguarded and the endpoint
 * checks the credentials itself.
 *
 * THE DOCUMENT IS THE CONTRACT. The response is written here, key by key,
 * rather than serialized from an object: a released client reads these exact
 * names and cannot be redeployed because a serializer was reconfigured. The
 * suite asserts the literal document for the same reason.
 */
final class ApiAuthController
{
    /** UTC, to the second, with a literal Z — no offsets, no microseconds. */
    private const string TIMESTAMP = 'Y-m-d\TH:i:s\Z';

    /**
     * The per-install identifier a client sends on every request. Accepted as
     * the device when the body names none, so a client need not say the same
     * thing twice for a token to be scoped to one handset.
     */
    private const string DEVICE_HEADER = 'X-Doria-Device';

    public function __construct(
        private readonly FieldSignIn $signIn,
        private readonly ApiTokenManager $tokens,
        private readonly PermissionCatalogue $permissions,
    ) {
    }

    #[Route('/api/auth/token', name: 'team_api_auth_token', methods: ['POST'])]
    public function token(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || array_is_list($payload)) {
            return self::problem(Response::HTTP_BAD_REQUEST, 'invalid_request', 'The request body is not a JSON object.');
        }

        $identifier = self::text($payload, 'rangerId');
        $passcode = self::text($payload, 'passcode');
        if ('' === $identifier || '' === $passcode) {
            return self::problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_payload', 'A service number and a passcode are both required.');
        }

        $user = $this->signIn->authenticate($identifier, $passcode);
        if (!$user instanceof User) {
            // ONE SENTENCE FOR EVERY REFUSAL — see FieldSignIn. 401 is never
            // retried in a loop: the person has to act.
            return self::problem(Response::HTTP_UNAUTHORIZED, 'invalid_credentials', 'That service number and passcode do not match an account.');
        }

        [$plaintext, $token] = $this->tokens->issue(
            $user,
            '' !== self::text($payload, 'deviceId') ? self::text($payload, 'deviceId') : self::deviceHeader($request),
            '' !== self::text($payload, 'deviceName') ? self::text($payload, 'deviceName') : null,
        );

        return new JsonResponse([
            'token' => $plaintext,
            'expiresAt' => $token->getExpiresAt()->setTimezone(new \DateTimeZone('UTC'))->format(self::TIMESTAMP),
            'ranger' => [
                // The service number where there is one, the sign-in address
                // otherwise: office staff are never issued one.
                'id' => $user->getRangerCode() ?? (string) $user->getEmail(),
                'name' => $user->getFullName(),
                // What a client prints under the name, and what its refusal
                // screens name as the thing to change. Never blank.
                'role' => $user->getPosition()?->getName() ?? $user->getTeamRole()->label(),
            ],
            /*
             * THE EARLY SIGNAL. Without it a client cannot know whether it may
             * record until its first upload — hours later, possibly out of
             * signal, after a day of walking. The whole permission set goes
             * across, catalogue values and nothing else, and a client reads the
             * one member it understands; this installation learns nothing about
             * what any of them mean.
             *
             * ALWAYS SENT, INCLUDING EMPTY. An empty array is a refusal; a
             * MISSING field reads as "an older installation, therefore
             * permitted", so omitting it for some accounts and not others would
             * read as a grant.
             */
            'permissions' => $this->permissions->heldBy($user),
        ]);
    }

    /**
     * The failure document every field endpoint answers in: a code a client
     * switches on, a sentence it may show, and whether trying again could ever
     * help. `details` is an object even when empty, so a client's parser meets
     * one shape.
     */
    private static function problem(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse([
            'code' => $code,
            'message' => $message,
            'retryable' => false,
            'details' => new \stdClass(),
        ], $status);
    }

    /**
     * @param array<mixed> $payload
     */
    private static function text(array $payload, string $key): string
    {
        return \is_string($payload[$key] ?? null) ? trim($payload[$key]) : '';
    }

    private static function deviceHeader(Request $request): ?string
    {
        $header = trim($request->headers->get(self::DEVICE_HEADER) ?? '');

        return '' === $header ? null : $header;
    }
}
