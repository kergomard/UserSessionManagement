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

namespace kergomard\UserSessionManagement\Management;

use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\DataRow;

class UserSessionDBRepository implements UserSessionRepository
{
    public const TABLE_NAME_SESSIONS = 'xusm_sessions';

    private array $session_data = [];

    public function __construct(
        private readonly \ilDBInterface $db,
        private readonly \ilObjUser $user
    ) {
    }

    public function preloadDataForUserIds(array $user_ids): void
    {
        if ($user_ids === []) {
            return;
        }

        $query = $this->db->query(
            'SELECT * FROM ' . self::TABLE_NAME_SESSIONS .
                ' WHERE ' . $this->db->in('user_id', $user_ids, false, \ilDBConstants::T_INTEGER)
        );

        while ($session = $query->fetchObject()) {
            $this->session_data[$session->user_id] = $session;
        }
    }

    public function getSessionForUserId(int $user_id): ?Session
    {
        if (!array_key_exists($user_id, $this->session_data)) {
            $this->session_data[$user_id] = $this->db->fetchObject(
                $this->db->query(
                    'SELECT * FROM ' . self::TABLE_NAME_SESSIONS .
                        ' WHERE user_id = ' . $this->db->quote($user_id, \ilDBConstants::T_INTEGER)
                )
            );
        }

        if ($this->session_data[$user_id] === null) {
            return null;
        }

        return $this->buildSessionFromDBRow($this->session_data[$user_id]);
    }

    public function getTableRowForUser(
        DataRowBuilder $row_builder,
        array $user
    ): DataRow {
        $session = $this->getSessionForUserId($user['usr_id']);

        $row_data = [
            ManagementGUI::ROW_ID => $user['usr_id'],
            ManagementGUI::COLUMN_FIRST_NAME => $user['firstname'],
            ManagementGUI::COLUMN_LAST_NAME => $user['lastname'],
            ManagementGUI::COLUMN_EMAIL => $user['email'],
            ManagementGUI::COLUMN_LOGGED_IN => false
        ];

        if ($session === null) {
            return $row_builder->buildDataRow(
                (string) $user['usr_id'],
                $row_data
            )->withDisabledAction(ManagementGUI::ACTION_STRING);
        }

        $row_data[ManagementGUI::COLUMN_LAST_LOGIN_IP] = $session->getLoginIp();

        if (\ilSession::lookupExpireTime($session->getSessionId()) > time()) {
            $row_data[ManagementGUI::COLUMN_LOGGED_IN] = true;
        }

        if ($session->getReloginAllowedUntil() !== null
            && $session->getReloginAllowedUntil() > time()) {
            $row_data[ManagementGUI::COLUMN_RELOING_AUTHORIZED_UNTIL] = (new \DateTimeImmutable(
                '@' . $session->getReloginAllowedUntil()
            ))->setTimezone(new \DateTimeZone($this->user->getTimeZone()));
        }

        $row = $row_builder->buildDataRow(
                (string) $user['usr_id'],
                $row_data
            );

        if (array_key_exists(ManagementGUI::COLUMN_RELOING_AUTHORIZED_UNTIL, $row_data)) {
            return $row->withDisabledAction(ManagementGUI::ACTION_STRING);
        }

        return $row;
    }

    public function storeSession(Session $session): void
    {
        $this->db->replace(
            self::TABLE_NAME_SESSIONS,
            ['user_id' => [\ilDBConstants::T_INTEGER, $session->getUserId()]],
            [
                'session_id' => [\ilDBConstants::T_TEXT, $session->getSessionId()],
                'last_login_ip' => [\ilDBConstants::T_TEXT, $session->getLoginIp()],
                'relogin_allowed_until' => [\ilDBConstants::T_INTEGER, $session->getReloginAllowedUntil()]
            ]
        );
    }

    public function reauthorizeLoginForUsers(
        array $user_ids,
        int $until
    ): void {
        $this->db->manipulate(
            'UPDATE ' . self::TABLE_NAME_SESSIONS . ' SET '
                . ' relogin_allowed_until= ' . $until
                . ' WHERE ' . $this->db->in('user_id', $user_ids, false, \ilDBConstants::T_INTEGER)
        );
    }

    private function buildSessionFromDBRow(\stdClass $row): Session
    {
        return new Session(
            $row->user_id,
            $row->session_id,
            $row->last_login_ip,
            $row->relogin_allowed_until
        );
    }
}

