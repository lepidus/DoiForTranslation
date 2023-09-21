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
}
