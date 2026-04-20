<?php

import('lib.pkp.classes.handler.APIHandler');
import('plugins.generic.doiForTranslation.classes.TranslationCreator');
import('plugins.generic.doiForTranslation.classes.TranslationLocaleValidator');
import('plugins.generic.doiForTranslation.classes.TranslationsService');

class DoiForTranslationHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = 'doiForTranslation';
        $roles = [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR];
        $this->_endpoints = array(
            'POST' => array(
                array(
                    'pattern' => $this->getEndpointPattern() . '/create',
                    'handler' => array($this, 'createTranslation'),
                    'roles' => $roles
                ),
            ),
        );
        parent::__construct();
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function createTranslation($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $translationLocale = $requestParams['translationLocale'] ?? '';
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        if (!$this->getValidator()->isAvailable(
            $translationLocale,
            $submission->getData('locale'),
            !is_null($submission->getData('isTranslationOf')),
            $this->getSupportedSubmissionLocales(),
            $this->getExistingTranslationLocales($submission->getId())
        )) {
            return $response->withStatus(400);
        }

        $translationCreator = new TranslationCreator();
        $translationCreator->createTranslation($submission->getId(), $translationLocale);

        return $response->withStatus(201);
    }

    protected function getValidator(): TranslationLocaleValidator
    {
        return new TranslationLocaleValidator();
    }

    protected function getSupportedSubmissionLocales(): array
    {
        $context = $this->getRequest()->getContext();
        return $context->getSupportedSubmissionLocales() ?: [];
    }

    protected function getExistingTranslationLocales(int $submissionId): array
    {
        $translationsService = new TranslationsService();
        return array_column(
            $translationsService->getTranslations($submissionId, TranslationsService::PLACE_WORKFLOW),
            'locale'
        );
    }
}
