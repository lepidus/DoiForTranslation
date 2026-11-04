<?php

use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation\DoiForTranslationHandler;
use APP\plugins\generic\doiForTranslation\classes\TranslationCreator;
use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use APP\submission\Submission;
use Illuminate\Http\Request as IlluminateRequest;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\plugins\Hook;
use Symfony\Component\HttpFoundation\Response;

class CreateTranslationConcurrencyTest extends TestCase
{
    private const TRANSLATION_LOCALE = 'fr_CA';

    private int $contextId;
    private int $originalSubmissionId;

    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $plugin = new DoiForTranslationPlugin();
        Hook::add('Schema::get::submission', $plugin->addOurFieldsToSubmissionSchema(...));

        $this->contextId = $this->createContext();
        $this->originalSubmissionId = $this->createOriginalSubmission();
    }

    public function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function testRejectsRequestValidatedBeforeConcurrentCreationOfSameLocale(): void
    {
        $handler = $this->buildHandlerThatValidatedBeforeAnyTranslationExisted();
        (new TranslationCreator())->createTranslation($this->originalSubmissionId, self::TRANSLATION_LOCALE);

        $response = $handler->createTranslation($this->buildCreateTranslationRequest());

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(1, $this->countTranslationsOf($this->originalSubmissionId));
    }

    private function buildHandlerThatValidatedBeforeAnyTranslationExisted(): DoiForTranslationHandler
    {
        return new class ($this->contextId) extends DoiForTranslationHandler {
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
                return ['en', 'fr_CA'];
            }

            protected function getExistingTranslationLocales(int $submissionId): array
            {
                return [];
            }
        };
    }

    private function countTranslationsOf(int $originalSubmissionId): int
    {
        return DB::table('submission_settings')
            ->where('setting_name', 'isTranslationOf')
            ->where('setting_value', (string) $originalSubmissionId)
            ->count();
    }

    private function createOriginalSubmission(): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $submission->setData('status', Submission::STATUS_QUEUED);
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        return Repo::submission()->dao->insert($submission);
    }

    private function createContext(): int
    {
        return DB::table('journals')->insertGetId([
            'path' => 'dftConcurrency' . uniqid(),
            'seq' => 0,
            'primary_locale' => 'en',
            'enabled' => 1,
        ]);
    }

    private function buildCreateTranslationRequest(): IlluminateRequest
    {
        $submissionId = $this->originalSubmissionId;
        $request = IlluminateRequest::create(
            '/index/api/v1/submissions/' . $submissionId . '/doiForTranslation/create',
            'POST',
            ['submissionId' => $submissionId, 'translationLocale' => self::TRANSLATION_LOCALE]
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
