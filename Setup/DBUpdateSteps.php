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

namespace kergomard\UserSessionManagement\Setup;

use kergomard\UserSessionManagement\Config\DBRepository as ConfigRepo;
use kergomard\UserSessionManagement\Management\UserSessionDBRepository;

class DBUpdateSteps implements \ilDatabaseUpdateSteps
{
    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    private function migrateAffectedRolesToUnrestrictedRoles(
        \stdClass $current_values
    ): void {
        $global_roles = array_map(
            fn (\stdClass $v): int => $v->rol_id,
            $this->db->fetchAll(
                $this->db->query(
                    'SELECT rol_id FROM rbac_fa WHERE parent = '
                    . ROLE_FOLDER_ID
                    . ' AND assign = "y"'

                ),
                \ilDBConstants::FETCHMODE_OBJECT
            )
        );

        $affected_roles = explode(
            ',',
            $current_values->affected_roles
        );

        $unrestricted_roles = array_diff($global_roles, $affected_roles);

        $this->db->manipulate('TRUNCATE TABLE ' . ConfigRepo::CONFIG_TABLE);
        $this->db->insert(
            ConfigRepo::CONFIG_TABLE,
            [
                'affected_roles' => [
                    \ilDBConstants::T_TEXT, $current_values->affected_roles
                ],
                'relogin_validity' => [
                    \ilDBConstants::T_INTEGER, $current_values->relogin_validity
                ],
                'unrestricted_roles' => [
                    \ilDBConstants::T_TEXT, implode(',', $unrestricted_roles)
                ]
            ]
        );
    }

    public function step_1(): void
    {
        if (!$this->db->tableExists(UserSessionDBRepository::TABLE_NAME_SESSIONS)) {
            $this->db->createTable(UserSessionDBRepository::TABLE_NAME_SESSIONS, [
                'user_id' => [
                    'type' => \ilDBConstants::T_INTEGER,
                    'length' => 8,
                    'notnull' => true
                ],
                'session_id' => [
                    'type' => \ilDBConstants::T_TEXT,
                    'length' => 256,
                    'notnull' => true
                ],
                'relogin_allowed_until' => [
                    'type' => \ilDBConstants::T_INTEGER,
                    'length' => 8
                ],
                'last_login_ip' => [
                    'type' => \ilDBConstants::T_TEXT,
                    'length' => 42,
                    'notnull' => true
                ]
            ]);
            $this->db->addPrimaryKey(
                UserSessionDBRepository::TABLE_NAME_SESSIONS,
                ['user_id']
            );
        }
        if (!$this->db->tableExists(ConfigRepo::CONFIG_TABLE)) {
            $this->db->createTable(ConfigRepo::CONFIG_TABLE, [
                'affected_roles' => [
                    'type' => \ilDBConstants::T_TEXT,
                    'length' => 512,
                    'notnull' => true
                ],
                'relogin_validity' => [
                    'type' => \ilDBConstants::T_INTEGER,
                    'length' => 8
                ]
            ]);
        }
    }

    public function step_2(): void
    {
        if (!$this->db->tableColumnExists(
            ConfigRepo::CONFIG_TABLE,
            'unrestricted_roles'
        )) {
            $this->db->addTableColumn(
                ConfigRepo::CONFIG_TABLE,
                'unrestricted_roles',
                [
                        'type' => \ilDBConstants::T_TEXT,
                        'length' => 512,
                        'notnull' => true
                ]
            );

            $current_values = $this->db->fetchObject(
                $this->db->query(
                    'SELECT * FROM ' . ConfigRepo::CONFIG_TABLE
                )
            );

            if ($current_values !== null) {
                $this->migrateAffectedRolesToUnrestrictedRoles($current_values);
            }

            $this->db->dropTableColumn(
                ConfigRepo::CONFIG_TABLE,
                'affected_roles'
            );
        }
    }
}