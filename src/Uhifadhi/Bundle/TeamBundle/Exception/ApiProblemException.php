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

namespace Uhifadhi\Bundle\TeamBundle\Exception;

/**
 * A FAILURE A FIELD CLIENT IS EXPECTED TO UNDERSTAND.
 *
 * A client's whole failure policy keys off two things: the HTTP status and
 * `retryable`. Getting `retryable` wrong is worse than getting the message
 * wrong — a false `true` makes a handset retry a permanently broken request
 * forever, and a false `false` parks work that would have gone through. So it
 * is an explicit argument here and is never inferred at the edge by whoever is
 * rendering.
 *
 * IT IS THROWN, NOT RETURNED, because the thing that renders it sits on
 * kernel.exception ahead of everything else that would like to reshape an
 * error. A returned response would be one more body for the safety net to
 * second-guess; a thrown problem is answered verbatim and stops there.
 *
 * Named `problemCode` rather than `code`: \Exception already owns a `$code`
 * property and a final getCode(), so the contract's string code needs its own
 * name.
 */
final class ApiProblemException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details context a client can act on — the
     *                                      records it must re-queue, the field it must fix
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $problemCode,
        string $message,
        private readonly bool $retryable = false,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getProblemCode(): string
    {
        return $this->problemCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }
}
