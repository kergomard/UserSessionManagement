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

namespace kergomard\UserSessionManagement;

use kergomard\UserSessionManagement\Config\DBRepository as ConfigDBRepository;
use kergomard\UserSessionManagement\Management\UserSessionDBRepository;
use kergomard\UserSessionManagement\Management\SessionsDataRetrieval;

use Pimple\Container as PimpleContainer;
use ILIAS\DI\Container as ILIASContainer;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Data\Factory as DataFactory;

class LocalDIC extends PimpleContainer
{
    public function __construct(ILIASContainer $DIC, array $values = [])
    {
        parent::__construct($values);

        $this['ilUser'] = static fn($c): \ilObjUser => $DIC['ilUser'];
        $this['ilAuthSession'] = static fn($c): \ilAuthSession => $DIC['ilAuthSession'];
        $this['il_ui_service'] = static fn($c): \ilUIService => $DIC->uiService();
        $this['tpl'] = static fn($c): \ilGlobalTemplateInterface => $DIC['tpl'];
        $this['ui.factory'] = static fn($c): UIFactory => $DIC['ui.factory'];
        $this['ui.renderer'] = static fn($c): UIRenderer => $DIC['ui.renderer'];
        $this['refinery'] = static fn($c): Refinery => $DIC['refinery'];
        $this['http'] = static fn($c): HTTPServices => $DIC['http'];
        $this['ilCtrl'] = static fn($c): \ilCtrl => $DIC['ilCtrl'];
        $this['lng'] = static fn($c): \ilLanguage => $DIC['lng'];
        $this['rbacreview'] = static fn($c): \ilRbacReview => $DIC['rbacreview'];
        $this['ilAccess'] = static fn($c): \ilAccessHandler => $DIC['ilAccess'];
        $this['ilLog'] = static fn($c): \ilLogger => $DIC['ilLog'];
        $this['ilTabs'] = static fn($c): \ilTabsGUI => $DIC['ilTabs'];
        $this['ilHelp'] = static fn($c): \ilHelpGUI => $DIC['ilHelp'];
        $this['ilLocator'] = static fn($c): \ilLocatorGUI => $DIC['ilLocator'];

        $this['config_repo'] = static fn($c): ConfigDBRepository
            => new ConfigDBRepository($DIC['ilDB']);
        $this['user_session_repo'] = static fn($c): UserSessionDBRepository
            => new UserSessionDBRepository($DIC['ilDB'], $c['ilUser']);
        $this['sessions_table_data_retriever'] = static fn($c): SessionsDataRetrieval
            => new SessionsDataRetrieval($c['user_session_repo']);
    }
}