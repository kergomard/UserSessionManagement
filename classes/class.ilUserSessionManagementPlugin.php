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

use kergomard\UserSessionManagement\LocalDIC;
use kergomard\UserSessionManagement\Config\Config;
use kergomard\UserSessionManagement\Management\UserSessionRepository;
use kergomard\UserSessionManagement\Management\Session;

class ilUserSessionManagementPlugin extends ilUserInterfaceHookPlugin
{
    public const PLUGIN_ID = 'usm';
    private const PLUGIN_NAME = 'UserSessionManagement';

    public const VERSION = '0.0.1';

    private LocalDIC $local_dic;
    private Config $config;
    private UserSessionRepository $usm_repo;
    private ?Session $session;

    private \ilObjUser $user;
    private ilRbacReview $rbacreview;
    private \ilAuthSession $auth;
    private \ilCtrl $ctrl;
    private \ilLogger $logger;
    private \ilGlobalTemplateInterface $tpl;

    private bool $is_initialized = false;

	public function getPluginName(): string
	{
		return self::PLUGIN_NAME;
	}

    public function __construct(
        \ilDBInterface $db,
        \ilComponentRepositoryWrite $component_repository,
        string $id
    ) {
        parent::__construct($db, $component_repository, $id);

        global $DIC;

        $this->local_dic = new LocalDIC($DIC);
    }

    public function getLocalDIC(): LocalDIC
    {
        return $this->local_dic;
    }

    public function getConfig(): Config
    {
        $this->deferredInit();
        return $this->config;
    }

    public function handleEvent(
        string $component,
        string $event,
        array $parameter
    ): void {
        if (isset($parameter['username'])
                && $parameter['username'] === 'anonymous'
        ) {
            return;
        }
        if ($event === 'afterLogin') {
            $this->deferredInit();
            $this->handleLogin();
            return;
        }
    }

    private function deferredInit(): void
    {
        if ($this->is_initialized) {
            return;
        }

        $this->user = $this->local_dic['ilUser'];
        $this->rbacreview = $this->local_dic['rbacreview'];
        $this->auth = $this->local_dic['ilAuthSession'];
        $this->ctrl = $this->local_dic['ilCtrl'];
        $this->tpl = $this->local_dic['tpl'];
        $this->logger = $this->local_dic['ilLog'];

        $this->usm_repo = $this->local_dic['user_session_repo'];
        $this->config = $this->local_dic['config_repo']->get();
        $this->session = $this->usm_repo->getSessionForUserId($this->user->getId());

        $this->is_initialized = true;
    }

    private function handleLogin(): void
    {
        if (!$this->doesUserNeedChecking()) {
            return;
        }

        if (!$this->isCurrentUserAllowed()) {
            $this->logger->warning("The User {$this->user->getFullname()} tried "
            . "to log in for a second time while another session was present. "
            . "Login occured from the IP {$_SERVER['REMOTE_ADDR']}");
            $this->logoutAndNotify();
            return;
        }

        if ($this->session !== null && $this->session->getSessionId() !== $this->auth->getId()) {
            ilSession::_destroy($this->session->getSessionId());
        }

        $this->usm_repo->storeSession(
            new Session(
                $this->user->getId(),
                $this->auth->getId(),
                $_SERVER['REMOTE_ADDR']
            )
        );
    }

    private function doesUserNeedChecking(): bool
    {
        foreach ($this->config->getAffectedRoles() as $role) {
            if ($this->rbacreview->isAssigned($this->user->getId(), $role)) {
                return true;
            }
        }
        return false;
    }

    private function isCurrentUserAllowed(): bool
    {
        if ($this->session === null
            || $this->isCurrentSessionValid()
            || $this->isReloginAuthorized()) {
            return true;
        }

        return false;
    }

    private function isCurrentSessionValid(): bool
    {
        if ($this->session->getSessionId() !== $this->auth->getId()
            && \ilSession::lookupExpireTime($this->session->getSessionId()) > time()) {
            return false;
        }

        return true;
    }

    private function isReloginAuthorized(): bool
    {
        if ($this->session->getReloginAllowedUntil() === null
            || $this->session->getReloginAllowedUntil() < time()) {
            return false;
        }

        return true;
    }

    private function logoutAndNotify(): void
    {
        ilSession::setClosingContext(ilSession::SESSION_CLOSE_LOGIN);
        if ($this->auth->isValid()) {
            $this->auth->logout();
        }

        $this->tpl->setOnScreenMessage('failure', $this->language_handler->txt('already_logged_in'), true);
        $this->ctrl->redirectToURL('login.php?cmd=force_login');
    }
}
