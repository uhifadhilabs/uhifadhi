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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Integration\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;
use Uhifadhi\Bundle\TeamBundle\Security\ApiTokenAuthenticator;
use Uhifadhi\Bundle\TeamBundle\Service\ApiTokenManager;
use Uhifadhi\Bundle\TeamBundle\Tests\Integration\IntegrationTestCase;

/**
 * THE BEARER TOKEN, READ BESIDE THE STORE THAT KEEPS IT.
 *
 * A token is a credential of a person — issued, rotated and withdrawn beside
 * the account, exactly as a password is — so the thing that authenticates one
 * lives in the same bundle as the store, and asks it who a presented string
 * names. It still sees no row: no hash, no expiry and no device reaches it.
 *
 * The distinction these tests exist for is 401 vs 403. A client shows a person
 * different things for the two — "sign in again" against "you may not do
 * that" — so the difference has to be real, and it is made by claiming ONLY
 * requests that actually present a bearer token: a request with none falls
 * through unauthenticated to the entry point, which answers 401.
 */
#[CoversClass(ApiTokenAuthenticator::class)]
final class ApiTokenAuthenticatorTest extends IntegrationTestCase
{
    /**
     * @return \Generator<string, array{?string, bool}>
     */
    public static function headers(): \Generator
    {
        yield 'a bearer token' => ['Bearer abc123', true];
        yield 'no header at all' => [null, false];
        yield 'an empty header' => ['', false];
        yield 'another scheme' => ['Basic YWxpY2U6c2VjcmV0', false];
        yield 'the scheme with nothing after it' => ['Bearer ', false];
        yield 'the scheme with only spaces after it' => ['Bearer    ', false];
    }

    #[DataProvider('headers')]
    public function testItClaimsOnlyRequestsThatPresentABearerToken(?string $header, bool $expected): void
    {
        self::assertSame($expected, $this->authenticator()->supports(self::request($header)));
    }

    /**
     * WITH NO CREDENTIAL STORE, NOTHING AUTHENTICATES. The store is optional
     * because a container may not have one; the safe reading of "nobody can
     * answer who this is" is that nobody is anybody, so the request falls to
     * the entry point and 401.
     */
    public function testItClaimsNothingWhereNoCredentialStoreIsInstalled(): void
    {
        self::assertFalse(new ApiTokenAuthenticator(null)->supports(self::request('Bearer abc123')));
    }

    public function testAKnownTokenNamesItsPersonByTheirSignInIdentifier(): void
    {
        [$plaintext] = $this->tokens()->issue($this->ranger());

        $badge = $this->authenticator()->authenticate(self::request('Bearer '.$plaintext))->getBadge(UserBadge::class);

        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame('w.mbise@example.test', $badge->getUserIdentifier());

        // NO USER LOADER ON THE BADGE. The firewall's own provider loads the
        // account, so the checker a firewall names runs on the way in — a
        // loader that handed the object straight back would step over it.
        self::assertNull($badge->getUserLoader());
    }

    public function testAKnownTokenIsRecordedAsSeen(): void
    {
        [$plaintext, $token] = $this->tokens()->issue($this->ranger());
        self::assertNull($token->getLastUsedAt());

        $this->authenticator()->authenticate(self::request('Bearer '.$plaintext));

        $this->em->clear();
        self::assertNotNull($this->tokens()->find($plaintext), 'the token still names its person');
        self::assertNotNull(
            $this->em->getRepository($token::class)->find((int) $token->getId())?->getLastUsedAt(),
            'the token was noted as seen',
        );
    }

    /**
     * UNKNOWN, WITHDRAWN AND EXPIRED ARE ONE ANSWER. The store already collapses
     * them; this is the assertion that nothing here un-collapses them by
     * reporting the reason differently.
     */
    public function testATokenNamingNobodyIsRefused(): void
    {
        $this->ranger();

        $this->expectException(AuthenticationException::class);

        $this->authenticator()->authenticate(self::request('Bearer nothing-was-ever-issued-for-this'));
    }

    public function testNoCredentialsAtAllIsAnsweredWithUnauthorized(): void
    {
        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->authenticator()->start(self::request(null))->getStatusCode(),
        );
    }

    public function testARefusedTokenIsAnsweredWithUnauthorized(): void
    {
        self::assertSame(
            Response::HTTP_UNAUTHORIZED,
            $this->authenticator()->onAuthenticationFailure(self::request('Bearer wrong'), new AuthenticationException())->getStatusCode(),
        );
    }

    private function authenticator(): ApiTokenAuthenticator
    {
        return new ApiTokenAuthenticator($this->tokens());
    }

    private function tokens(): ApiTokenManager
    {
        return $this->service(ApiTokenManager::class);
    }

    private function ranger(): User
    {
        $user = new User()
            ->setEmail('w.mbise@example.test')
            ->setFirstName('Witness')
            ->setLastName('Mbise')
            ->setRangerCode('sl-0142')
            ->setTeamRole(TeamRoleEnum::Staff)
            ->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private static function request(?string $authorization): Request
    {
        $request = Request::create('/api/patrols');
        if (null !== $authorization) {
            $request->headers->set('Authorization', $authorization);
        }

        return $request;
    }
}
