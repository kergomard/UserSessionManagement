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

class UserSessionDBRepository implements UserSessionRepository
{
    public const TABLE_NAME_USM_SESSION_DATA = 'xusm_sessions';
    public const TABLE_NAME_ILIAS_SESSION_DATA = 'usr_session';

    private array $session_data = [];

    public function __construct(
        private readonly \ilDBInterface $db
    ) {
    }

    public function preloadDataForUserIds(
        array $user_ids
    ): void {
        if ($user_ids === []) {
            return;
        }

        $query = $this->db->query(
            'SELECT u.*, i.expires, i.user_id as i_user_id' . PHP_EOL
                . 'FROM ' . self::TABLE_NAME_USM_SESSION_DATA . ' u' . PHP_EOL
                . 'LEFT JOIN ' . self::TABLE_NAME_ILIAS_SESSION_DATA . ' i' . PHP_EOL
                . 'ON u.session_id = i.session_id' . PHP_EOL
                . 'WHERE ' . $this->db->in(
                    'u.user_id',
                    $user_ids,
                    false,
                    \ilDBConstants::T_INTEGER
                )
        );
        while ($session = $this->db->fetchObject($query)) {
            $this->session_data[$session->user_id] = $this->buildSessionFromDBRow(
                $session
            );
        }

        $this->completeSessionData($user_ids);
    }

    public function getSessionForUserId(
        int $user_id
    ): ?Session {
        if (array_key_exists($user_id, $this->session_data)) {
            return $this->session_data[$user_id];
        }

        $session_data = $this->db->fetchObject(
            $this->db->query(
                'SELECT u.*, i.expires, i.user_id as i_user_id' . PHP_EOL
                . 'FROM ' . self::TABLE_NAME_USM_SESSION_DATA . ' u' . PHP_EOL
                . 'LEFT JOIN ' . self::TABLE_NAME_ILIAS_SESSION_DATA . ' i' . PHP_EOL
                . 'ON u.session_id = i.session_id' . PHP_EOL
                . 'WHERE u.user_id = ' . $this->db->quote(
                    $user_id,
                    \ilDBConstants::T_INTEGER
                )
            )
        );

        if ($session_data === null) {
            return $this->buildEmptySession($user_id);
        }

        return $this->buildSessionFromDBRow($session_data);
    }

    public function storeSession(
        Session $session
    ): void {
        $this->db->replace(
            self::TABLE_NAME_USM_SESSION_DATA,
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
            'UPDATE ' . self::TABLE_NAME_USM_SESSION_DATA . ' SET '
                . ' relogin_allowed_until= ' . $until
                . ' WHERE ' . $this->db->in('user_id', $user_ids, false, \ilDBConstants::T_INTEGER)
        );
    }

    private function buildSessionFromDBRow(
        \stdClass $row
    ): Session {
        return new Session(
            $row->user_id,
            $row->session_id,
            $row->last_login_ip,
            $row->relogin_allowed_until,
            $row->expires,
            $row->i_user_id
        );
    }

    private function buildEmptySession(
        int $user_id
    ): Session {
        return $this->session_data[$user_id] = new Session($user_id);
    }

    private function completeSessionData(
        array $user_ids
    ): void {
        foreach ($user_ids as $user_id) {
            if (!array_key_exists($user_id, $this->session_data)) {
                $this->session_data[$user_id] = $this->buildEmptySession($user_id);
            }
        }
    }
}

