<?php

/**
 * This file is part of the UserSessionsManagement plugin for ILIAS.
 * ILIAS is a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * UserSessionsManagement is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 *********************************************************************/

declare(strict_types=1);

namespace kergomard\UserSessionManagement\Config;

class Config
{
    private const ANCHOR_OBJECT_TYPES = ['crs'];
    private const HIDE_MANAGE_TAB = [
        'crs' => [
            'cmd_class' => [
                'ilcontainerstartobjects',
                'ilmailmembersearchgui'
            ],
            'cmd' => [
                'printMembers',
                'fullEditor',
                'printMembersOutput' // Passed as FallbackCmd
            ]
        ]
    ];


    public function __construct(
        private array $affected_roles = [],
        private int $relogin_validity = 5
    ) {
    }

    public function getAffectedRoles(): array
    {
        return $this->affected_roles;
    }

    public function getReloginValidity(): int
    {
        return $this->relogin_validity;
    }

    public function getAnchorObjectTypes(): array
    {
        return self::ANCHOR_OBJECT_TYPES;
    }

    public function getViewsWithHiddenManageTab(string $obj_type): array
    {
        if (array_key_exists($obj_type,self::HIDE_MANAGE_TAB)) {
            return self::HIDE_MANAGE_TAB[$obj_type];
        }
        return [];
    }
}
