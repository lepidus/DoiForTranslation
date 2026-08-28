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

        $response = (new DoiForTranslationHandler())->createTranslation($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testRejectsTranslationDataWhenSubmissionBelongsToAnotherContext(): void
    {
        $localContextId = $this->createContext('dftDataLocal');
        $foreignContextId = $this->createContext('dftDataForeign');
        $foreignSubmissionId = $this->createSubmissionInContext($foreignContextId);
        $request = $this->buildTranslationDataRequest($localContextId, $foreignSubmissionId);

        $response = (new DoiForTranslationHandler())->getTranslationData($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
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
            '/index/api/v1/contexts/' . $contextId . '/doiForTranslation/create',
            'POST',
            ['submissionId' => $submissionId, 'translationLocale' => 'pt_BR']
        );
        $request->setRouteResolver(fn () => new class ($contextId) {
            public function __construct(private int $contextId)
            {
            }

            public function parameter(string $name, $default = null)
            {
                return $name === 'contextId' ? $this->contextId : $default;
            }
        });

        return $request;
    }

    private function buildTranslationDataRequest(int $contextId, int $submissionId): IlluminateRequest
    {
        $request = IlluminateRequest::create(
            '/index/api/v1/contexts/' . $contextId . '/doiForTranslation',
            'GET',
            ['submissionId' => $submissionId]
        );
        $request->setRouteResolver(fn () => new class ($contextId) {
            public function __construct(private int $contextId)
            {
            }

            public function parameter(string $name, $default = null)
            {
                return $name === 'contextId' ? $this->contextId : $default;
            }
        });

        return $request;
    }
}
