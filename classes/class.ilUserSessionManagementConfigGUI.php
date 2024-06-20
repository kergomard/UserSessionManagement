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

use kergomard\UserSessionManagement\Config\Repository;
use kergomard\UserSessionManagement\Config\Config;

use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;
use ILIAS\UI\Component\Input\Container\Form\Form;
use ILIAS\Refinery\Factory as Refinery;
use Psr\Http\Message\ServerRequestInterface;

 /**
 *  @ilCtrl_IsCalledBy ilUserSessionManagementConfigGUI: ilObjComponentSettingsGUI
 *
 */
class ilUserSessionManagementConfigGUI extends ilPluginConfigGUI
{
    private Repository $config_repo;
    private UIFactory $ui_factory;
    private UIRenderer $ui_renderer;
    private ilGlobalTemplateInterface $tpl;
    private Refinery $refinery;
    private ServerRequestInterface $request;
    private ilLanguage $lng;
    private ilCtrl $ctrl;
    private ilRbacReview $rbacreview;

    public function performCommand(string $cmd): void
    {
        $local_dic = $this->plugin_object->getLocalDIC();
        $this->config_repo = $local_dic['config_repo'];
        $this->ui_factory = $local_dic['ui.factory'];
        $this->ui_renderer = $local_dic['ui.renderer'];
        $this->refinery = $local_dic['refinery'];
        $this->request = $local_dic['http']->request();
        $this->tpl = $local_dic['tpl'];
        $this->ctrl = $local_dic['ilCtrl'];
        $this->lng = $local_dic['lng'];
        $this->rbacreview = $local_dic['rbacreview'];

        if ($cmd === 'save') {
            $this->save();
            return;
        }

        $this->configure();
    }

    public function configure(): void
    {
        $this->tpl->setContent(
            $this->ui_renderer->render(
                $this->initConfigurationForm()
            )
        );
    }

    public function save(): void
    {
        $form = $this->initConfigurationForm()->withRequest($this->request);
        $data = $form->getData();
        if ($data === null) {
            $this->tpl->setContent(
                $this->ui_renderer->render($form)
            );
            return;
        }

        $this->config_repo->store($data);
        $this->configure();
    }

    public function initConfigurationForm() : Form
    {
        $ff = $this->ui_factory->input()->field();

        $trafo = $this->refinery->custom()->transformation(
            static fn(array $vs): Config => new Config(
                $vs['roles'] ?? [],
                $vs['relogin_validity']
            )
        );

        $roles = array_reduce(
            $this->rbacreview->getGlobalRoles(),
            static function (array $c, int $v): array {
                $c[$v] = ilObject::_lookupTitle($v);
                return $c;
            },
            []
        );

        $config = $this->config_repo->get();

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormActionByClass(self::class, 'save'),
            [
                'roles' => $ff->multiSelect(
                    $this->plugin_object->txt('affected_roles_label'),
                    $roles,
                    $this->plugin_object->txt('affected_roles_byline')
                )->withValue($config->getAffectedRoles()),
                'relogin_validity' => $ff->numeric(
                    $this->plugin_object->txt('relogin_validity_label'),
                    $this->plugin_object->txt('relogin_validity_byline')
                )->withAdditionalTransformation(
                    $this->refinery->custom()->constraint(
                        static fn(int $v): bool => $v > 0 ? true : false,
                        sprintf(
                            $this->lng->txt('not_greater_than'),
                            $this->plugin_object->txt('relogin_validity'), 0
                        )
                    )
                )->withValue($config->getReloginValidity()),
            ]
        )->withAdditionalTransformation($trafo);
    }
}