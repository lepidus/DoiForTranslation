<?php

import('lib.pkp.classes.handler.APIHandler');
import('plugins.generic.doiForTranslation.classes.TranslationCreator');

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
        import('lib.pkp.classes.security.authorization.PolicySet');
        $rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

        import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function createTranslation($slimRequest, $response, $args)
    {
        $requestParams = $slimRequest->getParsedBody();
        $translationLocale = $requestParams['translationLocale'];
        $submission = $this->getSubmission($slimRequest);

        if (is_null($translationLocale)
            || $translationLocale == $submission->getData('locale')
            || !is_null($submission->getData('isTranslationOf'))
        ) {
            return $response->withStatus(400);
        }

        $translationCreator = new TranslationCreator();
        $translationSubmissionId = $translationCreator->createTranslation($submission->getId(), $translationLocale);

        return $response->withStatus(201);
    }

    private function getSubmission($slimRequest)
    {
        $queryParams = $slimRequest->getQueryParams();
        $submissionId = (int) $queryParams['submissionId'];

        $submissionService = Services::get('submission');
        return $submissionService->get($submissionId);
    }
}
