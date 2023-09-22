<?php

use PKP\components\forms\FormComponent;
use PKP\components\forms\FieldSelect;

define('FORM_CREATE_TRANSLATION', 'createTranslationForm');

class CreateTranslationForm extends FormComponent
{
    public function __construct($action, $submission)
    {
        $this->action = $action;
        $this->id = FORM_CREATE_TRANSLATION;
        $this->method = 'POST';

        $availableLocales = $this->getAvailableLocalesForTranslation($submission);

        $this->addPage([
            'id' => 'default',
            'submitButton' => [
                'label' => __('common.create')
            ],
        ]);
        $this->addGroup([
            'id' => 'default',
            'pageId' => 'default',
        ]);

        $this->addField(new FieldSelect('translationLocale', [
            'groupId' => 'default',
            'isRequired' => true,
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

        $availableLocales = [];
        foreach($supportedSubmissionLocales as $key => $name) {
            $availableLocales[] = [
                'label' => $name,
                'value' => $key
            ];
        }

        return $availableLocales;
    }
}
