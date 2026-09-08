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

namespace Uhifadhi\Bundle\RegistryBundle\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Uhifadhi\Bundle\RegistryBundle\Security\ApiTokenAuthenticator;
use Uhifadhi\Bundle\RegistryBundle\Tests\Unit\Security\Fixtures\FixedTokenResolver;

/**
 * THE BEARER TOKEN, DECIDED WITHOUT KNOWING WHAT A TOKEN IS.
 *
 * The authenticator asks a contract who a presented string names and builds a
 * passport out of the answer. It never sees a row, a hash or an expiry, which
 * is what lets it live beside the rest of the mechanism instead of beside the
 * accounts.
 *
 * The distinction these tests exist for is 401 vs 403. A client shows a person
 * different things for the two — "sign in again" against "you may not do
 * that" — so the difference has to be real, and it is made by claiming ONLY
 * requests that actually present a bearer token: a request with none falls
 * through unauthenticated to the entry point, which answers 401.
 */
final class ApiTokenAuthenticatorTest extends TestCase
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
        $authenticator = new ApiTokenAuthenticator(new FixedTokenResolver('abc123'));

        self::assertSame($expected, $authenticator->supports(self::request($header)));
    }

    /**
     * WITH NO CREDENTIAL STORE INSTALLED, NOTHING AUTHENTICATES. The resolver is
     * optional because this package can be installed without the one that keeps
     * accounts; the safe reading of "nobody can answer who this is" is that
     * nobody is anybody, so the request falls to the entry point and 401.
     */
    public function testItClaimsNothingWhereNoCredentialStoreIsInstalled(): void
    {
        self::assertFalse(new ApiTokenAuthenticator(null)->supports(self::request('Bearer abc123')));
    }

    public function testAKnownTokenNamesItsPersonByTheirSignInIdentifier(): void
    {
        $resolver = new FixedTokenResolver('abc123');
        $passport = new ApiTokenAuthenticator($resolver)->authenticate(self::request('Bearer abc123'));

        $badge = $passport->getBadge(UserBadge::class);
        self::assertInstanceOf(UserBadge::class, $badge);
        self::assertSame(FixedTokenResolver::EMAIL, $badge->getUserIdentifier());

        // NO USER LOADER ON THE BADGE. The firewall's own provider loads the
        // account, so the checker a firewall names runs on the way in — a
        // loader that handed the object straight back would step over it.
        self::assertNull($badge->getUserLoader());
    }

    public function testAKnownTokenIsRecordedAsSeen(): void
    {
        $resolver = new FixedTokenResolver('abc123');
        new ApiTokenAuthenticator($resolver)->authenticate(self::request('Bearer abc123'));

        self::assertSame(['abc123'], $resolver->touched);
    }

    /**
     * UNKNOWN, WITHDRAWN AND EXPIRED ARE ONE ANSWER. The resolver already
     * collapses them; this is the assertion that nothing here un-collapses them
     * by reporting the reason differently.
     */
    public function testATokenNamingNobodyIsRefused(): void
    {
        $this->expectException(AuthenticationException::class);

        new ApiTokenAuthenticator(new FixedTokenResolver('abc123'))
            ->authenticate(self::request('Bearer wrong'));
    }

    public function testNoCredentialsAtAllIsAnsweredWithUnauthorized(): void
    {
        $response = new ApiTokenAuthenticator(new FixedTokenResolver('abc123'))
            ->start(self::request(null));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testARefusedTokenIsAnsweredWithUnauthorized(): void
    {
        $response = new ApiTokenAuthenticator(new FixedTokenResolver('abc123'))
            ->onAuthenticationFailure(self::request('Bearer wrong'), new AuthenticationException());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
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
