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

class Session
{
    public function __construct(
        private int $user_id,
        private string $session_id = '',
        private string $login_ip = '',
        private ?int $relogin_allowed_until = null,
        private ?int $expiration_time = null,
        private ?int $user_id_from_session = null
    ) {
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getSessionId(): string
    {
        return $this->session_id;
    }

    public function getLoginIp(): string
    {
        return $this->login_ip;
    }

    public function getReloginAllowedUntil(): ?int
    {
        return $this->relogin_allowed_until;
    }

    public function isSessionActive(): bool
    {
        return $this->expiration_time !== null
            && $this->expiration_time > time()
            && $this->user_id === $this->user_id_from_session;
    }

    public function getAsTableRow(
        DataRowBuilder $row_builder,
        array $user_data,
        \DateTimeZone $current_user_timezone
    ): DataRow {
        $row_data = [
            ManagementGUI::ROW_ID => $this->user_id,
            ManagementGUI::COLUMN_FIRST_NAME => $user_data['firstname'],
            ManagementGUI::COLUMN_LAST_NAME => $user_data['lastname'],
            ManagementGUI::COLUMN_USERNAME => $user_data['login'],
            ManagementGUI::COLUMN_EMAIL => $user_data['email'],
            ManagementGUI::COLUMN_LOGGED_IN => false
        ];

        if ($user_data['last_login'] !== null) {
            $row_data[ManagementGUI::COLUMN_LAST_LOG_IN] = (new \DateTimeImmutable(
                $user_data['last_login']
            ))->setTimezone($current_user_timezone);
        }

        if ($this->session_id === '') {
            return $row_builder->buildDataRow(
                (string) $user_data['usr_id'],
                $row_data
            )->withDisabledAction(ManagementGUI::ACTION_STRING);
        }

        $row_data[ManagementGUI::COLUMN_LAST_LOGIN_IP] = $this->login_ip;

        if ($this->isSessionActive()) {
            $row_data[ManagementGUI::COLUMN_LOGGED_IN] = true;
        }

        if ($this->relogin_allowed_until !== null
            && $this->relogin_allowed_until > time()) {
            $row_data[ManagementGUI::COLUMN_RELOING_AUTHORIZED_UNTIL] = (new \DateTimeImmutable(
                '@' . $this->relogin_allowed_until
            ))->setTimezone($current_user_timezone);
        }

        $row = $row_builder->buildDataRow(
                (string) $user_data['usr_id'],
                $row_data
            );

        if (!$row_data[ManagementGUI::COLUMN_LOGGED_IN]) {
            return $row->withDisabledAction(ManagementGUI::ACTION_STRING);
        }

        return $row;
    }
}
