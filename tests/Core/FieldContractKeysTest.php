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

namespace Uhifadhi\Core\Tests\Core;

/**
 * THE TWO DOCUMENTS, KEY BY KEY, AGAINST THE FIELD CONTRACT.
 *
 * A released handset reads these exact names and cannot be redeployed because a
 * serializer was reconfigured, a property was renamed, or a format was added to
 * the URL space. The endpoints' own suites assert what each field MEANS; this one
 * asserts only the NAMES and their ORDER, in one place, so the contract can be
 * read off a single file.
 *
 * NOTHING EXTRA IS TOLERATED EITHER. A stray `@context` or `@id` is the exact
 * failure a JSON-LD default would cause, and it would reach a client as an
 * unparseable document rather than as a build failure — so the assertions are on
 * the whole key list and not on the presence of the keys that are wanted.
 */
final class FieldContractKeysTest extends FieldApiTestCase
{
    /** `GET /api/me`: the account, and the permissions it holds. */
    private const array ME = ['ranger', 'permissions'];

    /** The account, as every document that names one spells it. */
    private const array RANGER = ['id', 'name', 'role'];

    /** `GET /api/areas/mine`: one key, whose list is the offline cache. */
    private const array AREAS_MINE = ['areas'];

    private const array AREA = ['id', 'name', 'areaKm2', 'stations', 'team', 'boundary'];

    private const array TEAM_MEMBER = ['id', 'name'];

    public function testTheAccountDocumentCarriesExactlyTheContractsKeys(): void
    {
        $body = $this->get('/api/me', $this->tokenFor($this->ranger()));

        self::assertSame(self::ME, array_keys($body));
        self::assertSame(self::RANGER, array_keys(self::nested($body, 'ranger')));
    }

    public function testTheAreasDocumentCarriesExactlyTheContractsKeys(): void
    {
        $this->area('Northern Conservation Reserve');
        $this->officeStaff('Naomi', 'Kileo');

        $body = $this->get('/api/areas/mine', $this->tokenFor($this->ranger()));

        self::assertSame(self::AREAS_MINE, array_keys($body));

        $area = self::nested($body, 'areas', 0);
        self::assertSame(self::AREA, array_keys($area));
        self::assertSame(self::TEAM_MEMBER, array_keys(self::nested($area, 'team', 0)));
    }

    /**
     * ONE FORMAT ON THIS URL SPACE. JSON-LD would answer the same resources with
     * `@context` and `@id` members, which is a document no client here parses —
     * so a request that asks for it is refused rather than served something the
     * contract does not describe.
     */
    public function testTheApiSpeaksJsonAndNothingElse(): void
    {
        $token = $this->tokenFor($this->ranger());

        $this->client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertSame(406, $this->client->getResponse()->getStatusCode());
    }

    /** The answer is JSON whatever a client's Accept header happens to say. */
    public function testTheAnswerIsJsonForAClientThatAsksForAnything(): void
    {
        $token = $this->tokenFor($this->ranger());

        $this->client->request('GET', '/api/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => '*/*',
        ]);

        self::assertStringContainsString(
            'application/json',
            (string) $this->client->getResponse()->headers->get('Content-Type'),
        );
    }
}
