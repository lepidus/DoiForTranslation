<?php

namespace APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation;

use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\classes\TranslationCreator;
use APP\plugins\generic\doiForTranslation\classes\TranslationLocaleValidator;
use APP\plugins\generic\doiForTranslation\classes\TranslationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\Role;
use Symfony\Component\HttpFoundation\Response;

class DoiForTranslationHandler
{
    public function registerRoute(string $hookName, PKPBaseController &$apiController, APIHandler $apiHandler): bool
    {
        $apiHandler->addRoute(
            'POST',
            '{contextId}/doiForTranslation/create',
            $this->createTranslation(...),
            'doiForTranslation.createTranslation',
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]
        );

        return Hook::CONTINUE;
    }

    public function createTranslation(IlluminateRequest $request): JsonResponse
    {
        $contextId = (int) $request->route('contextId');
        $submissionId = (int) $request->input('submissionId');
        $translationLocale = $request->input('translationLocale', '');
        $submission = Repo::submission()->get($submissionId);

        if (!$submission || (int) $submission->getData('contextId') !== $contextId) {
            return response()->json(null, Response::HTTP_NOT_FOUND);
        }

        if (!$this->getValidator()->isAvailable(
            $translationLocale,
            $submission->getData('locale'),
            !is_null($submission->getData('isTranslationOf')),
            $this->getSupportedSubmissionLocales($contextId),
            $this->getExistingTranslationLocales($submission->getId())
        )) {
            return response()->json(null, Response::HTTP_BAD_REQUEST);
        }

        $translationCreator = new TranslationCreator();
        $translationCreator->createTranslation($submission->getId(), $translationLocale);

        return response()->json(null, Response::HTTP_CREATED);
    }

    protected function getValidator(): TranslationLocaleValidator
    {
        return new TranslationLocaleValidator();
    }

    protected function getSupportedSubmissionLocales(int $contextId): array
    {
        $context = app()->get('context')->get($contextId);
        return $context ? ($context->getSupportedSubmissionLocales() ?: []) : [];
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
