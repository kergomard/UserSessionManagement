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

}