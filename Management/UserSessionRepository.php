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

namespace kergomard\UserSessionManagement\Management;

use ILIAS\UI\Implementation\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\DataRow;

interface UserSessionRepository
{
    public function preloadDataForUserIds(array $user_ids): void;
    public function getSessionForUserId(int $user_id): ?Session;
    public function getTableRowForUser(
        DataRowBuilder $row_builder,
        array $user,
    ): DataRow;
    public function storeSession(Session $session): void;
    /**
     * @param array<int> $user_ids
     */
    public function reauthorizeLoginForUsers(array $user_ids, int $until): void;
}

