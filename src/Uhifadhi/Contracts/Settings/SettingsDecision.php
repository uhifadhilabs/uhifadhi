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
 * ONE THING ABOUT THE INSTALLATION THAT NEEDS SOMEBODY TO DECIDE.
 *
 * NOT THE OPERATION — the installation. A ranger who has not checked in is
 * the organization dashboard's business; a module one release behind, three
 * areas registered and empty, an organization with no mark set are this
 * screen's. The test is whose decision it is: an operator's, or the person
 * who owns the installation.
 *
 * IT SAYS WHAT IS TRUE, THEN WHAT FOLLOWS. `$headline` is the fact in one
 * sentence and `$detail` is the consequence of leaving it — a row that said
 * only "Incidents is behind" leaves the reader to guess whether it matters.
 *
 * WHERE IT GOES IS THE SOURCE'S TO SAY. A row nobody can act on from here is
 * a row that should not be here, so `$url` is a route the source generated;
 * a source that cannot generate one leaves it null and the row is still read,
 * just not followed.
 */
final readonly class SettingsDecision
{
    /**
     * @param string      $key      stable, and what a test names this row by
     * @param string      $headline the fact, in one sentence
     * @param string      $detail   what follows from leaving it
     * @param string      $origin   whose fact it is — "installation", "organization"
     * @param string      $subject  what it is about — a package name, "3 areas"
     * @param string      $since    when it became true, in the reader's words
     * @param string      $age      how long it has been true — "2 d", "—"
     * @param string|null $url      where to act on it, or null
     */
    public function __construct(
        public string $key,
        public DecisionUrgency $urgency,
        public string $headline,
        public string $detail,
        public string $origin,
        public string $subject,
        public string $since,
        public string $age,
        public ?string $url = null,
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A decision is named by its key: it cannot be empty.');
        }

        if ('' === trim($headline)) {
            throw new \InvalidArgumentException(\sprintf('The "%s" decision says nothing: it cannot have an empty headline.', $key));
        }
    }
}
