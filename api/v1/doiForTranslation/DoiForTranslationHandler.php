<?php

namespace APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation;

use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\classes\components\forms\CreateTranslationForm;
use APP\plugins\generic\doiForTranslation\classes\TranslationCreator;
use APP\plugins\generic\doiForTranslation\classes\TranslationLocaleValidator;
use APP\plugins\generic\doiForTranslation\classes\TranslationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request as IlluminateRequest;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\handler\APIHandler;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use Symfony\Component\HttpFoundation\Response;

class DoiForTranslationHandler implements HasAuthorizationPolicy
{
    public function __construct(private ?GenericPlugin $plugin = null)
    {
    }

    public function registerRoute(string $hookName, PKPBaseController $apiController, APIHandler $apiHandler): bool
    {
        $roles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        $apiHandler->addRoute(
            'GET',
            '{submissionId}/doiForTranslation',
            $this->getTranslationData(...),
            'doiForTranslation.getTranslationData',
            $roles,
            $this
        );
        $apiHandler->addRoute(
            'POST',
            '{submissionId}/doiForTranslation/create',
            $this->createTranslation(...),
            'doiForTranslation.createTranslation',
            $roles,
            $this
        );

        return Hook::CONTINUE;
    }

    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        return [new SubmissionAccessPolicy($request, $args, $roleAssignments)];
    }

    public function getTranslationData(IlluminateRequest $request): JsonResponse
    {
        $contextId = (int) $this->getRequestContextId();
        $submissionId = (int) $request->route('submissionId');
        $submission = $this->getSubmission($contextId, $submissionId);

        if (!$submission) {
            return response()->json(null, Response::HTTP_NOT_FOUND);
        }

        $translationsService = new TranslationsService();
        $translatedSubmissionId = $submission->getData('isTranslationOf');

        if ($translatedSubmissionId) {
            return response()->json([
                'translatedSubmission' => $translationsService->getTranslatedSubmissionData(
                    (int) $translatedSubmissionId,
                    TranslationsService::PLACE_WORKFLOW
                ),
                'translations' => [],
                'createTranslationForm' => null,
            ]);
        }

        $createTranslationForm = new CreateTranslationForm(
            $this->getCreateTranslationUrl($contextId, $submissionId),
            $submission
        );

        return response()->json([
            'translatedSubmission' => null,
            'translations' => $translationsService->getTranslations(
                $submissionId,
                TranslationsService::PLACE_WORKFLOW
            ),
            'createTranslationForm' => $createTranslationForm->getConfig(),
        ]);
    }

    public function createTranslation(IlluminateRequest $request): JsonResponse
    {
        $contextId = (int) $this->getRequestContextId();
        $submissionId = (int) $request->route('submissionId');
        $translationLocale = $request->input('translationLocale', '');
        $submission = $this->getSubmission($contextId, $submissionId);

        if (!$submission) {
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

    private function getSubmission(int $contextId, int $submissionId)
    {
        if ($contextId !== $this->getRequestContextId()) {
            return null;
        }

        if ($this->plugin && !$this->plugin->getEnabled($contextId)) {
            return null;
        }

        $submission = Repo::submission()->get($submissionId);

        if (!$submission || (int) $submission->getData('contextId') !== $contextId) {
            return null;
        }

        return $submission;
    }

    private function getCreateTranslationUrl(int $contextId, int $submissionId): string
    {
        $request = Application::get()->getRequest();
        $context = app()->get('context')->get($contextId);

        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'submissions/' . $submissionId . '/doiForTranslation/create',
            null,
            null
        );
    }

    protected function getRequestContextId(): ?int
    {
        return Application::get()->getRequest()->getContext()?->getId();
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
