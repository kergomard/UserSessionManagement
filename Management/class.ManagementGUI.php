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

use kergomard\UserSessionManagement\Config\Config;

use Psr\Http\Message\ServerRequestInterface;
use ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\UI\Component\Input\Container\Filter\Standard as Filter;
use ILIAS\UI\Component\Table\Data as DataTable;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\Data\Factory as DataFactory;
use ILIAS\Data\DateFormat\DateFormat;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;

/**
 * @ilCtrl_isCalledBy kergomard\UserSessionManagement\Management\ManagementGUI: ilUIPluginRouterGUI
 * @ilCtrl_calls kergomard\UserSessionManagement\Management\ManagementGUI: ilObjCourseGUI, ilCourseContentGUI, ilCourseMembershipGUI
 */
class ManagementGUI
{
    public const TAB_ID = 'manage_sessions';
    public const FILTER_ID = 'manage_sessions_filter';
    public const ACTION_STRING = 'authorize_relogin';

    public const ROW_ID = 'usr_id';

    public const COLUMN_FIRST_NAME = 'firstname';
    public const COLUMN_LAST_NAME = 'lastname';
    public const COLUMN_EMAIL = 'email';
    public const COLUMN_LAST_LOGIN_IP = 'last_login_ip';
    public const COLUMN_LOGGED_IN = 'logged_in';
    public const COLUMN_RELOING_AUTHORIZED_UNTIL = 'relogin_authorized_until';

    private \ilUserSessionManagementPlugin $pl;
    private Config $config;
    private UserSessionRepository $user_session_repo;
    private SessionsDataRetrieval $sessions_table_data_retriever;

    private \ilTabsGUI $tabs_gui;
    private \ilHelpGUI $help;
    private \ilLocatorGUI $locator;
    private \ilComponentFactory $component_factory;
    private ServerRequestInterface $request;
    private ArrayBasedRequestWrapper $query;
    private \ilGlobalTemplateInterface $tpl;
    private \ilLanguage $lng;
    private \ilCtrl $ctrl;
    private \ilUIService $ui_service;
    private UIFactory $ui_factory;
    private UIRenderer $ui_renderer;
    private Refinery $refinery;
    private \ilObjUser $user;
    private \ilDBInterface $db;
    private \ilAccessHandler $access;
    private DataFactory $data_factory;

    private \ilObjCourse $object;

    private ?array $parameters = null;

    /**
     *
     * @var array<string, string|array>
     */
    private array $filter_data;

    public function __construct()
    {
        /** @var \ILIAS\DI\Container $DIC */
        global $DIC;
        $this->pl = $DIC['component.factory']->getPlugin(\ilUserSessionManagementPlugin::PLUGIN_ID);
        $local_dic = $this->pl->getLocalDIC();
        $this->config = $this->pl->getConfig();
        $this->user_session_repo = $local_dic['user_session_repo'];
        $this->sessions_table_data_retriever = $local_dic['sessions_table_data_retriever'];

        $this->access = $local_dic['ilAccess'];
        $this->tabs_gui = $local_dic['ilTabs'];
        $this->help = $local_dic['ilHelp'];
        $this->locator = $local_dic['ilLocator'];
        $this->request = $local_dic['http']->request();
        $this->query = $local_dic['http']->wrapper()->query();
        $this->tpl = $local_dic['tpl'];
        $this->lng = $local_dic['lng'];
        $this->ctrl = $local_dic['ilCtrl'];
        $this->user = $local_dic['ilUser'];
        $this->refinery = $local_dic['refinery'];
        $this->ui_factory = $local_dic['ui.factory'];
        $this->ui_renderer = $local_dic['ui.renderer'];
        $this->ui_service = $local_dic['il_ui_service'];
        $this->data_factory = new DataFactory();

        $ref_id = $this->retriveRefId();
        $this->object = new \ilObjCourse($ref_id);

        $this->lng->loadLanguageModule('crs');

        $this->ctrl->setParameterByClass(self::class, 'ref_id', $ref_id);
        $this->ctrl->setParameterByClass(\ilObjCourseGUI::class, 'ref_id', $ref_id);
    }

    private function retriveRefId(): int
    {
        $ref_id = null;
        if ($this->query->has('ref_id')) {
            $ref_id = $this->query->retrieve(
                'ref_id',
                $this->refinery->byTrying([
                    $this->refinery->kindlyTo()->int(),
                    $this->refinery->always(null)
                ])
            );
        }

        if ($ref_id === null) {
            $this->tpl->setOnScreenMessage('failure', $this->pl->txt('no_ref_id'));
            $this->ctrl->redirectByClass(\ilRepositoryGUI::class);
        }

        return $ref_id;
    }

    public function executeCommand() : void
    {
        if (!$this->access->checkAccess('write', '', $this->object->getRefId(), 'crs', $this->object->getId())) {
            $this->tpl->setOnScreenMessage('failure', $this->lng->txt('no_access_item_public'), true);
            $this->ctrl->redirectToURL('login.php?cmd=force_login');
        }

        list($url_builder, $action_parameter_token, $row_id_token) = $this->acquireParameters();

        if ($this->query->has($action_parameter_token->getName())) {
            $this->executeTableAction(
                $url_builder,
                $action_parameter_token,
                $row_id_token
            );
        }

        $this->showSessions();
    }

    private function executeTableAction(
        URLBuilder $url_builder,
        URLBuilderToken $action_parameter_token,
        URLBuilderToken $row_id_token
    ): void {
        $action = $this->query->retrieve(
            $action_parameter_token->getName(),
            $this->refinery->kindlyTo()->string()
        );

        $affected_users = [];
        if ($this->query->has($row_id_token->getName())) {
            $affected_users = $this->query->retrieve(
                $row_id_token->getName(),
                $this->refinery->byTrying(
                    [
                        $this->refinery->container()->mapValues(
                            $this->refinery->kindlyTo()->string()
                        ),
                        $this->refinery->always([])
                    ]
                )
            );
        }

        if ($affected_users === []) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('no_user_selected'));
            return;
        }

        if ($affected_users[0] === 'ALL_OBJECTS') {
            $this->buildFilter();
            $affected_users = $this->sessions_table_data_retriever
                ->withObject($this->object)
                ->withFilterData($this->filter_data)
                ->getAccessibleAndFilteredMemberIds();
        }

        if ($action === self::ACTION_STRING) {
            $this->user_session_repo->reauthorizeLoginForUsers(
                $affected_users,
                time() + $this->config->getReloginValidity() * 60
            );
            $this->ctrl->redirectByClass(self::class);
        }
    }

    private function showSessions() : void
    {
        $this->setupUI();
        $this->showSessionTable();
    }

    private function setupUI() : void
    {
        $this->locator->addRepositoryItems($this->object->getRefId());
        $this->tpl->setLocator();
        $this->setTitleAndDescription();
        $this->setTabs();
        $this->ctrl->setParameterByClass(self::class, self::TAB_ID, '1');
    }

    private function showSessionTable() : void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render([
                $this->buildFilter(),
                $this->buildTable()
            ])
        );
        $this->tpl->printToStdOut();
    }

    private function buildFilter(): Filter
    {
        list($url_builder) = $this->acquireParameters();
        $field_factory = $this->ui_factory->input()->field();
        $filter_inputs = [
            self::COLUMN_FIRST_NAME => $field_factory->text($this->lng->txt('firstname')),
            self::COLUMN_LAST_NAME => $field_factory->text($this->lng->txt('lastname')),
            self::COLUMN_EMAIL => $field_factory->text($this->lng->txt('email'))
        ];

        $active = array_fill(0, count($filter_inputs), true);

        $filter = $this->ui_service->filter()->standard(
            self::FILTER_ID,
            $this->ctrl->getLinkTargetByClass(self::class),
            $filter_inputs,
            $active,
            true,
            true
        );
        $this->filter_data = $this->ui_service->filter()->getData($filter) ?? [];
        return $filter;
    }

    private function buildTable(): DataTable
    {
        $column_factory = $this->ui_factory->table()->column();
        return $this->ui_factory->table()->data(
            $this->pl->txt('manage_sessions'),
            [
                self::COLUMN_FIRST_NAME => $column_factory->text($this->lng->txt('firstname')),
                self::COLUMN_LAST_NAME => $column_factory->text($this->lng->txt('lastname')),
                self::COLUMN_EMAIL => $column_factory->eMail($this->lng->txt('email')),
                self::COLUMN_LAST_LOGIN_IP => $column_factory->text($this->pl->txt('ip'))
                    ->withIsSortable(false),
                self::COLUMN_LOGGED_IN => $column_factory->boolean(
                    $this->pl->txt('currently_logged_in'),
                    $this->ui_factory->symbol()->icon()->custom(
                        \ilUtil::getImagePath('standard/icon_checked.svg'),
                        $this->lng->txt('not_logged_in')
                    ),
                    $this->ui_factory->symbol()->icon()->custom(
                        \ilUtil::getImagePath('standard/icon_unchecked.svg'),
                        $this->lng->txt('logged_in')
                    )
                )->withIsSortable(false),
                self::COLUMN_RELOING_AUTHORIZED_UNTIL => $column_factory
                    ->date($this->pl->txt('relogin_authorized_until'), $this->buildUserDateFormat())
                    ->withIsSortable(false)
            ],
            $this->sessions_table_data_retriever->withObject($this->object)->withFilterData($this->filter_data)
        )->withActions($this->buildActions())
        ->withRequest($this->request);
    }

    /**
     *
     * @return array<string, \ILIAS\UI\Component\Table\Action\Action>
     */
    private function buildActions(): array
    {
        list($url_builder, $action_parameter_token, $row_id_token) = $this->acquireParameters();

        return [
            self::ACTION_STRING => $this->ui_factory->table()->action()->standard(
                $this->pl->txt('authorize_relogin'),
                $url_builder->withParameter($action_parameter_token, self::ACTION_STRING),
                $row_id_token
            )
        ];
    }

    private function acquireParameters(): array
    {
        if ($this->parameters === null) {
            $this->parameters = (new URLBuilder(
                $this->data_factory->uri(ILIAS_HTTP_PATH . '/'
                    . $this->ctrl->getLinkTargetByClass(self::class))
            ))->acquireParameters(
                ['xusm', 'st'],
                'action',
                'user_id'
            );
        }

        return $this->parameters;
    }

    private function buildUserDateFormat(): DateFormat
    {
        $user_format = $this->user->getDateFormat();
        if ($this->user->getTimeFormat() === (string) \ilCalendarSettings::TIME_FORMAT_24) {
            return $this->data_factory->dateFormat()->withTime24($user_format);
        }

        return $this->data_factory->dateFormat()->withTime12($user_format);
    }

    /*
     * Here starteth the whole shenannigans to build the page.
     */

    private function setTitleAndDescription(): void
    {
        $this->tpl->setTitle(
            strip_tags(
                $this->object->getPresentationTitle(),
                \ilObjectGUI::ALLOWED_TAGS_IN_TITLE_AND_DESCRIPTION
            )
        );
        $this->tpl->setDescription(
            strip_tags(
                $this->object->getLongDescription(),
                \ilObjectGUI::ALLOWED_TAGS_IN_TITLE_AND_DESCRIPTION
            )
        );

        $this->tpl->setTitleIcon(
            \ilObject::_getIcon($this->object->getId(), 'big', $this->object->getType()),
            $this->lng->txt("obj_" . $this->object->getType())
        );

        $lgui = \ilObjectListGUIFactory::_getListGUIByType($this->object->getType());
        $lgui->initItem($this->object->getRefId(), $this->object->getId(), $this->object->getType());
        $this->tpl->setAlertProperties($lgui->getAlertProperties());
    }

    private function setTabs(): void
    {
        $this->help->setScreenIdComponent("crs");

        $this->addContentTabs();

        if ($this->object->getViewMode() === \ilCourseConstants::IL_CRS_VIEW_TIMING) {
            $cmd = $this->object->getMemberObject()->isParticipant() ? 'managePersonalTimings' : 'manageTimings';
            $this->tabs_gui->addTab(
                'timings_timings',
                $this->lng->txt('timings_timings'),
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilCourseContentGUI::class], $cmd)
            );
        }

        if ($this->object->getViewMode() === \ilCourseConstants::IL_CRS_VIEW_OBJECTIVE
            || \ilCourseObjective::_getCountObjectives($this->object->getId())) {
            $this->tabs_gui->addTab(
                'crs_objectives',
                $this->lng->txt('crs_objectives'),
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilLOEditorGUI::class], '')
            );
        }

        $this->tabs_gui->addTab(
            'info_short',
            $this->lng->txt('info_short'),
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilInfoScreenGUI::class], 'showSummary'),
        );

        $this->tabs_gui->addTab(
            'settings',
            $this->lng->txt('settings'),
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class], 'edit'),
        );

        $this->tabs_gui->addTab(
            'members',
            $this->lng->txt('members'),
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilCourseMembershipGUI::class])
        );

        if (\ilBadgeHandler::getInstance()->isObjectActive($this->object->getId())) {
            $this->tabs_gui->addTarget(
                'obj_tool_setting_badges',
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilBadgeManagementGUI::class], ''),
            );
        }

        if (\ilContSkillPresentationGUI::isAccessible($this->object->getRefId())) {
            $this->tabs_gui->addTarget(
                'obj_tool_setting_skills',
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilContainerSkillGUI::class, \ilContSkillPresentationGUI::class], ''),
            );
        }

        if (\ilContainer::_lookupContainerSetting(
            $this->object->getId(),
            \ilObjectServiceSettingsGUI::BOOKING,
            '0'
        )) {
            $this->tabs_gui->addTarget(
               ' "obj_tool_setting_booking"',
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilBookingGatewayGUI::class])
            );
        }

        if (\ilObjUserTracking::_enabledLearningProgress()) {
            $this->tabs_gui->addTarget(
                'learning_progress',
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilLearningProgressGUI::class])
            );
        }

        $this->tabs_gui->addTarget(
            'meta_data',
            $this->ctrl->getLinkTargetByClass(
                [\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilObjectMetaDataGUI::class, \ilMDEditorGUI::class],
                'listSection'
            )
        );

        $this->tabs_gui->addTarget(
            'export',
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilExportGUI::class]),
        );

        if ($this->access->checkAccess('edit_permission', '', $this->object->getRefId(), 'crs', $this->object->getId())) {
            $this->tabs_gui->addTarget(
                'perm_settings',
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilPermissionGUI::class], 'perm'),
            );
        }

        if ($this->access->checkAccess('join', '', $this->object->getRefId(), 'crs', $this->object->getId())
            && !$this->object->getMemberObject()->isAssigned()) {
            $id = 'join';
            $langvar = 'join';
            if (\ilCourseWaitingList::_isOnList($this->user->getId(), $this->object->getId())) {
                $id = 'leave';
                $langvar = 'membership_leave';
            }

            $this->tabs_gui->addTab(
                $id,
                $this->lng->txt($langvar),
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class, \ilCourseRegistrationGUI::class], 'show'),
            );
        }
        if ($this->access->checkAccess('leave', '', $this->object->getRefId(), 'crs', $this->object->getId())
            && $this->object->getMemberObject()->isMember()) {
            $this->tabs_gui->addTab(
                'crs_unsubscribe',
                $this->lng->txt('crs_unsubscribe'),
                $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class], 'unsubscribe'),
            );
        }
    }

    private function addContentTabs(): void
    {
        if (!$this->object->isNewsTimelineEffective()) {
            $this->addContentTab();
            return;
        }

        if (!$this->object->isNewsTimelineLandingPageEffective()) {
            $this->addContentTab();
            $this->addTimelineTab();
            return;
        }

        $this->addTimelineTab();
        $this->addContentTab();
    }

    private function addContentTab(): void
    {
        $this->tabs_gui->addTab(
            'view_content',
            $this->lng->txt('content'),
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::class], 'view')
        );
    }

    private function addTimelineTab(): void
    {
        $this->tabs_gui->addTab(
            'news_timeline',
            $this->lng->txt('cont_news_timeline_tab'),
            $this->ctrl->getLinkTargetByClass([\ilRepositoryGUI::class, \ilObjCourseGUI::classe, \ilNewsTimelineGUI::class], 'show')
        );
    }
}
