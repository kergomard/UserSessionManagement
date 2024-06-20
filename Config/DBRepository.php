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

class DBRepository implements Repository
{
    public const CONFIG_TABLE = 'xusm_config';

    public function __construct(
        private \ilDBInterface $db
    ) {
    }

    public function get(): Config
    {
        return $this->buildFromDBValues(
            $this->db->fetchObject(
                $this->db->query('SELECT * FROM ' . self::CONFIG_TABLE . ' LIMIT 1')
            )
        );
    }

    public function store(Config $config): void
    {
        $this->db->manipulate('TRUNCATE TABLE ' . self::CONFIG_TABLE);
        $this->db->insert(
            self::CONFIG_TABLE,
            [
                'affected_roles' => [
                    \ilDBConstants::T_TEXT, implode(',', $config->getAffectedRoles())
                ],
                'relogin_validity' => [
                    \ilDBConstants::T_INTEGER, $config->getReloginValidity()
                ]
            ]
        );
    }

    private function buildFromDBValues(?\stdClass $values): Config
    {
        if ($values === null) {
            return new Config();
        }

        $affected_rows = $values->affected_roles === ''
            ? []
            : array_map(
                static fn(string $v): int => (int) $v,
                explode(',', $values->affected_roles)
            );

        return new Config(
            $affected_rows,
            $values->relogin_validity
        );
    }
}
