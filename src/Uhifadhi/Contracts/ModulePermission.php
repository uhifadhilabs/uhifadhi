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

namespace Uhifadhi\Contracts;

/**
 * One granular permission a module DECLARES to the host — never grants.
 *
 * The host folds declared permissions into its own catalogue (permission matrix,
 * voter), so an admin can assign them to positions; uninstalling the module
 * removes the declaration and the permission disappears with it. A declaration
 * carries no role and no default holders: installing a module must never hand
 * any existing user a new power. Enforcement direction stays clean both ways —
 * the module checks its own routes for the value it declares, and the host
 * alone decides who holds it.
 *
 * A DECLARATION CARRIES A SENTENCE, and it is required. The host's permission
 * matrix is a page of checkboxes, and a checkbox labelled "Verification ·
 * Tiebreak" tells an administrator which words the module chose, not what
 * ticking it lets somebody do. The umbrella and the action are a NAME; the
 * description is the ANSWER, and it is printed under the name wherever the
 * permission appears. It is a constructor argument with no default on purpose:
 * a default would make the sentence optional, an optional sentence is one most
 * modules will not write, and a matrix half of whose rows explain themselves is
 * a matrix an administrator stops reading. A module that has not thought about
 * the sentence does not compile.
 *
 * Write it as a sentence about the holder, in the product's voice: "Look at
 * what somebody else recorded and mark it settled", not "Grants tiebreak
 * access". It is read by the person deciding whether to hand this power over.
 */
final readonly class ModulePermission
{
    /**
     * @param string $value       the attribute checked at the module's routes,
     *                            namespaced by convention (e.g. "verification.tiebreak")
     * @param string $umbrella    catalogue group label shown in the host's
     *                            permission matrix (e.g. "Verification")
     * @param string $action      short action label within the umbrella (e.g. "Tiebreak")
     * @param string $description one sentence saying what holding this lets a
     *                            person do, printed under the name in the host's matrix
     */
    public function __construct(
        public string $value,
        public string $umbrella,
        public string $action,
        public string $description,
    ) {
        // Refused rather than stored: under the checkbox, a blank sentence and
        // no sentence are the same thing, and this is the one place the
        // difference can still be caught.
        if ('' === trim($description)) {
            throw new \InvalidArgumentException(\sprintf('The permission "%s" was declared without a description. Say in one sentence what holding it lets a person do — it is printed under the name in the host\'s permission matrix.', $value));
        }
    }
}
