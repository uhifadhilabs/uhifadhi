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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\AreaBundle\Entity\CheckIn;
use Uhifadhi\Contracts\Api\FieldErrorDocument;

/**
 * THE ENVELOPES §13 DRAWS, written by hand.
 *
 * NO ENTITY IS EVER SERIALIZED. These key names are an external
 * contract a released client reads; an array built here says exactly
 * what goes across, and a field added to an entity next month does not
 * silently appear on the wire.
 *
 * THE SERVER'S CLOCK CARRIES ITS OFFSET too, because everything else on
 * this surface does and a client comparing them must not have to guess
 * which one is naive.
 */
final class DutyResponse
{
    /** §13A and §13B: the claim's own reference, and whether this was a repeat. */
    public static function checkIn(CheckIn $checkIn, bool $duplicate, bool $created = false): JsonResponse
    {
        return new JsonResponse([
            'clientRef' => $checkIn->getClientRef(),
            'duplicate' => $duplicate,
            'serverTime' => self::now(),
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * §13C, the part-ack of §5: what was stored, so the phone deletes
     * exactly those and keeps the rest.
     *
     * @param list<string> $acceptedRefs
     */
    public static function partAck(array $acceptedRefs, bool $duplicate): JsonResponse
    {
        return new JsonResponse([
            'accepted' => true,
            'acceptedUuids' => $acceptedRefs,
            'duplicate' => $duplicate,
            'serverTime' => self::now(),
        ]);
    }

    /**
     * §10: one shape for every refusal, with the code the app branches on.
     *
     * MARKED AS ALREADY WRITTEN. The `/api` space gives every failure that
     * shape, and its safety net replaces the body of any 4xx it finds
     * unmarked — which would turn `unsupported_status` into the generic
     * `invalid_payload` its status maps to and drop the details with it. The
     * header says this document already IS the contract's, so the net leaves
     * it alone. Naming the header rather than copying its value keeps the two
     * from drifting.
     *
     * @see FieldErrorDocument — the name, and why it is not a bundle's
     */
    public static function refusal(DutyApiException $refusal): JsonResponse
    {
        $response = new JsonResponse($refusal->toArray(), $refusal->statusCode());
        $response->headers->set(FieldErrorDocument::HANDLED_HEADER, '1');

        return $response;
    }

    private static function now(): string
    {
        return new \DateTimeImmutable()->format(\DateTimeInterface::ATOM);
    }
}
