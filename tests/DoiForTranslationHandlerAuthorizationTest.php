<?php

use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation\DoiForTranslationHandler;
use APP\submission\Submission;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class DoiForTranslationHandlerAuthorizationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    public function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function testRejectsCreateTranslationWhenSubmissionBelongsToAnotherContext(): void
    {
        $localContextId = $this->createContext('dftHandlerLocal');
        $foreignContextId = $this->createContext('dftHandlerForeign');
        $foreignSubmissionId = $this->createSubmissionInContext($foreignContextId);
        $request = $this->buildCreateTranslationRequest($localContextId, $foreignSubmissionId);

        $response = $this->buildHandler($localContextId)->createTranslation($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRejectsCreateTranslationForForeignSubmissionRoute(): void
    {
        $requestContextId = $this->createContext('dftReqCtx');
        $routeContextId = $this->createContext('dftRouteCtx');
        $routeSubmissionId = $this->createSubmissionInContext($routeContextId);
        $request = $this->buildCreateTranslationRequest($routeContextId, $routeSubmissionId);

        $response = $this->buildHandler($requestContextId)->createTranslation($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $this->assertSame(1, $this->countSubmissionsInContext($routeContextId));
    }

    public function testRejectsTranslationDataForForeignSubmissionRoute(): void
    {
        $requestContextId = $this->createContext('dftDataReqCtx');
        $routeContextId = $this->createContext('dftDataRouteCtx');
        $routeSubmissionId = $this->createSubmissionInContext($routeContextId);
        $request = $this->buildTranslationDataRequest($routeContextId, $routeSubmissionId);

        $response = $this->buildHandler($requestContextId)->getTranslationData($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRejectsTranslationDataWhenSubmissionBelongsToAnotherContext(): void
    {
        $localContextId = $this->createContext('dftDataLocal');
        $foreignContextId = $this->createContext('dftDataForeign');
        $foreignSubmissionId = $this->createSubmissionInContext($foreignContextId);
        $request = $this->buildTranslationDataRequest($localContextId, $foreignSubmissionId);

        $response = $this->buildHandler($localContextId)->getTranslationData($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    private function buildHandler(int $requestContextId): DoiForTranslationHandler
    {
        return new class ($requestContextId) extends DoiForTranslationHandler {
            public function __construct(private int $requestContextId)
            {
                parent::__construct();
            }

            protected function getRequestContextId(): ?int
            {
                return $this->requestContextId;
            }

            protected function getSupportedSubmissionLocales(int $contextId): array
            {
                return ['en', 'pt_BR'];
            }

            protected function getExistingTranslationLocales(int $submissionId): array
            {
                return [];
            }
        };
    }

    private function countSubmissionsInContext(int $contextId): int
    {
        return DB::table('submissions')->where('context_id', $contextId)->count();
    }

    private function createSubmissionInContext(int $contextId): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', Submission::STATUS_QUEUED);
        $submission->setData('locale', 'en');

        return Repo::submission()->dao->insert($submission);
    }

    private function createContext(string $pathPrefix): int
    {
        return DB::table('journals')->insertGetId([
            'path' => $pathPrefix . uniqid(),
            'seq' => 0,
            'primary_locale' => 'en',
            'enabled' => 1,
        ]);
    }

    private function buildCreateTranslationRequest(int $contextId, int $submissionId): IlluminateRequest
    {
        $request = IlluminateRequest::create(
            '/index/api/v1/submissions/' . $submissionId . '/doiForTranslation/create',
            'POST',
            ['submissionId' => $submissionId, 'translationLocale' => 'pt_BR']
        );
        $request->setRouteResolver(fn () => new class ($submissionId) {
            public function __construct(private int $submissionId)
            {
            }

            public function parameter(string $name, $default = null)
            {
                return $name === 'submissionId' ? $this->submissionId : $default;
            }
        });

        return $request;
    }

    private function buildTranslationDataRequest(int $contextId, int $submissionId): IlluminateRequest
    {
        $request = IlluminateRequest::create(
            '/index/api/v1/submissions/' . $submissionId . '/doiForTranslation',
            'GET',
            ['submissionId' => $submissionId]
        );
        $request->setRouteResolver(fn () => new class ($submissionId) {
            public function __construct(private int $submissionId)
            {
            }

            public function parameter(string $name, $default = null)
            {
                return $name === 'submissionId' ? $this->submissionId : $default;
            }
        });

        return $request;
    }
}
