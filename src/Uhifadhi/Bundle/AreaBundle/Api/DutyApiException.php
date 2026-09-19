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

namespace Uhifadhi\Bundle\AreaBundle\Api;

/**
 * A REFUSAL THE HANDSET HAS A RULE FOR — API-CONTRACT.md §10.
 *
 * THE CODE IS THE CONTRACT, not the sentence. The app branches on
 * `code` and shows `message`, so a refusal whose code the app does not
 * know is a refusal it cannot act on: every one raised here is a code
 * §13 names, and the status is what decides whether the phone retries
 * for ever or parks the item and tells somebody.
 *
 * PERMANENT MEANS PERMANENT. A body naming a status this area does not
 * offer will be just as wrong on the tenth attempt, so it is a 422 and
 * the app stops. A transport failure is the retryable case and never
 * reaches this class at all.
 */
final class DutyApiException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        // NAMED AWAY FROM THE PARENT'S OWN `$code`, which is an int and
        // is not this: a contract code is a word the app branches on.
        private readonly int $statusCode,
        private readonly string $problemCode,
        string $message,
        private readonly array $details = [],
        private readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public static function unauthorized(): self
    {
        return new self(401, 'unauthorized', 'Sign in again.');
    }

    public static function forbidden(): self
    {
        return new self(403, 'forbidden', 'This account may not report a day.');
    }

    public static function unknownArea(string $uuid): self
    {
        return new self(404, 'unknown_area', 'No area of this installation has that identifier.', ['areaUuid' => $uuid]);
    }

    public static function unknownCheckIn(string $clientRef): self
    {
        return new self(404, 'unknown_checkin', 'No check-in of this area carries that reference.', ['clientRef' => $clientRef]);
    }

    /** A status this area does not offer, or has switched off. */
    public static function unsupportedStatus(string $status): self
    {
        return new self(422, 'unsupported_status', \sprintf('This area does not offer the status "%s".', $status), ['status' => $status]);
    }

    public static function unknownStation(string $uuid): self
    {
        return new self(422, 'unknown_station', 'No post of this area carries that identifier.', ['stationUuid' => $uuid]);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function invalidPayload(string $message, array $details = []): self
    {
        return new self(422, 'invalid_payload', $message, $details);
    }

    /**
     * @param array<string, mixed> $details
     */
    public static function invalidGeometry(string $message, array $details = []): self
    {
        return new self(422, 'invalid_geometry', $message, $details);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->problemCode,
            'message' => $this->getMessage(),
            'retryable' => $this->retryable,
            'details' => $this->details,
        ];
    }
}
