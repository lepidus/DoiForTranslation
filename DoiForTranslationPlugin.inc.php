<?php

/**
 * @file plugins/generic/DoiForTranslation/DoiForTranslationPlugin.inc.php
 *
 * Copyright (c) 2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class DoiForTranslationPlugin
 * @ingroup plugins_generic_DoiForTranslation
 * @brief Main class of Submissions Translation plugin.
 *
 */

import('lib.pkp.classes.plugins.GenericPlugin');
import('plugins.generic.DoiForTranslation.classes.TranslationsService');

class DoiForTranslationPlugin extends GenericPlugin
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
            HookRegistry::register('Templates::Article::Main', array($this, 'addPublicSiteModifications'));
            HookRegistry::register('Templates::Issue::Issue::Article', array($this, 'addPublicSiteModifications'));
            HookRegistry::register('Dispatcher::dispatch', array($this, 'setupDoiForTranslationHandler'));
            HookRegistry::register('Schema::get::submission', array($this, 'addOurFieldsToSubmissionSchema'));
            HookRegistry::register('articlecrossrefxmlfilter::execute', [$this, 'addCrossrefTranslationRelation']);
        }

        $this->addSummaryStyleSheet();

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.DoiForTranslation.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.DoiForTranslation.description');
    }

    private function addSummaryStyleSheet()
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $styleSheetUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/translationsSummary.css';
        $templateMgr->addStyleSheet('translationsSummary', $styleSheetUrl);
    }

    public function addOurFieldsToSubmissionSchema($hookName, $params)
    {
        $schema = &$params[0];

        $schema->properties->{'isTranslationOf'} = (object) [
            'type' => 'integer'
        ];

        return false;
    }

    public function addWorkflowModifications($hookName, $params)
    {
        $templateMgr = &$params[1];
        $submission = $templateMgr->getTemplateVars('submission');
        $submissionIsTranslation = !is_null($submission->getData('isTranslationOf'));

        if ($templateMgr->getTemplateVars('requestedPage') != 'workflow') {
            return false;
        }

        if ($submissionIsTranslation) {
            $templateMgr->registerFilter("output", array($this, 'refTranslatedWorkflowFilter'));
        } else {
            $templateMgr->registerFilter("output", array($this, 'nonTranslationWorkflowFilter'));

            $translationsService = new TranslationsService();
            $translationsForDisplay = $translationsService->getTranslations($submission->getId(), 'workflow');
            $templateMgr->assign([
                'hasTranslations' => (count($translationsForDisplay) > 0),
                'translations' => $translationsForDisplay
            ]);
        }

        return false;
    }

    public function nonTranslationWorkflowFilter($output, $templateMgr)
    {
        return $this->workflowActionsFilter($output, $templateMgr, 'nonTranslationWorkflow');
    }

    public function refTranslatedWorkflowFilter($output, $templateMgr)
    {
        return $this->workflowActionsFilter($output, $templateMgr, 'refTranslatedWorkflow');
    }

    private function workflowActionsFilter($output, $templateMgr, $templateName)
    {
        $pattern = '/<template slot="actions">/';
        if (preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE)) {
            $posBeginning = $matches[0][1];
            $patternLength = strlen($pattern) - 2;

            $nonTranslationTemplate = $templateMgr->fetch($this->getTemplateResource($templateName . '.tpl'));

            $output = substr_replace($output, $nonTranslationTemplate, $posBeginning + $patternLength, 0);
            $templateMgr->unregisterFilter('output', array($this, $templateName . 'Filter'));
        }
        return $output;
    }

    public function loadResourcesToWorkflow($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if ($template == 'workflow/workflow.tpl') {
            $submission = $templateMgr->getTemplateVars('submission');

            if (is_null($submission->getData('isTranslationOf'))) {
                $this->addCreateTranslationForm($templateMgr, $request);
            } else {
                $translationsService = new TranslationsService();
                $translatedSubmissionId = $submission->getData('isTranslationOf');
                $translatedSubmissionData = $translationsService->getTranslatedSubmissionData($translatedSubmissionId, 'workflow');

                $templateMgr->setState([
                    'translatedSubmission' => $translatedSubmissionData
                ]);
            }
        }

        return false;
    }

    private function addCreateTranslationForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->getTemplateVars('submission');

        $this->import('classes.components.forms.CreateTranslationForm');
        $createTranslationUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'DoiForTranslation/create', null, null, ['submissionId' => $submission->getId()]);
        $createTranslationForm = new CreateTranslationForm($createTranslationUrl, $submission);

        $workflowComponents = $templateMgr->getState('components');
        $workflowComponents[$createTranslationForm->id] = $createTranslationForm->getConfig();

        $templateMgr->setState([
            'components' => $workflowComponents
        ]);
    }

    public function addPublicSiteModifications($hookName, $params)
    {
        $templateMgr = &$params[1];
        $output = &$params[2];
        $submission = $templateMgr->getTemplateVars('article');
        $submissionIsTranslation = !is_null($submission->getData('isTranslationOf'));

        $place = ($templateMgr->getTemplateVars('requestedPage') == 'article' ? 'ArticlePage' : 'Summary');

        if ($submissionIsTranslation) {
            $localeNames = &AppLocale::getAllLocales();
            $translationsService = new TranslationsService();
            $translatedSubmissionId = $submission->getData('isTranslationOf');
            $translatedSubmissionData = $translationsService->getTranslatedSubmissionData($translatedSubmissionId, 'article');

            $templateMgr->assign([
                'translatedSubmission' => $translatedSubmissionData,
                'translationLocale' => $localeNames[$submission->getData('locale')]
            ]);
            $output .= $templateMgr->fetch($this->getTemplateResource("refTranslated{$place}.tpl"));
        } else {
            $translationsService = new TranslationsService();
            $translations = $translationsService->getTranslations($submission->getId(), 'article');

            if (count($translations) > 0) {
                $templateMgr->assign('translations', $translations);
                $output .= $templateMgr->fetch($this->getTemplateResource("listTranslations{$place}.tpl"));
            }
        }

        return false;
    }

    public function addCrossrefTranslationRelation($hookName, $params)
    {
        $preliminaryOutput = & $params[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = isset($context) ? $context->getId() : null;
        $publicationDAO = DAORegistry::getDAO('PublicationDAO');

        $relationsNamespace = 'http://www.crossref.org/relations.xsd';
        $crossrefNamespace = 'http://www.crossref.org/schema/4.3.6';
        $articleNodes = $preliminaryOutput->getElementsByTagName('journal_article');
        foreach ($articleNodes as $articleNode) {
            $doiDataNode = $articleNode->getElementsByTagName('doi_data')->item(0);
            $doiNode = $doiDataNode->getElementsByTagName('doi')->item(0);
            $doi = $doiNode->nodeValue;

            $publicationIds = $publicationDAO->getIdsBySetting('pub-id::doi', $doi, $contextId);

            assert(count($publicationIds) >= 1);
            if (count($publicationIds) >= 1) {
                $submissionDao = DAORegistry::getDAO('SubmissionDAO');
                $publicationService = Services::get('publication');
                $submissionService = Services::get('submission');

                $publication = $publicationService->get($publicationIds[0]);
                $submission = $submissionService->get($publication->getData('submissionId'));
                if ($submission->getData('isTranslationOf')) {
                    $localeNames = AppLocale::getAllLocales();
                    $originalSubmission = $submissionService->get($submission->getData('isTranslationOf'));
                    $originalLanguage = $originalSubmission->getLocale();

                    $programNode = $preliminaryOutput->createElementNS($relationsNamespace, 'program');
                    $relatedItemNode = $preliminaryOutput->createElementNS($relationsNamespace, 'related_item');
                    $relatedItemNode->appendChild($node = $preliminaryOutput->createElementNS(
                        $relationsNamespace,
                        'description',
                        htmlspecialchars($localeNames[$submission->getLocale()] . ' translation', ENT_COMPAT, 'UTF-8')
                    ));
                    $relatedItemNode->appendChild($node = $preliminaryOutput->createElementNS(
                        $relationsNamespace,
                        'intra_work_relation',
                        htmlspecialchars($originalSubmission->getCurrentPublication()->getStoredPubId('doi'), ENT_COMPAT, 'UTF-8')
                    ));
                    $node->setAttribute('relationship-type', 'isTranslationOf');
                    $node->setAttribute('identifier-type', 'doi');
                    $programNode->appendChild($relatedItemNode);

                    $doiDataNode->parentNode->insertBefore($programNode, $doiDataNode);

                    $titlesNode = $articleNode->getElementsByTagName('titles')->item(0);
                    $titlesNode->appendChild($originalLanguageTitleNode = $preliminaryOutput->createElementNS(
                        $crossrefNamespace,
                        'original_language_title',
                        htmlspecialchars($originalSubmission->getLocalizedTitle(), ENT_COMPAT, 'UTF-8')
                    ));
                    $originalLanguageTitleNode->setAttribute('language', PKPLocale::getIso1FromLocale($originalLanguage));
                }
            }
        }
        return false;
    }

    public function setupDoiForTranslationHandler($hookName, $request)
    {
        $router = $request->getRouter();
        if (!($router instanceof \APIRouter)) {
            return;
        }

        if (str_contains($request->getRequestPath(), 'api/v1/DoiForTranslation')) {
            $this->import('api.v1.DoiForTranslation.DoiForTranslationHandler');
            $handler = new DoiForTranslationHandler();
        }

        if (!isset($handler)) {
            return;
        }

        $router->setHandler($handler);
        $handler->getApp()->run();
        exit;
    }
}
