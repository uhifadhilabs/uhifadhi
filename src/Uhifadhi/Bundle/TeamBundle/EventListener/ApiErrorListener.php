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

namespace Uhifadhi\Bundle\TeamBundle\EventListener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Uhifadhi\Bundle\TeamBundle\Exception\ApiProblemException;

/**
 * GIVES EVERY `/api` FAILURE ONE SHAPE: `{code, message, retryable, details}`.
 *
 * A field client's entire failure policy reads those fields. The sign-in
 * endpoint answers in that document because it was written to; nothing else
 * under `/api` was — a 401 comes from the firewall, a 404 from routing, a 422
 * from validation, a 500 from anywhere — and each of those answers in its own
 * way, which for a refusal is an HTML error page. A client parsing that gets a
 * stack trace where it expected a code, and its retry policy becomes whatever
 * the parse failure happens to do. So the document is made a property of the
 * URL SPACE rather than of the controllers that happen to live in it.
 *
 * TWO PASSES, DELIBERATELY.
 *
 * 1. {@see onException} catches an {@see ApiProblemException} — the only
 *    failures that know their own code, retryability and details — and answers
 *    it verbatim. Priority 512 puts it ahead of the firewall's exception
 *    listener and the framework's own, so the answer is not reshaped by
 *    somebody else's error document on the way out.
 * 2. {@see onResponse} is the safety net for everything else. It runs on the
 *    RESPONSE rather than the exception on purpose: by then the status is
 *    settled, and the status is the one thing this must not second-guess — a
 *    listener that decided statuses would be a second authorization system. It
 *    replaces the body and nothing else.
 *
 * IT LIVES IN THIS BUNDLE BECAUSE THE MACHINE DOOR DOES. The credential a field
 * client presents, the endpoint that mints one and the authenticator that reads
 * one are all here; the shape of a refusal at that door is the same subject.
 *
 * @see https://symfony.com/doc/current/reference/events.html#kernel-exception
 * @see vendor/symfony/http-kernel/EventListener/ErrorListener.php
 */
final class ApiErrorListener
{
    /** Marks a body this listener already wrote, so the second pass leaves it alone. */
    public const string HANDLED_HEADER = 'X-Uhifadhi-Api-Error';

    /**
     * Status → code, for failures raised outside anything that knows this
     * contract. `retryable` is derived from the status the same way: 429 and
     * 5xx are worth trying again, nothing else is.
     */
    private const array STATUS_CODES = [
        Response::HTTP_BAD_REQUEST => 'invalid_request',
        Response::HTTP_UNAUTHORIZED => 'unauthorized',
        Response::HTTP_FORBIDDEN => 'forbidden',
        Response::HTTP_NOT_FOUND => 'not_found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
        Response::HTTP_NOT_ACCEPTABLE => 'not_acceptable',
        Response::HTTP_CONFLICT => 'conflict',
        Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'unsupported_media_type',
        Response::HTTP_UNPROCESSABLE_ENTITY => 'invalid_payload',
        Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
    ];

    public function onException(ExceptionEvent $event): void
    {
        if (!self::isApiRequest($event->getRequest())) {
            return;
        }

        $problem = self::findApiProblem($event->getThrowable());
        if (!$problem instanceof ApiProblemException) {
            return;
        }

        $event->setResponse(self::render(
            $problem->getStatusCode(),
            $problem->getProblemCode(),
            $problem->getMessage(),
            $problem->isRetryable(),
            $problem->getDetails(),
        ));

        // Nothing downstream may re-render this: the codes are the contract.
        $event->stopPropagation();
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!self::isApiRequest($event->getRequest())
            || $response->getStatusCode() < 400
            || $response->headers->has(self::HANDLED_HEADER)
        ) {
            return;
        }

        $status = $response->getStatusCode();
        $code = self::STATUS_CODES[$status] ?? ($status >= 500 ? 'server_error' : 'request_failed');

        $event->setResponse(self::render(
            $status,
            $code,
            self::messageFor($response, $status),
            // Only 429 and 5xx are worth trying again.
            Response::HTTP_TOO_MANY_REQUESTS === $status || $status >= 500,
        ));
    }

    /**
     * Only the machine API. Every Twig page keeps its own error rendering — a
     * person meets a page, not a document — and so does anything an
     * installation mounts to describe the API to a browser.
     */
    private static function isApiRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return ('/api' === $path || str_starts_with($path, '/api/'))
            && !str_starts_with($path, '/api/docs');
    }

    /**
     * A problem thrown inside a service arrives WRAPPED — by the kernel when a
     * listener rethrows, or by whatever layer caught it — so the cause chain is
     * walked rather than only the outermost throwable.
     */
    private static function findApiProblem(\Throwable $throwable): ?ApiProblemException
    {
        for ($e = $throwable; null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof ApiProblemException) {
                return $e;
            }
        }

        return null;
    }

    /**
     * A readable line for whoever is looking at a sync screen. An error
     * response carries either a JSON document or an HTML page; the HTML is
     * never forwarded — it would put a stack trace in a field application — so
     * the status text stands in.
     */
    private static function messageFor(Response $response, int $status): string
    {
        $content = $response->getContent();
        if (\is_string($content) && str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $decoded = json_decode($content, true);
            if (\is_array($decoded)) {
                foreach (['detail', 'description', 'message', 'title'] as $key) {
                    if (isset($decoded[$key]) && \is_string($decoded[$key]) && '' !== $decoded[$key]) {
                        return $decoded[$key];
                    }
                }
            }
        }

        return Response::$statusTexts[$status] ?? 'Request failed';
    }

    /**
     * The document, and the only place it is written.
     *
     * `details` is an OBJECT even when empty, so a client's parser meets one
     * shape rather than an object on some failures and an empty array on
     * others.
     *
     * @param array<string, mixed> $details
     */
    private static function render(int $status, string $code, string $message, bool $retryable, array $details = []): JsonResponse
    {
        $response = new JsonResponse([
            'code' => $code,
            'message' => $message,
            'retryable' => $retryable,
            'details' => (object) $details,
        ], $status);

        $response->headers->set(self::HANDLED_HEADER, '1');

        return $response;
    }
}
