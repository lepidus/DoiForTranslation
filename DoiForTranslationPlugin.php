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
use APP\plugins\generic\doiForTranslation\classes\TranslationsService;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\Registry;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\i18n\PKPLocale;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use APP\plugins\generic\doiForTranslation\classes\TranslationsDAO;

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
        $bestLocaleRank = $localeRanks[$selectedSubmission->getData('locale')] ?? null;

        foreach ($submissionGroup as $submission) {
            $localeRank = $localeRanks[$submission->getData('locale')] ?? null;

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

        if ($success) {
            $handler = new DoiForTranslationHandler($this);

            Hook::add('TemplateManager::display', $this->loadBackendResources(...));
            Hook::add('TemplateManager::display', $this->filterTranslationsByLocale(...));
            Hook::add('Templates::Article::Main', $this->addPublicSiteModifications(...));
            Hook::add('Templates::Issue::Issue::Article', $this->addPublicSiteModifications(...));
            Hook::add('APIHandler::endpoints::contexts', $handler->registerRoute(...));
            Hook::add('Schema::get::submission', $this->addOurFieldsToSubmissionSchema(...));
            Hook::add('articlecrossrefxmlfilter::execute', $this->addCrossrefTranslationRelation(...));
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

    private function addSummaryStyleSheet(TemplateManager $templateMgr): void
    {
        $request = Application::get()->getRequest();
        $styleSheetUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/styles/translationsSummary.css';
        $templateMgr->addStyleSheet('translationsSummary', $styleSheetUrl, ['contexts' => ['frontend']]);
    }

    public function addOurFieldsToSubmissionSchema($hookName, $params)
    {
        $schema = &$params[0];

        $schema->properties->{'isTranslationOf'} = (object) [
            'type' => 'integer'
        ];

        return false;
    }

    public function loadBackendResources(string $hookName, array $params): bool
    {
        $templateMgr = $params[0];
        $request = Application::get()->getRequest();

        if (!$this->isEnabledForCurrentContext()) {
            return Hook::CONTINUE;
        }

        $buildUrl = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/public/build';
        $version = $this->getCurrentVersion()?->getVersionString() ?? '';
        $templateMgr->addJavaScript(
            'DoiForTranslationPlugin',
            $buildUrl . '/build.iife.js?v=' . $version,
            [
                'inline' => false,
                'contexts' => ['backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
            ]
        );

        return Hook::CONTINUE;
    }

    public function filterTranslationsByLocale($hookName, $params)
    {
        $templateMgr = $params[0];
        $template = $params[1];

        if (!$this->isEnabledForCurrentContext()) {
            return Hook::CONTINUE;
        }

        if ($template != 'frontend/pages/issue.tpl' && $template != 'frontend/pages/indexJournal.tpl') {
            return Hook::CONTINUE;
        }

        $this->addSummaryStyleSheet($templateMgr);

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

        return Hook::CONTINUE;
    }

    public function addPublicSiteModifications($hookName, $params)
    {
        $templateMgr = &$params[1];
        $output = &$params[2];

        if (!$this->isEnabledForCurrentContext()) {
            return Hook::CONTINUE;
        }

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

        return Hook::CONTINUE;
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

    protected function isEnabledForCurrentContext(): bool
    {
        $context = Application::get()->getRequest()->getContext();

        return $context && $this->getEnabled($context->getId());
    }

    protected function getPublicTemplatePlace(string $requestedPage): string
    {
        return $requestedPage === TranslationsService::PLACE_ARTICLE ? 'ArticlePage' : 'Summary';
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

        if (!$contextId || !$this->getEnabled($contextId)) {
            return Hook::CONTINUE;
        }

        $translationDAO = new TranslationsDAO();

        $relationsNamespace = 'http://www.crossref.org/relations.xsd';
        $crossrefNamespace = 'http://www.crossref.org/schema/5.4.0';
        $articleNodes = $preliminaryOutput->getElementsByTagName('journal_article');
        foreach ($articleNodes as $articleNode) {
            $doiDataNode = $articleNode->getElementsByTagName('doi_data')->item(0);
            $doiNode = $doiDataNode->getElementsByTagName('doi')->item(0);
            $doi = $doiNode->nodeValue;

            $publicationId = $translationDAO->getPublicationIdByDoi($doi, $contextId);

            if (!is_null($publicationId)) {
                $publication = Repo::publication()->get($publicationId);
                $submission = Repo::submission()->get($publication->getData('submissionId'));
                if ($submission->getData('isTranslationOf')) {
                    $localeNames = PKPLocale::getAllLocales();
                    $originalSubmission = Repo::submission()->get($submission->getData('isTranslationOf'));
                    $originalLanguage = $originalSubmission->getData('locale');

                    $programNode = $preliminaryOutput->createElementNS($relationsNamespace, 'program');
                    $relatedItemNode = $preliminaryOutput->createElementNS($relationsNamespace, 'related_item');
                    $relatedItemNode->appendChild($node = $preliminaryOutput->createElementNS(
                        $relationsNamespace,
                        'description',
                        htmlspecialchars($localeNames[$submission->getData('locale')] . ' translation', ENT_COMPAT, 'UTF-8')
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
                        htmlspecialchars($originalSubmission->getCurrentPublication()->getLocalizedTitle(), ENT_COMPAT, 'UTF-8')
                    ));
                    $originalLanguageTitleNode->setAttribute('language', LocaleConversion::getIso1FromLocale($originalLanguage));
                }
            }
        }
        return Hook::CONTINUE;
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
