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

/**
 * WHAT A WRITE ON THE STATIONS SECTION LEFT THE READER TO READ.
 *
 * EVERY WRITE ANSWERS WITH A REDIRECT, so a refresh cannot repeat it — which
 * means the sentence about what happened has to survive exactly one request
 * and no more. That is all this is: one refusal or one outcome, per area, per
 * person, taken by the page that draws it.
 *
 * A STORE AND NOT A SERVICE: it holds state between two requests and decides
 * nothing.
 */
final readonly class StationNoticeStore
{
    private const string REFUSAL = 'area.stations.refusal.';
    private const string OUTCOME = 'area.stations.outcome.';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    /**
     * A WRITE THAT DID NOT HAPPEN, AND WHY. The subject is kept beside the
     * reason because the card names what it refused, and "that was refused"
     * with no subject is a sentence about nothing.
     */
    public function holdRefusal(AreaOfInterest $area, string $subject, string $why): void
    {
        $this->requests->getSession()->set(self::REFUSAL.$area->getUuidString(), ['subject' => $subject, 'why' => $why]);
    }

    /** @return array{subject: string, why: string}|null */
    public function takeRefusal(AreaOfInterest $area): ?array
    {
        $held = $this->take(self::REFUSAL.$area->getUuidString());

        return \is_array($held) && \is_string($held['subject'] ?? null) && \is_string($held['why'] ?? null)
            ? ['subject' => $held['subject'], 'why' => $held['why']]
            : null;
    }

    public function holdOutcome(AreaOfInterest $area, string $headline): void
    {
        $this->requests->getSession()->set(self::OUTCOME.$area->getUuidString(), $headline);
    }

    public function takeOutcome(AreaOfInterest $area): ?string
    {
        $held = $this->take(self::OUTCOME.$area->getUuidString());

        return \is_string($held) ? $held : null;
    }

    private function take(string $key): mixed
    {
        $session = $this->requests->getSession();
        $held = $session->get($key);
        $session->remove($key);

        return $held;
    }
}
