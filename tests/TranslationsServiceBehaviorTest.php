<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.doiForTranslation.classes.TranslationsService');

class TranslationsServiceBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        TranslationsService::clearRequestCache();
    }

    public function testReturnsStableTranslationDataWithinSameRequest(): void
    {
        $dao = new FakeTranslationsDao();
        $service = new TestableTranslationsService($dao);

        $firstResult = $service->getTranslations(10, 'workflow');
        $secondResult = $service->getTranslations(10, 'workflow');

        $this->assertSame($firstResult, $secondResult);
        $this->assertSame([
            [
                'url' => '/test-context/workflow/access/11',
                'locale' => 'pt_BR',
                'localeName' => 'Português (Brasil)',
                'title' => 'Titulo em Portugues',
            ],
            [
                'url' => '/test-context/workflow/access/12',
                'locale' => 'es_ES',
                'localeName' => 'Español',
                'title' => 'Titulo en Espanol',
            ],
        ], $firstResult);
    }

    public function testWorkflowListsAvailableTranslationsThroughAccessLinks(): void
    {
        $dao = new FakeTranslationsDao();
        $service = new TestableTranslationsService($dao);

        $translations = $service->getTranslations(10, TranslationsService::PLACE_WORKFLOW);

        $this->assertCount(2, $translations);
        $this->assertSame('/test-context/workflow/access/11', $translations[0]['url']);
        $this->assertSame('/test-context/workflow/access/12', $translations[1]['url']);
    }

    public function testPublicArticleListsOnlyPublishedTranslationsThroughViewLinks(): void
    {
        $dao = new FakeTranslationsDao();
        $service = new TestableTranslationsService($dao);

        $translations = $service->getTranslations(10, TranslationsService::PLACE_ARTICLE);

        $this->assertCount(1, $translations);
        $this->assertSame('/test-context/article/view/11', $translations[0]['url']);
        $this->assertSame('pt_BR', $translations[0]['locale']);
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
    private $workflowTranslationsBySubmissionId;
    private $publishedTranslationsBySubmissionId;
    private $titlesBySubmissionId;

    public function __construct(
        array $workflowTranslationsBySubmissionId = null,
        array $publishedTranslationsBySubmissionId = null,
        array $titlesBySubmissionId = null
    ) {
        $this->workflowTranslationsBySubmissionId = $workflowTranslationsBySubmissionId ?? [
            10 => [
                ['id' => 11, 'locale' => 'pt_BR'],
                ['id' => 12, 'locale' => 'es_ES'],
            ],
        ];
        $this->publishedTranslationsBySubmissionId = $publishedTranslationsBySubmissionId ?? [
            10 => [
                ['id' => 11, 'locale' => 'pt_BR'],
            ],
        ];
        $this->titlesBySubmissionId = $titlesBySubmissionId ?? [
            11 => 'Titulo em Portugues',
            12 => 'Titulo en Espanol',
        ];
    }

    public function getTranslationsBySubmissionIds(array $submissionIds, int $contextId, bool $onlyPublished = false): array
    {
        $result = [];
        $source = $onlyPublished ? $this->publishedTranslationsBySubmissionId : $this->workflowTranslationsBySubmissionId;

        foreach ($submissionIds as $submissionId) {
            $result[$submissionId] = $source[$submissionId] ?? [];
        }

        return $result;
    }

    public function getTitlesBySubmissionIds(array $submissionIds, array $localesBySubmissionId = []): array
    {
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
