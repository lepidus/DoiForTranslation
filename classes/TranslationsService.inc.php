<?php

import('plugins.generic.doiForTranslation.classes.TranslationsDAO');
import('lib.pkp.classes.core.Registry');

class TranslationsService
{
    public const PLACE_WORKFLOW = 'workflow';
    public const PLACE_ARTICLE = 'article';

    public function getTranslations(int $submissionId, string $place): array
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $cacheKey = $this->getTranslationsCacheKey($context->getId(), $place, $submissionId);
        $requestCache = $this->getRequestCache($request);

        if (!array_key_exists($cacheKey, $requestCache['translations'])) {
            $this->prefetchTranslations([$submissionId], $place);
            $requestCache = $this->getRequestCache($request);
        }

        return $requestCache['translations'][$cacheKey] ?? [];
    }

    public function prefetchTranslations(array $submissionIds, string $place): void
    {
        $submissionIds = array_values(array_unique(array_map('intval', $submissionIds)));

        if (empty($submissionIds)) {
            return;
        }

        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();
        $requestCache = $this->getRequestCache($request);
        $missingSubmissionIds = [];

        foreach ($submissionIds as $submissionId) {
            $cacheKey = $this->getTranslationsCacheKey($contextId, $place, $submissionId);
            if (!array_key_exists($cacheKey, $requestCache['translations'])) {
                $missingSubmissionIds[] = $submissionId;
            }
        }

        if (empty($missingSubmissionIds)) {
            return;
        }

        $translationsDao = $this->createTranslationsDao();
        $onlyPublishedTranslations = $this->shouldOnlyLoadPublishedTranslations($place);
        $groupedTranslations = $translationsDao->getTranslationsBySubmissionIds($missingSubmissionIds, $contextId, $onlyPublishedTranslations);
        $localeNames = $this->getLocaleNames();
        $titleLocalesBySubmissionId = [];

        foreach ($groupedTranslations as $translations) {
            foreach ($translations as $translation) {
                $titleCacheKey = $this->getTitleCacheKey($translation['id'], $translation['locale']);
                if (!array_key_exists($titleCacheKey, $requestCache['titles'])) {
                    $titleLocalesBySubmissionId[$translation['id']] = $translation['locale'];
                }
            }
        }

        if (!empty($titleLocalesBySubmissionId)) {
            $titles = $translationsDao->getTitlesBySubmissionIds(array_keys($titleLocalesBySubmissionId), $titleLocalesBySubmissionId);

            foreach ($titleLocalesBySubmissionId as $titleSubmissionId => $locale) {
                $requestCache['titles'][$this->getTitleCacheKey($titleSubmissionId, $locale)] = $titles[$titleSubmissionId] ?? '';
            }
        }

        foreach ($missingSubmissionIds as $submissionId) {
            $mappedTranslations = [];

            foreach ($groupedTranslations[$submissionId] ?? [] as $translation) {
                $mappedTranslations[] = [
                    'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), $place, $this->getPlaceOperation($place), $translation['id']),
                    'locale' => $translation['locale'],
                    'localeName' => $localeNames[$translation['locale']] ?? $translation['locale'],
                    'title' => $requestCache['titles'][$this->getTitleCacheKey($translation['id'], $translation['locale'])] ?? ''
                ];
            }

            $requestCache['translations'][$this->getTranslationsCacheKey($contextId, $place, $submissionId)] = $mappedTranslations;
        }

        $this->setRequestCache($request, $requestCache);
    }

    public function getTranslatedSubmissionData(int $translatedSubmissionId, string $place): array
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $cacheKey = $this->getTranslatedSubmissionDataCacheKey($context->getId(), $place, $translatedSubmissionId);
        $requestCache = $this->getRequestCache($request);

        if (array_key_exists($cacheKey, $requestCache['translatedSubmissions'])) {
            return $requestCache['translatedSubmissions'][$cacheKey];
        }

        $url = $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), $place, $this->getPlaceOperation($place), $translatedSubmissionId);
        $data = [
            'url' => $url,
            'title' => $this->getTitle($translatedSubmissionId)
        ];

        $requestCache['translatedSubmissions'][$cacheKey] = $data;
        $this->setRequestCache($request, $requestCache);

        return $data;
    }

    public static function clearRequestCache(): void
    {
        Registry::delete('plugins.generic.doiForTranslation.requestCache');
    }

    protected function createTranslationsDao()
    {
        $translationsDao = DAORegistry::getDAO('TranslationsDAO');

        if (!$translationsDao) {
            $translationsDao = new TranslationsDAO();
            DAORegistry::registerDAO('TranslationsDAO', $translationsDao);
        }

        return $translationsDao;
    }

    protected function getRequest()
    {
        return Application::get()->getRequest();
    }

    protected function getLocaleNames(): array
    {
        return AppLocale::getAllLocales();
    }

    protected function getPlaceOperation(string $place): string
    {
        return [
            self::PLACE_WORKFLOW => 'access',
            self::PLACE_ARTICLE => 'view',
        ][$place];
    }

    protected function shouldOnlyLoadPublishedTranslations(string $place): bool
    {
        return $place === self::PLACE_ARTICLE;
    }

    private function getTitle(int $submissionId, string $locale = null): string
    {
        $titleCacheKey = $this->getTitleCacheKey($submissionId, $locale);
        $request = $this->getRequest();
        $requestCache = $this->getRequestCache($request);

        if (!array_key_exists($titleCacheKey, $requestCache['titles'])) {
            $translationsDao = $this->createTranslationsDao();
            $titles = $translationsDao->getTitlesBySubmissionIds(
                [$submissionId],
                is_null($locale) ? [] : [$submissionId => $locale]
            );
            $requestCache['titles'][$titleCacheKey] = $titles[$submissionId] ?? '';
            $this->setRequestCache($request, $requestCache);
        }

        return $requestCache['titles'][$titleCacheKey];
    }

    private function getTranslationsCacheKey(int $contextId, string $place, int $submissionId): string
    {
        return implode(':', [$contextId, $place, $submissionId]);
    }

    private function getTitleCacheKey(int $submissionId, string $locale = null): string
    {
        return implode(':', [$submissionId, $locale ?? '__default__']);
    }

    private function getTranslatedSubmissionDataCacheKey(int $contextId, string $place, int $submissionId): string
    {
        return implode(':', [$contextId, $place, $submissionId]);
    }

    private function getRequestCache($request): array
    {
        $allRequestCaches = Registry::get('plugins.generic.doiForTranslation.requestCache', true, []);
        $requestCacheKey = $this->getRequestCacheKey($request);

        if (!isset($allRequestCaches[$requestCacheKey])) {
            $allRequestCaches[$requestCacheKey] = [
                'translations' => [],
                'titles' => [],
                'translatedSubmissions' => [],
            ];
            Registry::set('plugins.generic.doiForTranslation.requestCache', $allRequestCaches);
        }

        return $allRequestCaches[$requestCacheKey];
    }

    private function setRequestCache($request, array $requestCache): void
    {
        $allRequestCaches = Registry::get('plugins.generic.doiForTranslation.requestCache', true, []);
        $allRequestCaches[$this->getRequestCacheKey($request)] = $requestCache;
        Registry::set('plugins.generic.doiForTranslation.requestCache', $allRequestCaches);
    }

    private function getRequestCacheKey($request): string
    {
        return spl_object_hash($request);
    }
}
