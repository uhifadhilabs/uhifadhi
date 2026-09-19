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

namespace Uhifadhi\Bundle\AreaBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Uhifadhi\Bundle\AreaBundle\Api\State\CreateCheckInProcessor;
use Uhifadhi\Bundle\AreaBundle\Api\State\UpdateCheckInProcessor;
use Uhifadhi\Bundle\AreaBundle\Api\State\UploadPositionsProcessor;

/**
 * THE DAY A RANGER REPORTS — API-CONTRACT.md §13, the handset's side.
 *
 * WHY THE AREA OWNS THESE. A check-in is about standing on a piece of
 * ground at a post, and the ground, the posts and what "inside" one
 * means are this bundle's. A module that served them would be
 * answering for data it does not hold, and the roster module — which
 * does not exist yet — would have to exist before anybody could check
 * in at all.
 *
 * NO SECOND TRANSPORT AND NO SECOND IDENTITY. These are the patrol
 * sync's own shapes with the subject changed: a client-minted
 * reference, an upsert on it, the §5 part-ack for a batch, the §10
 * error document. A handset that learnt one has learnt both.
 *
 * NO INPUT DTO, for the reason the patrol resource gives: these bodies
 * are contract-specific and their rules are per-field — four position
 * fields that travel together, an instant to be trusted verbatim with
 * its offset, a status checked against what the area publishes. That
 * lives in {@see \Uhifadhi\Bundle\AreaBundle\Api\DutyPayload}, where
 * each refusal carries the code the app branches on. Entities are never
 * exposed; every response is written by hand in
 * {@see \Uhifadhi\Bundle\AreaBundle\Api\DutyResponse}.
 *
 * THE CLASS IS FOUND WITHOUT REGISTRATION: `ApiResource/` in a
 * registered bundle is a mapped resource path, read off
 * `kernel.bundles_metadata`.
 */
#[ApiResource(
    shortName: 'DutySync',
    operations: [
        new Post(
            uriTemplate: '/areas/{areaUuid}/checkins',
            status: 201,
            description: 'Report the day: one status, at one moment, with the post where the status names one and the position where there was a fix. Upserts by clientRef — a re-send returns the same claim with "duplicate": true and 200.',
            deserialize: false,
            validate: false,
            read: false,
            processor: CreateCheckInProcessor::class,
        ),
        new Patch(
            uriTemplate: '/areas/{areaUuid}/checkins/{clientRef}',
            status: 200,
            description: 'The check-out, a correction and the back-fill of a position that arrived after the tap. All three append; none of them rewrites the claim.',
            /*
             * PLAIN JSON ON A PATCH, NAMED HERE. `patch_formats` is a SETTING
             * OF ITS OWN and defaults to `application/merge-patch+json`
             * alone, so an installation that narrowed `formats` to JSON — as
             * the field contract requires — would still answer this operation
             * 415 for a body the handset sends as `application/json`. A
             * bundle cannot ask an installation to configure a third format
             * list to make its own endpoint reachable, so the operation
             * states what it accepts.
             *
             * @see vendor/api-platform/core/src/Symfony/Bundle/DependencyInjection/Configuration.php — the patch_formats default
             */
            inputFormats: ['json' => ['application/json']],
            deserialize: false,
            validate: false,
            read: false,
            processor: UpdateCheckInProcessor::class,
        ),
        new Post(
            uriTemplate: '/areas/{areaUuid}/positions',
            status: 200,
            description: 'The duty pings, as an array after an offline stretch. Answers the part-ack: acceptedUuids lists the clientRefs stored, and the phone deletes exactly those.',
            deserialize: false,
            validate: false,
            read: false,
            processor: UploadPositionsProcessor::class,
        ),
    ],
)]
final class DutySync
{
    /**
     * NOTHING IS SERIALIZED FROM THIS CLASS. It exists to carry the
     * operations above; every response on this surface is built by hand,
     * because these key names are an external contract a released client
     * reads.
     */
    private function __construct()
    {
    }
}
