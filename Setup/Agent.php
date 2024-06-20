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

use ILIAS\Setup\Agent\NullAgent;
use ILIAS\Setup\Objective;
use ILIAS\Setup\Metrics\Storage;
use ILIAS\Setup;
use ILIAS\Setup\Config;

class Agent extends NullAgent
{
    use Setup\Agent\HasNoNamedObjective;

    public function getUpdateObjective(Config $config = null): Objective
    {
        return new \ilDatabaseUpdateStepsExecutedObjective(new DBUpdateSteps());
    }

    public function getStatusObjective(Storage $storage): Objective
    {
        return new \ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new DBUpdateSteps());
    }
}
