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

namespace Uhifadhi\Bundle\TeamBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Uhifadhi\Bundle\TeamBundle\Api\State\MeProvider;

/**
 * `GET /api/me` — WHO THIS TOKEN BELONGS TO, AND WHAT THE ACCOUNT MAY DO.
 *
 * The same facts sign-in already answered, asked again — because months pass
 * between sign-ins and a permission granted in the web app has to reach a handset
 * without a sign-out, a re-install or any ceremony. A client calls it at the start
 * of every sync run and from the controls it offers somebody who has been
 * refused, so it is read far more often than it is interesting.
 *
 * IT LIVES IN THIS BUNDLE BECAUSE THE ACCOUNT DOES. The person, their position,
 * their tier and the permission catalogue are all here, and so is the credential
 * the caller presented. This bundle still learns nothing about what any permission
 * MEANS: the whole set crosses and a client reads the one member it understands.
 *
 * THE CLASS IS FOUND WITHOUT REGISTRATION. `ApiResource/` in a registered bundle
 * is a mapped resource path, read off `kernel.bundles_metadata` — so neither this
 * bundle nor an installation writes a `mapping.paths` line, and writing one would
 * DISABLE the project-dir defaults an installation's own resources rely on.
 *
 * @see https://api-platform.com/docs/symfony/state-providers/
 * @see vendor/api-platform/core/src/Symfony/Bundle/DependencyInjection/ApiPlatformExtension.php — `getBundlesResourcesPaths()` adds "<bundle path>/ApiResource"
 */
#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(
            uriTemplate: '/me',
            description: 'The bearer account and the permissions it holds, re-read on every sync.',
            provider: MeProvider::class,
        ),
    ],
)]
final class Me
{
    /**
     * HAND-BUILT ARRAYS, as everywhere on this wire: these key names are an
     * external contract a released client reads, and an array says exactly what
     * goes across without depending on serializer naming conventions.
     *
     * @param array{id: string, name: string, role: string} $ranger
     * @param list<string>                                  $permissions every catalogue
     *                                                                   value this account holds. An EMPTY array is a
     *                                                                   REFUSAL and the field is never omitted — a
     *                                                                   missing one reads as "an older installation,
     *                                                                   therefore permitted"
     */
    public function __construct(
        public array $ranger = ['id' => '', 'name' => '', 'role' => ''],
        public array $permissions = [],
    ) {
    }
}
