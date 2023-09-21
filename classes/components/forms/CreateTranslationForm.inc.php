<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldSelect;

define('FORM_CREATE_TRANSLATION', 'createTranslation');

class CreateTranslationForm extends FormComponent
{
    public function __construct($action, $submission)
    {
        $this->action = $action;
        $this->id = FORM_CREATE_TRANSLATION;
        $this->method = 'POST';

        $availableLocales = $this->getAvailableLocalesForTranslation($submission);

        $this->addField(new FieldSelect('translationLocale', [
            'label' => __('plugins.generic.submissionsTranslation.translationLocale.label'),
            'description' => __('plugins.generic.submissionsTranslation.translationLocale.description'),
            'options' => $availableLocales
        ]));
    }

    private function getAvailableLocalesForTranslation($submission): array
    {
        $context = Application::get()->getRequest()->getContext();
        $supportedSubmissionLocales = $context->getSupportedSubmissionLocaleNames();
        $originalSubmissionLocale = $submission->getData('locale');

        unset($supportedSubmissionLocales[$originalSubmissionLocale]);

        return $supportedSubmissionLocales;
    }
}
