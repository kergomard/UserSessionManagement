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

use kergomard\UserSessionManagement\Config\Config;
use kergomard\UserSessionManagement\Management\ManagementGUI;

class ilUserSessionManagementUIHookGUI extends ilUIHookPluginGUI
{
    private const STANDARD_BASE_CLASS = ilUIPLuginRouterGUI::class;

    private Config $config;

    private ilCtrl $ctrl;
    private ilAccessHandler $access;

    private ?int $ref_id = null;

    public function modifyGUI(
        string $component,
        string $part,
        array $params = []
    ): void {
        if ($part === 'tabs'
            && $this->deferredInit()
            && $this->needsManageTab()) {
            $params['tabs']->addTab(
                ManagementGUI::TAB_ID,
                $this->getPluginObject()->txt(ManagementGUI::TAB_ID),
                $this->ctrl->getLinkTargetByClass([
                    self::STANDARD_BASE_CLASS,
                    ManagementGUI::class
                ])
            );

            if (array_key_exists(ManagementGUI::TAB_ID, $this->ctrl->getParameterArrayByClass(ManagementGUI::class)))
            {
                $params['tabs']->activateTab(ManagementGUI::TAB_ID);
                $this->ctrl->clearParameterByClass(ManagementGUI::class, ManagementGUI::TAB_ID);
            }
        }
    }

    private function deferredInit(): bool
    {
        $local_dic = $this->getPluginObject()->getLocalDIC();
        $this->config = $this->getPluginObject()->getConfig();
        $this->ctrl = $local_dic['ilCtrl'];
        $this->access = $local_dic['ilAccess'];
        $query = $local_dic['http']->wrapper()->query();
        $refinery = $local_dic['refinery'];

        if ($query->has('ref_id')) {
            $this->ref_id = $query->retrieve('ref_id', $refinery->kindlyTo()->int());
            $this->ctrl->setParameterByClass(ManagementGUI::class, 'ref_id', $this->ref_id);
            return true;
        }

        return false;
    }

    private function needsManageTab(): bool
    {
        $obj_type = ilObject::_lookupType(
            ilObject::_lookupObjectId($this->ref_id)
        );

        if ($this->access->checkAccess('write', '', $this->ref_id, 'crs')
            && in_array($obj_type, $this->config->getAnchorObjectTypes())
            && $this->viewHasManageTab($obj_type)) {
            return true;
        }

        return false;
    }

    private function viewHasManageTab(string $obj_type): bool
    {
        $views_with_hidden_manage_tab = $this->config->getViewsWithHiddenManageTab($obj_type);

        if (is_array($views_with_hidden_manage_tab['cmd_class'])
                && in_array($this->ctrl->getCmdClass(), $views_with_hidden_manage_tab['cmd_class'])
            || is_array($views_with_hidden_manage_tab['cmd'])
                && in_array($this->ctrl->getCmd(), $views_with_hidden_manage_tab['cmd'])) {
            return false;
        }

        return true;
    }
}

