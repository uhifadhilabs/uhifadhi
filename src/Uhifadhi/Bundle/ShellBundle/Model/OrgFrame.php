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

namespace Uhifadhi\Bundle\ShellBundle\Model;

use Uhifadhi\Contracts\Shell\OrgPage;
use Uhifadhi\Contracts\Shell\Scope;

/**
 * EVERYTHING THE ORG BASE DRAWS AROUND AN ORGANIZATION-LEVEL SCREEN, read
 * once, on the render, for the page the request is on.
 *
 * ONE OBJECT AND ONE TWIG CALL, rather than four: the frame's trail, title,
 * strip and scope control all answer the same question — which module's
 * organization-level set is this, and how wide is it looking — and a
 * template that asked it four times could be given four different answers by
 * a later change.
 */
final readonly class OrgFrame
{
    /**
     * @param string        $name   what the module calls itself, for the trail and the head
     * @param OrgPage|null  $page   the screen the viewer is on, where the request is on one
     * @param list<AreaTab> $tabs   the module's mounted screens; empty where a strip would be one tab
     * @param list<Scope>   $scopes what this viewer may look at, the host's answer
     * @param Scope|null    $scope  the slice the page is drawn at, null where nothing is offered
     */
    public function __construct(
        public string $name,
        public ?OrgPage $page,
        public array $tabs,
        public array $scopes,
        public ?Scope $scope,
    ) {
    }
}
