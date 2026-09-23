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

use ILIAS\Data\DateFormat\DateFormat;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\DataRow;

class Session
{
    public function __construct(
        private readonly int $user_id,
        private readonly bool $unrestricted_user,
        private readonly string $session_id = '',
        private readonly string $login_ip = '',
        private readonly ?int $relogin_allowed_until = null,
        private readonly ?int $expiration_time = null,
        private readonly ?int $user_id_from_session = null
    ) {
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function isUserUnrestricted(): bool
    {
        return $this->unrestricted_user;
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
        \ilPlugin $pl,
        DataRowBuilder $row_builder,
        array $user_data,
        \DateTimeZone $current_user_timezone,
        DateFormat $current_user_date_format
    ): DataRow {
        $row_data = [
            ManagementGUI::ROW_ID => $this->user_id,
            ManagementGUI::COLUMN_FIRST_NAME => $user_data['firstname'],
            ManagementGUI::COLUMN_LAST_NAME => $user_data['lastname'],
            ManagementGUI::COLUMN_USERNAME => $user_data['login'],
            ManagementGUI::COLUMN_EMAIL => $user_data['email'],
        ];

        if ($user_data['last_login'] !== null) {
            $row_data[ManagementGUI::COLUMN_LAST_LOG_IN] = (new \DateTimeImmutable(
                $user_data['last_login']
            ))->setTimezone($current_user_timezone);
        }

        if ($this->session_id !== '') {
            $row_data[ManagementGUI::COLUMN_LAST_LOGIN_IP] = $this->login_ip;
        }



        $logged_in_value = $this->buildLoggedInColumnValue();
        if ($logged_in_value !== null) {
            $row_data[ManagementGUI::COLUMN_LOGGED_IN] = $logged_in_value;
        }

        $relogin_value = $this->buildReloginUntilColumnValue(
            $pl,
            $current_user_timezone,
            $current_user_date_format
        );
        if ($relogin_value !== null) {
            $row_data[ManagementGUI::COLUMN_RELOING_AUTHORIZED_UNTIL] = $relogin_value;
        }

        $row = $row_builder->buildDataRow(
                (string) $user_data['usr_id'],
                $row_data
            );

        if ($this->unrestricted_user || !$row_data[ManagementGUI::COLUMN_LOGGED_IN]) {
            return $row->withDisabledAction(ManagementGUI::ACTION_STRING);
        }

        return $row;
    }

    private function buildLoggedInColumnValue(): ?bool
    {
        if ($this->unrestricted_user) {
            return null;
        }

        if ($this->isSessionActive()) {
            return true;
        }

        return false;
    }

    private function buildReloginUntilColumnValue(
        \ilPlugin $pl,
        \DateTimeZone $current_user_timezone,
        DateFormat $current_user_date_format
    ): ?string {
        if ($this->unrestricted_user) {
            return $pl->txt('unrestricted_user');
        }

        if ($this->relogin_allowed_until !== null
            && $this->relogin_allowed_until > time()) {
            return (new \DateTimeImmutable(
                '@' . $this->relogin_allowed_until
            ))->setTimezone($current_user_timezone)
            ->format($current_user_date_format->toString());
        }

        return null;
    }
}
