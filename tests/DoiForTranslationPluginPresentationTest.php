<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');
import('plugins.generic.doiForTranslation.classes.TranslationsService');

class DoiForTranslationPluginPresentationTest extends TestCase
{
    public function testWorkflowShowsAvailableTranslationsForOriginalSubmission(): void
    {
        $service = new FakeRenderedTranslationsService();
        $templateMgr = new FakePluginTemplateManager([
            'submission' => new FakeSubmissionWithData(10, 'en_US'),
            'requestedPage' => 'workflow',
        ]);
        $plugin = new TestableHookBehaviorPlugin($service);

        $plugin->addWorkflowModifications('Template::Workflow', [null, $templateMgr]);

        $this->assertSame('nonTranslationWorkflowFilter', $templateMgr->registeredFilter[1]);
        $this->assertTrue($templateMgr->assigned['hasTranslations']);
        $this->assertCount(1, $templateMgr->assigned['translations']);
    }

    public function testPublicSummaryShowsListOfAvailableTranslationsForOriginalArticle(): void
    {
        $service = new FakeRenderedTranslationsService();
        $templateMgr = new FakePluginTemplateManager([
            'article' => new FakeSubmissionWithData(10, 'en_US'),
            'requestedPage' => 'issue',
            'publishedSubmissions' => [],
        ]);
        $output = '';
        $plugin = new TestableHookBehaviorPlugin($service);

        $plugin->addPublicSiteModifications('Templates::Issue::Issue::Article', [null, $templateMgr, &$output]);

        $this->assertStringContainsString('listTranslationsSummary.tpl', $output);
        $this->assertSame([
            [
                'url' => '/article/view/11',
                'locale' => 'pt_BR',
                'localeName' => 'Português (Brasil)',
                'title' => 'Titulo em Portugues',
            ],
        ], $templateMgr->assigned['translations']);
    }

    public function testPublicArticleShowsReferenceToOriginalWhenViewingTranslation(): void
    {
        $service = new FakeTranslatedSubmissionService();
        $templateMgr = new FakePluginTemplateManager([
            'article' => new FakeSubmissionWithData(11, 'pt_BR', 5),
            'requestedPage' => 'article',
        ]);
        $output = '';
        $plugin = new TestableHookBehaviorPlugin($service);

        $plugin->addPublicSiteModifications('Templates::Article::Main', [null, $templateMgr, &$output]);

        $this->assertStringContainsString('refTranslatedArticlePage.tpl', $output);
        $this->assertSame('/article/view/5', $templateMgr->assigned['translatedSubmission']['url']);
        $this->assertSame('Português (Brasil)', $templateMgr->assigned['translationLocale']);
    }
}

class TestableHookBehaviorPlugin extends DoiForTranslationPlugin
{
    private $translationsService;

    public function __construct($translationsService = null)
    {
        $this->translationsService = $translationsService;
    }

    protected function createTranslationsService(): TranslationsService
    {
        return $this->translationsService ?? new TranslationsService();
    }

    public function getTemplateResource($template = null, $inCore = false)
    {
        return $template;
    }
}

class FakeTranslatedSubmissionService extends TranslationsService
{
    public $calls = [];

    public function getTranslatedSubmissionData(int $translatedSubmissionId, string $place): array
    {
        $this->calls[] = [$translatedSubmissionId, $place];

        return [
            'url' => sprintf('/%s/%s/%d', $place, $place === self::PLACE_WORKFLOW ? 'access' : 'view', $translatedSubmissionId),
            'title' => 'Original title',
        ];
    }
}

class FakeRenderedTranslationsService extends TranslationsService
{
    public function getTranslations(int $submissionId, string $place): array
    {
        return [
            [
                'url' => '/article/view/11',
                'locale' => 'pt_BR',
                'localeName' => 'Português (Brasil)',
                'title' => 'Titulo em Portugues',
            ],
        ];
    }

    public function prefetchTranslations(array $submissionIds, string $place): void
    {
    }
}

class FakePluginTemplateManager
{
    private $templateVars;
    public $state = [];
    public $assigned = [];
    public $registeredFilter = [];

    public function __construct(array $templateVars)
    {
        $this->templateVars = $templateVars;
    }

    public function getTemplateVars(string $key)
    {
        return $this->templateVars[$key] ?? null;
    }

    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function assign($key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $name => $assignedValue) {
                $this->assigned[$name] = $assignedValue;
            }

            return;
        }

        $this->assigned[$key] = $value;
    }

    public function fetch(string $resource): string
    {
        return sprintf('[rendered:%s]', $resource);
    }

    public function registerFilter(string $type, array $filter): void
    {
        $this->registeredFilter = $filter;
    }
}

class FakeSubmissionWithData
{
    private $id;
    private $locale;
    private $isTranslationOf;

    public function __construct(int $id, string $locale, ?int $isTranslationOf = null)
    {
        $this->id = $id;
        $this->locale = $locale;
        $this->isTranslationOf = $isTranslationOf;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getData(string $key)
    {
        if ($key === 'isTranslationOf') {
            return $this->isTranslationOf;
        }

        if ($key === 'locale') {
            return $this->locale;
        }

        return null;
    }
}
