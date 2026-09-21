<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
namespace Uhifadhi\Bundle\TeamBundle\Model;

use Uhifadhi\Contracts\Access\ScopeKind;
use Uhifadhi\Contracts\Access\Verb;

/**
 * ONE CONCERN, AS A POSITION HOLDS IT — a row of the matrix, on the record
 * and on the configure page and in the register's preview, all three read
 * from this one derivation.
 *
 * A CELL EXISTS ONLY WHERE THE CONCERN DECLARES THE VERB. {@see $cells} is
 * keyed by the verb's value and carries the verbs the concern supports and
 * no others, so a rendering that loops it cannot draw a box that would mean
 * nothing.
 */
final readonly class GrantRow
{
    /**
     * @param array<string, bool>     $cells      verb value => whether the position holds it
     * @param array<string, bool>     $scopeKinds scope kind value => whether the position allows it
     * @param list<Verb>              $verbs      the verbs the concern declares, in the fixed order
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public bool $sensitive,
        public ?string $ownWords,
        public array $verbs,
        public array $cells,
        public array $scopeKinds,
    ) {
    }

    /** Whether the position holds any verb at all on this concern. */
    public function isGranted(): bool
    {
        return \in_array(true, $this->cells, true);
    }

    /** @return list<Verb> the verbs actually held, for a summary line */
    public function heldVerbs(): array
    {
        return array_values(array_filter(
            $this->verbs,
            fn (Verb $verb): bool => $this->cells[$verb->value] ?? false,
        ));
    }

    /** @return list<ScopeKind> the kinds the concern offers, in the fixed order */
    public function offeredKinds(): array
    {
        return array_values(array_filter(
            ScopeKind::cases(),
            fn (ScopeKind $kind): bool => \array_key_exists($kind->value, $this->scopeKinds),
        ));
    }

    public function allows(ScopeKind $kind): bool
    {
        return $this->scopeKinds[$kind->value] ?? false;
    }
}
