<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldSelect;

define('FORM_CREATE_TRANSLATION', 'createTranslationForm');
import('plugins.generic.doiForTranslation.classes.TranslationsService');

class CreateTranslationForm extends FormComponent
{
    public function __construct($action, $submission)
    {
        $this->action = $action;
        $this->id = FORM_CREATE_TRANSLATION;
        $this->method = 'POST';

        $availableLocales = $this->getAvailableLocalesForTranslation($submission);

        if (empty($availableLocales)) {
            $submitButton = null;
            $formField = new FieldHTML('noLocalesAvailable', [
                'description' => __('plugins.generic.doiForTranslation.noLocalesAvailable'),
                'groupId' => 'default',
            ]);
        } else {
            $submitButton = ['label' => __('common.create')];
            $formField = new FieldSelect('translationLocale', [
                'groupId' => 'default',
                'isRequired' => true,
                'label' => __('plugins.generic.doiForTranslation.translationLocale.label'),
                'description' => __('plugins.generic.doiForTranslation.translationLocale.description'),
                'options' => $availableLocales
            ]);
        }

        $this->addPage([
            'id' => 'default',
            'submitButton' => $submitButton
        ]);
        $this->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);
        $this->addField($formField);
    }

    private function getAvailableLocalesForTranslation($submission): array
    {
        $context = Application::get()->getRequest()->getContext();
        $supportedSubmissionLocales = $context->getSupportedSubmissionLocaleNames();
        $originalSubmissionLocale = $submission->getData('locale');

        unset($supportedSubmissionLocales[$originalSubmissionLocale]);
        $translationsService = new TranslationsService();
        $translations = $translationsService->getTranslations($submission->getId(), 'workflow');

        foreach ($translations as $translation) {
            $translationLocale = $translation['locale'];
            unset($supportedSubmissionLocales[$translationLocale]);
        }

        $availableLocales = [];
        foreach ($supportedSubmissionLocales as $key => $name) {
            $availableLocales[] = [
                'label' => $name,
                'value' => $key
            ];
        }

        return $availableLocales;
    }
}
