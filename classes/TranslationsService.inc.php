<?php

import('plugins.generic.submissionsTranslation.classes.TranslationsDAO');

class TranslationsService
{
    public function getTranslationsForDisplay(int $submissionId): array
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
                'localeName' => $localeNames[$translation['locale']]
            ];
        }

        return $mappedTranslations;
    }

}
