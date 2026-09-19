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
 * READING A HANDSET'S BODY, FIELD BY FIELD — API-CONTRACT.md §13.
 *
 * NO DTO LAYER, for the reason the patrol's own payload reader gives:
 * these rules are per-field and contract-specific, and the refusal each
 * raises carries the code the app has a rule for. A serializer cannot
 * express "trust this timestamp exactly, offset and all" or "these four
 * fields arrive together or not at all".
 *
 * AN INSTANT IS TRUSTED VERBATIM. The phone's clock is the record of
 * when something happened in the field, offset included; normalising it
 * to the server's zone would throw away the one fact a 06:00 watch
 * depends on.
 */
final class DutyPayload
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws DutyApiException
     */
    public static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === trim($value)) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" is required.', $key), ['field' => $key]);
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws DutyApiException
     */
    public static function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return null;
        }

        if (!\is_float($value) && !\is_int($value)) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" must be a number.', $key), ['field' => $key]);
        }

        return (float) $value;
    }

    /**
     * AN INSTANT WITH ITS OFFSET, kept exactly as it was sent.
     *
     * @param array<string, mixed> $data
     *
     * @throws DutyApiException
     */
    public static function timestamp(array $data, string $key): ?\DateTimeImmutable
    {
        $value = self::string($data, $key);
        if (null === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" is not an instant this server can read.', $key), ['field' => $key, 'value' => $value]);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws DutyApiException
     */
    public static function requiredTimestamp(array $data, string $key): \DateTimeImmutable
    {
        return self::timestamp($data, $key)
            ?? throw DutyApiException::invalidPayload(\sprintf('"%s" is required.', $key), ['field' => $key]);
    }

    /**
     * THE RANGER'S OWN DAY, and not derived from any instant: a 06:00
     * watch belongs to that date whatever the offset says.
     *
     * @param array<string, mixed> $data
     *
     * @throws DutyApiException
     */
    public static function localDate(array $data, string $key): \DateTimeImmutable
    {
        $value = self::requiredString($data, $key);
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" is a day, written 2026-09-19.', $key), ['field' => $key, 'value' => $value]);
        }

        return new \DateTimeImmutable($value.' 00:00:00');
    }

    /**
     * THE FIX, OR NOTHING AT ALL. The four fields travel together: a
     * latitude with no longitude is not half a position, and a position
     * with no clock of its own cannot be placed against a watch.
     *
     * `positionAt` is the exception it is allowed to be — the app sends
     * a fix that has not landed yet with a null clock and back-fills
     * later — so a position without it is read as "reported at the tap".
     *
     * @param array<string, mixed> $data
     *
     * @return array{point: string, at: \DateTimeImmutable|null, accuracy: float|null}|null
     *
     * @throws DutyApiException
     */
    public static function fix(array $data, \DateTimeImmutable $fallbackAt): ?array
    {
        $lat = self::float($data, 'lat');
        $lon = self::float($data, 'lon');

        if (null === $lat && null === $lon) {
            return null;
        }

        if (null === $lat || null === $lon) {
            throw DutyApiException::invalidGeometry('A position needs both a latitude and a longitude.', ['lat' => $lat, 'lon' => $lon]);
        }

        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            throw DutyApiException::invalidGeometry('That position is not on the planet.', ['lat' => $lat, 'lon' => $lon]);
        }

        return [
            // GeoJSON IS LON/LAT, RFC 7946 — the one ordering mistake that
            // puts a post an ocean away from the ground it stands on.
            'point' => \sprintf('{"type":"Point","coordinates":[%.8F,%.8F]}', $lon, $lat),
            'at' => self::timestamp($data, 'positionAt') ?? $fallbackAt,
            'accuracy' => self::float($data, 'accuracyM'),
        ];
    }

    /**
     * A LIST OF ROWS, or a refusal that names the field.
     *
     * AN ABSENT LIST IS AN EMPTY ONE, and this is not leniency: the
     * handset omits every null and every default rather than spelling
     * them, so a check-out with nothing to correct arrives with no
     * `corrections` member at all. Refusing that would refuse the
     * commonest write on the surface.
     *
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     *
     * @throws DutyApiException
     */
    public static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return [];
        }

        if (!\is_array($value)) {
            throw DutyApiException::invalidPayload(\sprintf('"%s" must be a list.', $key), ['field' => $key]);
        }

        $rows = [];
        foreach ($value as $row) {
            if (!\is_array($row)) {
                throw DutyApiException::invalidPayload(\sprintf('Every entry of "%s" must be an object.', $key), ['field' => $key]);
            }

            /** @var array<string, mixed> $row */
            $rows[] = $row;
        }

        return $rows;
    }
}
