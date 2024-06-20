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

class Session
{
    public function __construct(
        private int $user_id,
        private string $session_id,
        private string $login_ip,
        private ?int $relogin_allowed_until = null
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
}

