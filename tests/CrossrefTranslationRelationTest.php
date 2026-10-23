<?php

import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class CrossrefTranslationRelationTest extends TestCase
{
    private const CROSSREF_NAMESPACE = 'http://www.crossref.org/schema/4.3.6';
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';

    private $contextId = 1;
    private $otherContextId = 999;

    public function setUp(): void
    {
        Capsule::connection()->beginTransaction();
        $this->loadSubmissionSchemaWithoutPluginFields();
    }

    public function tearDown(): void
    {
        Capsule::connection()->rollBack();
        HookRegistry::register('Schema::get::submission', [new DoiForTranslationPlugin(), 'addOurFieldsToSubmissionSchema']);
        Services::get('schema')->get(SCHEMA_SUBMISSION, true);
    }

    public function testRegistersCrossrefHookWithoutJournalInTheRequest(): void
    {
        HookRegistry::clear('articlecrossrefxmlfilter::execute');
        $request = Application::get()->getRequest();
        $previousRouter = $request->getRouter();
        $this->simulateCommandLineRouter($request);

        (new DoiForTranslationPlugin())->register('generic', 'plugins/generic/doiForTranslation');

        $request->setRouter($previousRouter);
        $this->assertNotEmpty(HookRegistry::getHooks('articlecrossrefxmlfilter::execute'));
    }

    public function testRegistersCrossrefHookOnlyOnceWhenPluginIsRegisteredAgain(): void
    {
        HookRegistry::clear('articlecrossrefxmlfilter::execute');
        $request = Application::get()->getRequest();
        $previousRouter = $request->getRouter();
        $this->simulateCommandLineRouter($request);

        (new DoiForTranslationPlugin())->register('generic', 'plugins/generic/doiForTranslation');
        (new DoiForTranslationPlugin())->register('generic', 'plugins/generic/doiForTranslation');

        $request->setRouter($previousRouter);
        $this->assertSame(1, $this->countPluginCrossrefHooks());
    }

    public function testAddsRelationUsingTheJournalOfTheExportedArticleWhenRequestHasNoJournal(): void
    {
        $originalDoi = $this->uniqueDoi();
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en_US', $originalDoi);
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $relations = $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation');
        $this->assertSame(1, $relations->length);
        $this->assertSame($originalDoi, $relations->item(0)->nodeValue);
    }

    public function testOriginalLanguageTitleUsesTheLanguageOfTheOriginal(): void
    {
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'pt_BR', $this->uniqueDoi());
        $this->addTitle($originalId, 'en_US', 'English title of the original');
        $this->createSubmission($this->contextId, 'en_US', $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $originalTitle = $document->getElementsByTagNameNS(self::CROSSREF_NAMESPACE, 'original_language_title')->item(0);
        $this->assertSame('pt', $originalTitle->getAttribute('language'));
        $this->assertSame('Title pt_BR', $originalTitle->nodeValue);
    }

    public function testAddsRelationWhenVersionsOfTheExportedArticleShareTheDoi(): void
    {
        $originalDoi = $this->uniqueDoi();
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en_US', $originalDoi);
        $translationId = $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $this->createPublication($translationId, 'pt_BR', $translationDoi, 2);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $relations = $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation');
        $this->assertSame(1, $relations->length);
        $this->assertSame($originalDoi, $relations->item(0)->nodeValue);
    }

    public function testSkipsRelationWhenPluginIsDisabledInTheJournalOfTheExportedArticle(): void
    {
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en_US', $this->uniqueDoi());
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $this->assertSame(0, $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation')->length);
    }

    public function testSkipsRelationWhenDoiBelongsToMoreThanOneJournal(): void
    {
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en_US', $this->uniqueDoi());
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $this->createSubmission($this->otherContextId, 'en_US', $translationDoi);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId, $this->otherContextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $this->assertSame(0, $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation')->length);
    }

    private function countPluginCrossrefHooks(): int
    {
        $count = 0;
        foreach ((array) HookRegistry::getHooks('articlecrossrefxmlfilter::execute') as $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback) && $callback[0] instanceof DoiForTranslationPlugin) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function simulateCommandLineRouter($request): void
    {
        import('classes.core.PageRouter');
        $router = new PageRouter();
        $router->setApplication(Application::get());
        $request->setRouter($router);
    }

    private function loadSubmissionSchemaWithoutPluginFields(): void
    {
        HookRegistry::clear('Schema::get::submission');
        Services::get('schema')->get(SCHEMA_SUBMISSION, true);
    }

    private function buildPlugin(array $enabledContextIds): DoiForTranslationPlugin
    {
        return new class ($enabledContextIds) extends DoiForTranslationPlugin {
            private $enabledContextIds;

            public function __construct(array $enabledContextIds)
            {
                $this->enabledContextIds = $enabledContextIds;
            }

            public function getEnabled($contextId = null)
            {
                return in_array((int) $contextId, $this->enabledContextIds, true);
            }
        };
    }

    private function buildCrossrefDocument(string $doi): DOMDocument
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $article = $document->appendChild($document->createElementNS(self::CROSSREF_NAMESPACE, 'journal_article'));
        $titles = $article->appendChild($document->createElementNS(self::CROSSREF_NAMESPACE, 'titles'));
        $titles->appendChild($document->createElementNS(self::CROSSREF_NAMESPACE, 'title', 'Translated title'));
        $doiData = $article->appendChild($document->createElementNS(self::CROSSREF_NAMESPACE, 'doi_data'));
        $doiData->appendChild($document->createElementNS(self::CROSSREF_NAMESPACE, 'doi', $doi));

        return $document;
    }

    private function createSubmission(int $contextId, string $locale, string $doi, ?int $isTranslationOf = null): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', STATUS_PUBLISHED);
        $submission->setData('locale', $locale);
        $submissionId = $submissionDao->insertObject($submission);

        if (!is_null($isTranslationOf)) {
            Capsule::table('submission_settings')->insert([
                'submission_id' => $submissionId,
                'locale' => '',
                'setting_name' => 'isTranslationOf',
                'setting_value' => (string) $isTranslationOf,
            ]);
        }

        $publicationId = $this->createPublication($submissionId, $locale, $doi, 1);
        $submission = $submissionDao->getById($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->updateObject($submission);

        return $submissionId;
    }

    private function createPublication(int $submissionId, string $locale, string $doi, int $version): int
    {
        $publication = new Publication();
        $publication->setData('status', STATUS_PUBLISHED);
        $publication->setData('version', $version);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', $locale);
        $publication->setData('title', 'Title ' . $locale, $locale);
        $publicationId = DAORegistry::getDAO('PublicationDAO')->insertObject($publication);

        Capsule::table('publication_settings')->insert([
            'publication_id' => $publicationId,
            'locale' => '',
            'setting_name' => 'pub-id::doi',
            'setting_value' => $doi,
        ]);

        return $publicationId;
    }

    private function addTitle(int $submissionId, string $locale, string $title): void
    {
        $publicationId = DAORegistry::getDAO('SubmissionDAO')->getById($submissionId)->getData('currentPublicationId');
        Capsule::table('publication_settings')->insert([
            'publication_id' => $publicationId,
            'locale' => $locale,
            'setting_name' => 'title',
            'setting_value' => $title,
        ]);
    }

    private function uniqueDoi(): string
    {
        return '10.9999/dft.' . uniqid();
    }
}
