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

use Symfony\Component\HttpFoundation\Response;
use Uhifadhi\Bundle\TeamBundle\Enum\PermissionEnum;
use Uhifadhi\Bundle\TeamBundle\Enum\TeamRoleEnum;

/**
 * `GET /api/me` — who this token belongs to, and what the account may do.
 *
 * The same facts sign-in already answered, asked again: months pass between
 * sign-ins, and a permission granted in the web app has to reach the handset
 * without a sign-out or a re-install. A client calls it at the start of every
 * sync run and from the controls it offers somebody who has been refused, so it
 * is read far more often than it is interesting.
 *
 * AN EMPTY PERMISSION ARRAY IS A REFUSAL and a MISSING one reads as "an older
 * installation, therefore permitted". The field is therefore always sent, which
 * is asserted here and not merely intended.
 */
final class FieldAccountEndpointTest extends FieldApiTestCase
{
    private const string ENDPOINT = '/api/me';

    public function testNoTokenIsUnauthorized(): void
    {
        $this->get(self::ENDPOINT);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /** A refusal at this door is the one failure document everything under /api answers in. */
    public function testARefusalCarriesTheApiErrorDocument(): void
    {
        $body = $this->get(self::ENDPOINT);

        self::assertSame(['code', 'message', 'retryable', 'details'], array_keys($body));
        self::assertSame('unauthorized', self::leaf($body, 'code'));
        self::assertFalse(self::leaf($body, 'retryable'));
    }

    public function testATokenNamingNobodyIsUnauthorized(): void
    {
        $this->get(self::ENDPOINT, 'nothing-was-ever-issued-for-this');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testTheBearerAccountIsAnsweredInTheContractsExactDocument(): void
    {
        $ranger = $this->ranger();

        $body = $this->get(self::ENDPOINT, $this->tokenFor($ranger));

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertSame(['ranger', 'permissions'], array_keys($body));
        self::assertSame(
            ['id' => 'sl-0142', 'name' => 'Witness Mbise', 'role' => 'Field Ranger'],
            self::nested($body, 'ranger'),
        );
        self::assertSame([PermissionEnum::AreaView->value], self::nested($body, 'permissions'));
    }

    /**
     * `ranger.role` is load-bearing rather than cosmetic: a refusal screen names
     * the POSITION that lacks the permission, because that is what an
     * administrator has to change. Somebody with no position still has a role —
     * their tier — so the field is never blank.
     */
    public function testSomebodyWithNoPositionIsNamedByTheirTier(): void
    {
        $office = $this->officeStaff('Naomi', 'Kileo');

        $body = $this->get(self::ENDPOINT, $this->tokenFor($office));

        self::assertSame(TeamRoleEnum::Staff->label(), self::leaf($body, 'ranger', 'role'));
    }

    /**
     * The address stands in where there is no service number: office staff are
     * never issued one, and the identifier here is the one that comes back in
     * every roster and on every record.
     */
    public function testTheAddressIdentifiesSomebodyWithNoServiceNumber(): void
    {
        $office = $this->officeStaff('Naomi', 'Kileo');

        $body = $this->get(self::ENDPOINT, $this->tokenFor($office));

        self::assertSame('n.kileo@example.test', self::leaf($body, 'ranger', 'id'));
    }

    /** A refusal, said out loud: the field is present and empty, never absent. */
    public function testAnAccountHoldingNothingIsSentAnEmptyArrayRatherThanNoField(): void
    {
        $nobody = $this->ranger('sl-0777', permissions: []);

        $body = $this->get(self::ENDPOINT, $this->tokenFor($nobody));

        self::assertArrayHasKey('permissions', $body);
        self::assertSame([], self::nested($body, 'permissions'));
    }

    /**
     * THE WHOLE SET CROSSES, catalogue values and nothing else, and a client
     * reads the one member it understands. This installation learns nothing
     * about what any of them mean.
     */
    public function testEveryPermissionTheAccountHoldsIsSent(): void
    {
        $ranger = $this->ranger('sl-0143', [PermissionEnum::AreaView, PermissionEnum::ModuleView]);

        $body = $this->get(self::ENDPOINT, $this->tokenFor($ranger));

        self::assertSame(
            [PermissionEnum::AreaView->value, PermissionEnum::ModuleView->value],
            self::nested($body, 'permissions'),
        );
    }
}
