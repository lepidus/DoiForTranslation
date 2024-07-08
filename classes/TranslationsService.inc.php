<?php

import('plugins.generic.DoiForTranslation.classes.TranslationsDAO');

class TranslationsService
{
    public function getTranslations(int $submissionId, string $place): array
    {
        $translationsDao = new TranslationsDAO();
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $localeNames = AppLocale::getAllLocales();

        $mapPlaceOp = ['workflow' => 'access', 'article' => 'view'];
        $onlyPublishedTranslations = ($place == 'article');
        $translations = $translationsDao->getTranslations($submissionId, $onlyPublishedTranslations);
        $mappedTranslations = [];

        foreach($translations as $translation) {
            $title = $translationsDao->getTitle($translation['id'], $translation['locale']);
            $mappedTranslations[] = [
                'url' => $request->getDispatcher()->url($request, ROUTE_PAGE, $context->getPath(), $place, $mapPlaceOp[$place], $translation['id']),
                'locale' => $translation['locale'],
                'localeName' => $localeNames[$translation['locale']],
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
