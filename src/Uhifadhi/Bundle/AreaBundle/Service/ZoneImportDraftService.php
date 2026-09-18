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

namespace Uhifadhi\Bundle\AreaBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Model\ZoneImportPlan;

/**
 * WHAT THE PERSON IS LOOKING AT BETWEEN THE UPLOAD AND THE CONFIRM.
 *
 * THE FILE IS GONE BY THEN, and that is the ruling rather than an accident: a
 * document is read to geometry and let go, and nothing writes a copy of it
 * anywhere. So what crosses from the preview request to the confirm request is
 * the PLAN — names, rings, verdicts — held for this one person, in their own
 * session, and dropped the moment it is used.
 *
 * ONE DRAFT PER AREA. Somebody previewing a file for one area and then opening
 * another must not find the first area's features waiting for them, so the key
 * carries the area's uuid.
 *
 * THE OUTCOME IS ALSO A ONE-SHOT. A confirm answers with a redirect — otherwise
 * a refresh imports the file again — so the sentence the page then prints has
 * to survive exactly one request and no more.
 */
final readonly class ZoneImportDraftService
{
    private const string PLAN = 'area.zones.plan.';
    private const string REFUSAL = 'area.zones.refusal.';
    private const string OUTCOME = 'area.zones.outcome.';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function holdPlan(AreaOfInterest $area, ZoneImportPlan $plan): void
    {
        $this->put(self::PLAN.$area->getUuidString(), $plan);
    }

    public function plan(AreaOfInterest $area): ?ZoneImportPlan
    {
        $held = $this->peek(self::PLAN.$area->getUuidString());

        return $held instanceof ZoneImportPlan ? $held : null;
    }

    public function dropPlan(AreaOfInterest $area): void
    {
        $this->requests->getSession()->remove(self::PLAN.$area->getUuidString());
    }

    /**
     * A WHOLE FILE THAT COULD NOT BE READ. The name is kept beside the reason
     * because the card shows the file it refused, and "a file was refused" with
     * no filename is a sentence about nothing.
     */
    public function holdRefusal(AreaOfInterest $area, string $fileName, string $why): void
    {
        $this->put(self::REFUSAL.$area->getUuidString(), ['file' => $fileName, 'why' => $why]);
    }

    /** @return array{file: string, why: string}|null */
    public function takeRefusal(AreaOfInterest $area): ?array
    {
        $held = $this->take(self::REFUSAL.$area->getUuidString());

        return \is_array($held) && \is_string($held['file'] ?? null) && \is_string($held['why'] ?? null)
            ? ['file' => $held['file'], 'why' => $held['why']]
            : null;
    }

    /**
     * @param list<string> $lines what the card states under the heading
     */
    public function holdOutcome(AreaOfInterest $area, string $headline, array $lines = []): void
    {
        $this->put(self::OUTCOME.$area->getUuidString(), ['headline' => $headline, 'lines' => $lines]);
    }

    /** @return array{headline: string, lines: list<string>}|null */
    public function takeOutcome(AreaOfInterest $area): ?array
    {
        $held = $this->take(self::OUTCOME.$area->getUuidString());
        if (!\is_array($held) || !\is_string($held['headline'] ?? null)) {
            return null;
        }

        $lines = $held['lines'] ?? [];

        return ['headline' => $held['headline'], 'lines' => \is_array($lines) ? array_values(array_filter($lines, \is_string(...))) : []];
    }

    private function put(string $key, mixed $value): void
    {
        $this->requests->getSession()->set($key, $value);
    }

    private function peek(string $key): mixed
    {
        return $this->requests->getSession()->get($key);
    }

    private function take(string $key): mixed
    {
        $session = $this->requests->getSession();
        $held = $session->get($key);
        $session->remove($key);

        return $held;
    }
}
