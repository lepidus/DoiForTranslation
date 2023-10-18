<?php

import('plugins.generic.submissionsTranslation.classes.TranslationsDAO');

class TranslationsService
{
    public function getTranslationsWorkflow(int $submissionId): array
    {
        $translationsDao = new TranslationsDAO();
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $localeNames = & AppLocale::getAllLocales();

        $translations = $translationsDao->getTranslations($submissionId);
        $mappedTranslations = [];

        foreach($translations as $translation) {
            $mappedTranslations[] = [
                'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), 'workflow', 'access', $translation['id']),
                'locale' => $translation['locale'],
                'localeName' => $localeNames[$translation['locale']]
            ];
        }

        return $mappedTranslations;
    }

    public function getTranslationsArticlePage(int $submissionId): array
    {
        $translationsDao = new TranslationsDAO();
        $request = Application::get()->getRequest();

        $translations = $translationsDao->getTranslations($submissionId);
        $mappedTranslations = [];

        foreach($translations as $translation) {
            $title = $translationsDao->getTitle($translation['id'], $translation['locale']);
            $mappedTranslations[] = [
                'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'article', 'view', $translation['id']),
                'title' => $title
            ];
        }

        return $mappedTranslations;
    }

    public function getTranslatedSubmissionData(int $translatedSubmissionId, string $place): array
    {
        $translationsDao = new TranslationsDAO();
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $mapPlaceOp = ['workflow' => 'access', 'article' => 'view'];

        $url = $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), $place, $mapPlaceOp[$place], $translatedSubmissionId);
        $title = $translationsDao->getTitle($translatedSubmissionId);

        return [
            'url' => $url,
            'title' => $title
        ];
    }

}
