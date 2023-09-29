<?php

/**
 * @file plugins/generic/submissionsTranslation/index.php
 *
 * Copyright (c) 2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class SubmissionsTranslationPlugin
 * @ingroup plugins_generic_submissionsTranslation
 * @brief Main class of Submissions Translation plugin.
 *
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class SubmissionsTranslationPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success and $this->getEnabled($mainContextId)) {
            HookRegistry::register('Template::Workflow', array($this, 'addWorkflowModifications'));
            HookRegistry::register('TemplateManager::display', array($this, 'loadResourcesToWorkflow'));
            HookRegistry::register('Dispatcher::dispatch', array($this, 'setupSubmissionsTranslationHandler'));
            HookRegistry::register('Schema::get::submission', array($this, 'addOurFieldsToSubmissionSchema'));
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.submissionsTranslation.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.submissionsTranslation.description');
    }

    public function addOurFieldsToSubmissionSchema($hookName, $params)
    {
        $schema = & $params[0];

        $schema->properties->{'isTranslationOf'} = (object) [
            'type' => 'integer'
        ];

        return false;
    }

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr = & $params[1];

        if($templateMgr->getTemplateVars('requestedPage') == 'workflow') {
            $templateMgr->registerFilter("output", array($this, 'addCreateTranslationButtonFilter'));
        }

        return false;
    }

    public function addCreateTranslationButtonFilter($output, $templateMgr)
    {
        $pattern = '/<template slot="actions">/';
        if (preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posBeginning = $matches[0][1];
            $patternLength = strlen($pattern) - 2;

            $createTranslationButton = $templateMgr->fetch($this->getTemplateResource('createTranslationWorkflow.tpl'));

            $output = substr_replace($output, $createTranslationButton, $posBeginning + $patternLength, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCreateTranslationButtonFilter'));
        }
        return $output;
    }

    public function loadResourcesToWorkflow($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];
        $request = Application::get()->getRequest();

        if ($template == 'workflow/workflow.tpl') {
            $this->addCreateTranslationForm($templateMgr, $request);
        }

        return false;
    }

    private function addCreateTranslationForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->get_template_vars('submission');

        $this->import('classes.components.forms.CreateTranslationForm');
        $createTranslationUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'submissionsTranslation/create', null, null, ['submissionId' => $submission->getId()]);
        $createTranslationForm = new CreateTranslationForm($createTranslationUrl, $submission);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$createTranslationForm->id] = $createTranslationForm->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    public function setupSubmissionsTranslationHandler($hookName, $request)
    {
        $router = $request->getRouter();
        if (!($router instanceof \APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/submissionsTranslation')) {
            $this->import('api.v1.submissionsTranslation.SubmissionsTranslationHandler');
            $handler = new SubmissionsTranslationHandler();
        }

        if (!isset($handler)) {
            return;
        }

        $router->setHandler($handler);
        $handler->getApp()->run();
        exit;
    }
}
