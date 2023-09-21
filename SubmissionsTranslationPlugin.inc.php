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

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr = & $params[1];
        //$templateMgr->registerFilter("output", array($this, 'addCreateTranslationButtonFilter'));

        return false;
    }

    public function addCreateTranslationButtonFilter($output, $templateMgr)
    {
        if (preg_match('//', $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posBeginning = $matches[0][1];

            $createTranslationButton = $templateMgr->fetch($this->getTemplateResource('createTranslationWorkflow.tpl'));

            $output = substr_replace($output, $createTranslationButton, $posBeginning, 0);
            $templateMgr->unregisterFilter('output', array($this, 'addCreateTranslationButtonFilter'));
        }
        return $output;
    }
}
