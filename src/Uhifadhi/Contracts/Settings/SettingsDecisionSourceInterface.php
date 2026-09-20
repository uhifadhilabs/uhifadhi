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

namespace Uhifadhi\Contracts\Settings;

/**
 * WHOEVER KNOWS THE INSTALLATION NEEDS A DECISION SAYS SO HERE.
 *
 * The queue is assembled, never authored, and it is sorted by urgency across
 * its sources rather than grouped by them: somebody reading it is asking
 * "what do I have to do", and a list arranged by which bundle noticed would
 * make them read all of it to find out.
 *
 * ONLY WHAT IS TRUE NOW. A source returns what it can see this request and
 * nothing it remembers; a decision somebody acted on this morning is gone
 * this morning, with no acknowledgement state to keep in step.
 *
 * Tag it by hand; see {@see SettingsFigureSourceInterface} on why.
 */
interface SettingsDecisionSourceInterface
{
    public const string TAG = 'shell.settings_decision';

    /**
     * @return iterable<SettingsDecision>
     */
    public function settingsDecisions(): iterable;
}
