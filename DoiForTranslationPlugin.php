<?php

/**
 * @file plugins/generic/doiForTranslation/DoiForTranslationPlugin.php
 *
 * Copyright (c) 2023-2025 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see LICENSE or https://www.gnu.org/licenses/gpl-3.0.txt.
 *
 * @class DoiForTranslationPlugin
 *
 * @ingroup plugins_generic_DoiForTranslation
 *
 * @brief Main class of DOI For Translation plugin.
 *
 */

namespace APP\plugins\generic\doiForTranslation;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation\DoiForTranslationHandler;
use APP\plugins\generic\doiForTranslation\classes\components\forms\CreateTranslationForm;
use APP\plugins\generic\doiForTranslation\classes\TranslationsService;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\i18n\PKPLocale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class DoiForTranslationPlugin extends GenericPlugin
{
    public function getVisibleSubmissionIdsByLocale(array $submissionGroups, array $localePrecedence): array
    {
        $visibleSubmissionIds = [];
        $localeRanks = $this->getLocalePrecedenceRanks($localePrecedence);

        foreach ($submissionGroups as $submissionGroup) {
            if (empty($submissionGroup)) {
                continue;
            }

            $selectedSubmission = $this->pickVisibleSubmissionFromGroup($submissionGroup, $localeRanks);
            $visibleSubmissionIds[] = $selectedSubmission->getId();
        }

        return $visibleSubmissionIds;
    }

    protected function getLocalePrecedenceRanks(array $localePrecedence): array
    {
        $localeRanks = [];

        foreach (array_values($localePrecedence) as $rank => $locale) {
            $localeRanks[$locale] = $rank;
        }

        return $localeRanks;
    }

    protected function pickVisibleSubmissionFromGroup(array $submissionGroup, array $localeRanks)
    {
        $selectedSubmission = $this->pickOriginalOrFirst($submissionGroup);
        $bestLocaleRank = $localeRanks[$selectedSubmission->getLocale()] ?? null;

        foreach ($submissionGroup as $submission) {
            $localeRank = $localeRanks[$submission->getLocale()] ?? null;

            if (is_null($localeRank)) {
                continue;
            }

            if (is_null($bestLocaleRank) || $localeRank < $bestLocaleRank) {
                $selectedSubmission = $submission;
                $bestLocaleRank = $localeRank;
            }
        }

        return $selectedSubmission;
    }

    private function pickOriginalOrFirst(array $submissionGroup)
    {
        foreach ($submissionGroup as $submission) {
            if (is_null($submission->getData('isTranslationOf'))) {
                return $submission;
            }
        }

        return reset($submissionGroup);
    }

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);

        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }

        if ($success and $this->getEnabled($mainContextId)) {
            $handler = new DoiForTranslationHandler();

            Hook::add('Template::Workflow', $this->addWorkflowModifications(...));
            Hook::add('TemplateManager::display', $this->loadResourcesToWorkflow(...));
            Hook::add('TemplateManager::display', $this->filterTranslationsByLocale(...));
            Hook::add('Templates::Article::Main', $this->addPublicSiteModifications(...));
            Hook::add('Templates::Issue::Issue::Article', $this->addPublicSiteModifications(...));
            Hook::add('APIHandler::endpoints::contexts', $handler->registerRoute(...));
            Hook::add('Schema::get::submission', $this->addOurFieldsToSubmissionSchema(...));
            Hook::add('articlecrossrefxmlfilter::execute', $this->addCrossrefTranslationRelation(...));

            $this->addSummaryStyleSheet();
        }

        return $success;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.doiForTranslation.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.doiForTranslation.description');
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
            $templateMgr->registerFilter('output', [$this, 'refTranslatedWorkflowFilter']);
        } else {
            $templateMgr->registerFilter('output', [$this, 'nonTranslationWorkflowFilter']);

            $translationsService = $this->createTranslationsService();
            $translationsForDisplay = $translationsService->getTranslations($submission->getId(), TranslationsService::PLACE_WORKFLOW);
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
            $templateMgr->unregisterFilter('output', [$this, $templateName . 'Filter']);
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
                $translationsService = $this->createTranslationsService();
                $translatedSubmissionId = $submission->getData('isTranslationOf');
                $this->assignTranslatedSubmissionState($templateMgr, $translationsService, $translatedSubmissionId, TranslationsService::PLACE_WORKFLOW);
            }
        }

        return false;
    }

    public function filterTranslationsByLocale($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if ($template != 'frontend/pages/issue.tpl' && $template != 'frontend/pages/indexJournal.tpl') {
            return false;
        }

        $publishedSubmissions = $templateMgr->getTemplateVars('publishedSubmissions');
        $localePrecedence = $this->getLocalePrecedence();

        foreach ($publishedSubmissions as $sectionId => $section) {
            if (empty($section['articles'])) {
                continue;
            }

            $submissionGroups = [];
            foreach ($section['articles'] as $submission) {
                $groupId = $submission->getData('isTranslationOf') ?: $submission->getId();
                if (!array_key_exists($groupId, $submissionGroups)) {
                    $submissionGroups[$groupId] = [];
                }
                $submissionGroups[$groupId][] = $submission;
            }

            $visibleSubmissionIds = $this->getVisibleSubmissionIdsByLocale(array_values($submissionGroups), $localePrecedence);
            $publishedSubmissions[$sectionId]['articles'] = array_values(
                array_filter(
                    $section['articles'],
                    function ($submission) use ($visibleSubmissionIds) {
                        return in_array($submission->getId(), $visibleSubmissionIds, true);
                    }
                )
            );
        }

        $templateMgr->assign('publishedSubmissions', $publishedSubmissions);

        return false;
    }

    private function addCreateTranslationForm($templateMgr, $request)
    {
        $context = $request->getContext();
        $submission = $templateMgr->getTemplateVars('submission');

        $createTranslationUrl = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_API,
            $context->getPath(),
            'contexts/' . $context->getId() . '/doiForTranslation/create',
            null,
            null,
            ['submissionId' => $submission->getId()]
        );
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

        $place = $this->getPublicTemplatePlace($templateMgr->getTemplateVars('requestedPage'));

        if ($submissionIsTranslation) {
            $localeNames = PKPLocale::getAllLocales();
            $translationsService = $this->createTranslationsService();
            $translatedSubmissionId = $submission->getData('isTranslationOf');
            $translatedSubmissionData = $translationsService->getTranslatedSubmissionData($translatedSubmissionId, TranslationsService::PLACE_ARTICLE);

            $templateMgr->assign([
                'translatedSubmission' => $translatedSubmissionData,
                'translationLocale' => $localeNames[$submission->getData('locale')]
            ]);
            $output .= $templateMgr->fetch($this->getTemplateResource("refTranslated{$place}.tpl"));
        } else {
            $translationsService = $this->createTranslationsService();
            $this->prefetchPublicTranslations($templateMgr, $translationsService);
            $translations = $translationsService->getTranslations($submission->getId(), TranslationsService::PLACE_ARTICLE);

            if (count($translations) > 0) {
                $templateMgr->assign('translations', $translations);
                $output .= $templateMgr->fetch($this->getTemplateResource("listTranslations{$place}.tpl"));
            }
        }

        return false;
    }

    private function prefetchPublicTranslations($templateMgr, TranslationsService $translationsService): void
    {
        if ($templateMgr->getTemplateVars('requestedPage') == 'article') {
            return;
        }

        $request = Application::get()->getRequest();
        $requestCache = $this->getPublicTranslationPreloadCache($request);
        $cacheKey = $templateMgr->getTemplateVars('requestedPage');

        if (!empty($requestCache[$cacheKey])) {
            return;
        }

        $publishedSubmissions = $templateMgr->getTemplateVars('publishedSubmissions');
        if (!is_array($publishedSubmissions)) {
            return;
        }

        $submissionIds = [];
        foreach ($publishedSubmissions as $section) {
            foreach ($section['articles'] ?? [] as $article) {
                if (is_null($article->getData('isTranslationOf'))) {
                    $submissionIds[] = $article->getId();
                }
            }
        }

        $translationsService->prefetchTranslations($submissionIds, TranslationsService::PLACE_ARTICLE);
        $requestCache[$cacheKey] = true;
        $this->setPublicTranslationPreloadCache($request, $requestCache);
    }

    protected function createTranslationsService(): TranslationsService
    {
        return new TranslationsService();
    }

    protected function getPublicTemplatePlace(string $requestedPage): string
    {
        return $requestedPage === TranslationsService::PLACE_ARTICLE ? 'ArticlePage' : 'Summary';
    }

    protected function assignTranslatedSubmissionState($templateMgr, TranslationsService $translationsService, int $translatedSubmissionId, string $place): void
    {
        $templateMgr->setState([
            'translatedSubmission' => $translationsService->getTranslatedSubmissionData($translatedSubmissionId, $place)
        ]);
    }

    private function getPublicTranslationPreloadCache($request): array
    {
        $allRequestCaches = Registry::get('plugins.generic.doiForTranslation.publicTranslationPreload', true, []);
        $requestCacheKey = spl_object_hash($request);

        if (!isset($allRequestCaches[$requestCacheKey])) {
            $allRequestCaches[$requestCacheKey] = [];
            Registry::set('plugins.generic.doiForTranslation.publicTranslationPreload', $allRequestCaches);
        }

        return $allRequestCaches[$requestCacheKey];
    }

    private function setPublicTranslationPreloadCache($request, array $requestCache): void
    {
        $allRequestCaches = Registry::get('plugins.generic.doiForTranslation.publicTranslationPreload', true, []);
        $allRequestCaches[spl_object_hash($request)] = $requestCache;
        Registry::set('plugins.generic.doiForTranslation.publicTranslationPreload', $allRequestCaches);
    }

    public function addCrossrefTranslationRelation($hookName, $params)
    {
        $preliminaryOutput = & $params[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = isset($context) ? $context->getId() : null;
        $publicationDAO = Repo::publication()->dao;

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
                $publication = Repo::publication()->get($publicationIds[0]);
                $submission = Repo::submission()->get($publication->getData('submissionId'));
                if ($submission->getData('isTranslationOf')) {
                    $localeNames = PKPLocale::getAllLocales();
                    $originalSubmission = Repo::submission()->get($submission->getData('isTranslationOf'));
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
                    $originalLanguageTitleNode->setAttribute('language', LocaleConversion::getIso1FromLocale($originalLanguage));
                }
            }
        }
        return false;
    }

    private function getLocalePrecedence(): array
    {
        $request = Application::get()->getRequest();
        $localePrecedence = [Locale::getLocale()];
        $context = $request->getContext();
        $site = $request->getSite();

        if ($context && !in_array($context->getPrimaryLocale(), $localePrecedence, true)) {
            $localePrecedence[] = $context->getPrimaryLocale();
        }

        if ($site && !in_array($site->getPrimaryLocale(), $localePrecedence, true)) {
            $localePrecedence[] = $site->getPrimaryLocale();
        }

        if ($context) {
            foreach ($context->getSupportedSubmissionLocales() as $locale) {
                if (!in_array($locale, $localePrecedence, true)) {
                    $localePrecedence[] = $locale;
                }
            }
        }

        return $localePrecedence;
    }
}
