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

namespace Uhifadhi\Bundle\TeamBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\TeamBundle\EventListener\ApiErrorListener;

/**
 * EVERY `/api` FAILURE IS ONE DOCUMENT, whoever produced it.
 *
 * A field client's entire failure policy reads `code` and `retryable`. The
 * sign-in endpoint answers in that document because it was written to; nothing
 * else under `/api` was — a 401 comes from the firewall, a 404 from routing, a
 * 500 from anywhere — and each of those answers in its own way, which for a
 * refusal is an HTML error page. A client parsing that gets a stack trace where
 * it expected a code, and its retry policy is whatever the parse failure
 * happens to do.
 *
 * So the document is made a property of the URL SPACE rather than of the
 * controllers that happen to live in it.
 *
 * IT ONLY EVER REPLACES A BODY. The status is settled by whoever refused, and
 * that is the one thing this must not second-guess — a listener that decided
 * statuses would be a second authorization system.
 */
#[CoversClass(ApiErrorListener::class)]
final class ApiErrorDocumentTest extends WebTestCaseWithSchema
{
    /**
     * NO TOKEN IS A JSON 401. The firewall's own answer is a bare response;
     * what a client meets is the document it parses everything else with.
     */
    public function testAnAnonymousApiRequestIsAJsonUnauthorized(): void
    {
        $this->client->request('GET', '/api/_guarded');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertJsonDocument();

        $body = $this->body();
        self::assertSame('unauthorized', $body['code']);
        self::assertNotSame('', $body['message']);
        // 401 is never retried in a loop: the person has to act.
        self::assertFalse($body['retryable']);
    }

    /** A path under /api that answers to nothing is the same document. */
    public function testAnUnroutedApiPathIsAJsonNotFound(): void
    {
        $this->client->request('GET', '/api/nothing-is-mounted-here');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertJsonDocument();
        self::assertSame('not_found', $this->body()['code']);
        self::assertFalse($this->body()['retryable']);
    }

    /** A method the route does not answer to, likewise. */
    public function testAMethodTheRouteRefusesIsAJsonMethodNotAllowed(): void
    {
        $this->client->request('DELETE', '/api/auth/token');

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $this->client->getResponse()->getStatusCode());
        self::assertJsonDocument();
        self::assertSame('method_not_allowed', $this->body()['code']);
    }

    /**
     * A REFUSAL THE ENDPOINT WROTE ITSELF IS LEFT ALONE. The sign-in document
     * is the wire contract a released client reads; a safety net that reshaped
     * it into a generic one would be the net breaking the thing it protects.
     */
    public function testAnEndpointsOwnRefusalIsNotReshaped(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['rangerId' => 'sl-9999', 'passcode' => 'guess']),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
        self::assertSame('invalid_credentials', $this->body()['code']);
    }

    /** And a success is not touched at all. */
    public function testASuccessfulApiResponseIsUntouched(): void
    {
        $this->client->request('GET', '/api/auth/token');

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * THE WEB IS NOT THE API. Every Twig page keeps its own error rendering —
     * a person meets a page, not a document.
     *
     * @return \Generator<string, array{string}>
     */
    public static function webPaths(): \Generator
    {
        yield 'an unrouted page' => ['/nothing-is-mounted-here'];
        yield 'a guarded page' => ['/_guarded'];
    }

    #[DataProvider('webPaths')]
    public function testAWebFailureIsNotTurnedIntoTheApiDocument(string $path): void
    {
        $this->client->request('GET', $path);

        self::assertGreaterThanOrEqual(300, $this->client->getResponse()->getStatusCode());
        self::assertStringStartsWith(
            'text/html',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
    }

    /** The document, key for key and in order — this is what a client parses. */
    private function assertJsonDocument(): void
    {
        self::assertStringStartsWith(
            'application/json',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
        self::assertSame(['code', 'message', 'retryable', 'details'], array_keys($this->body()));
        self::assertSame([], $this->body()['details'], 'details is an object, empty where there is nothing to say');
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
