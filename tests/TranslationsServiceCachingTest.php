<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.doiForTranslation.classes.TranslationsService');

class TranslationsServiceCachingTest extends TestCase
{
    protected function setUp(): void
    {
        TranslationsService::clearRequestCache();
    }

    public function testGetTranslationsCachesResultsWithinRequest(): void
    {
        $dao = new FakeTranslationsDao();
        $service = new TestableTranslationsService($dao);

        $firstResult = $service->getTranslations(10, 'workflow');
        $secondResult = $service->getTranslations(10, 'workflow');

        $this->assertSame($firstResult, $secondResult);
        $this->assertSame(1, $dao->getTranslationsBySubmissionIdsCalls);
        $this->assertSame(1, $dao->getTitlesBySubmissionIdsCalls);
        $this->assertSame([10], $dao->lastGroupedSubmissionIds);
        $this->assertSame([11 => 'pt_BR'], $dao->lastTitleLocalesBySubmissionId);
    }

    public function testPrefetchPublicTranslationsReusesGroupedData(): void
    {
        $dao = new FakeTranslationsDao([
            10 => [
                ['id' => 11, 'locale' => 'pt_BR'],
            ],
            20 => [
                ['id' => 21, 'locale' => 'es_ES'],
            ],
        ], [
            11 => 'Titulo em Portugues',
            21 => 'Titulo en Espanol',
        ]);
        $service = new TestableTranslationsService($dao);

        $service->prefetchTranslations([10, 20], 'article');

        $firstTranslations = $service->getTranslations(10, 'article');
        $secondTranslations = $service->getTranslations(20, 'article');

        $this->assertCount(1, $firstTranslations);
        $this->assertCount(1, $secondTranslations);
        $this->assertSame(1, $dao->getTranslationsBySubmissionIdsCalls);
        $this->assertSame(1, $dao->getTitlesBySubmissionIdsCalls);
        $this->assertSame([10, 20], $dao->lastGroupedSubmissionIds);
        $this->assertSame([11 => 'pt_BR', 21 => 'es_ES'], $dao->lastTitleLocalesBySubmissionId);
    }

}

class TestableTranslationsService extends TranslationsService
{
    private $dao;
    private $request;

    public function __construct($dao)
    {
        $this->dao = $dao;
        $this->request = new FakeRequest();
    }

    protected function createTranslationsDao()
    {
        return $this->dao;
    }

    protected function getRequest()
    {
        return $this->request;
    }

    protected function getLocaleNames(): array
    {
        return [
            'pt_BR' => 'Português (Brasil)',
            'es_ES' => 'Español',
        ];
    }
}

class FakeTranslationsDao
{
    public $getTranslationsBySubmissionIdsCalls = 0;
    public $getTitlesBySubmissionIdsCalls = 0;
    public $lastGroupedSubmissionIds = [];
    public $lastTitleLocalesBySubmissionId = [];
    private $translationsBySubmissionId;
    private $titlesBySubmissionId;

    public function __construct(array $translationsBySubmissionId = null, array $titlesBySubmissionId = null)
    {
        $this->translationsBySubmissionId = $translationsBySubmissionId ?? [
            10 => [
                ['id' => 11, 'locale' => 'pt_BR'],
            ],
        ];
        $this->titlesBySubmissionId = $titlesBySubmissionId ?? [
            11 => 'Titulo em Portugues',
        ];
    }

    public function getTranslationsBySubmissionIds(array $submissionIds, int $contextId, bool $onlyPublished = false): array
    {
        $this->getTranslationsBySubmissionIdsCalls++;
        $this->lastGroupedSubmissionIds = array_values($submissionIds);

        $result = [];
        foreach ($submissionIds as $submissionId) {
            $result[$submissionId] = $this->translationsBySubmissionId[$submissionId] ?? [];
        }

        return $result;
    }

    public function getTitlesBySubmissionIds(array $submissionIds, array $localesBySubmissionId = []): array
    {
        $this->getTitlesBySubmissionIdsCalls++;
        $this->lastTitleLocalesBySubmissionId = $localesBySubmissionId;

        $titles = [];
        foreach ($submissionIds as $submissionId) {
            $titles[$submissionId] = $this->titlesBySubmissionId[$submissionId] ?? '';
        }

        return $titles;
    }
}

class FakeRequest
{
    public function getContext()
    {
        return new class () {
            public function getId(): int
            {
                return 1;
            }

            public function getPath(): string
            {
                return 'test-context';
            }
        };
    }

    public function getDispatcher()
    {
        return new class () {
            public function url($request, $route, $contextPath, $page, $op, $submissionId): string
            {
                return sprintf('/%s/%s/%s/%d', $contextPath, $page, $op, $submissionId);
            }
        };
    }
}
