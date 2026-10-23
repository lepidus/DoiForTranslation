<?php

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

class CrossrefTranslationRelationTest extends TestCase
{
    private const CROSSREF_NAMESPACE = 'http://www.crossref.org/schema/5.4.0';
    private const RELATIONS_NAMESPACE = 'http://www.crossref.org/relations.xsd';

    private $contextId;

    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Hook::add('Schema::get::submission', (new DoiForTranslationPlugin())->addOurFieldsToSubmissionSchema(...));
        app()->get('schema')->get(PKPSchemaService::SCHEMA_SUBMISSION, true);
        $this->simulateCommandLineRequestWithoutJournal();
        $this->contextId = $this->createContext('dftRelation');
    }

    public function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function testAddsRelationUsingTheJournalOfTheExportedArticleWhenRequestHasNoJournal(): void
    {
        $originalDoi = $this->uniqueDoi();
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en', $originalDoi);
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $relations = $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation');
        $this->assertSame(1, $relations->length);
        $this->assertSame($originalDoi, $relations->item(0)->nodeValue);
    }

    public function testOriginalLanguageTitleUsesTheLanguageOfTheOriginal(): void
    {
        $interfaceLocale = Locale::getLocale();
        $originalLocale = $interfaceLocale === 'pt_BR' ? 'en' : 'pt_BR';
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, $originalLocale, $this->uniqueDoi());
        $this->addTitle($originalId, $interfaceLocale, 'Title in the interface language');
        $this->createSubmission($this->contextId, $interfaceLocale, $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $originalTitle = $document->getElementsByTagNameNS(self::CROSSREF_NAMESPACE, 'original_language_title')->item(0);
        $this->assertSame(LocaleConversion::getIso1FromLocale($originalLocale), $originalTitle->getAttribute('language'));
        $this->assertSame('Title ' . $originalLocale, $originalTitle->nodeValue);
    }

    public function testAddsRelationWhenVersionsOfTheExportedArticleShareTheDoi(): void
    {
        $originalDoi = $this->uniqueDoi();
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en', $originalDoi);
        $translationId = $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $this->addVersionSharingDoi($translationId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $relations = $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation');
        $this->assertSame(1, $relations->length);
        $this->assertSame($originalDoi, $relations->item(0)->nodeValue);
    }

    public function testSkipsRelationWhenPluginIsDisabledInTheJournalOfTheExportedArticle(): void
    {
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en', $this->uniqueDoi());
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $this->assertSame(0, $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation')->length);
    }

    public function testSkipsRelationWhenDoiBelongsToMoreThanOneJournal(): void
    {
        $otherContextId = $this->createContext('dftRelationOther');
        $translationDoi = $this->uniqueDoi();
        $originalId = $this->createSubmission($this->contextId, 'en', $this->uniqueDoi());
        $this->createSubmission($this->contextId, 'pt_BR', $translationDoi, $originalId);
        $this->createSubmission($otherContextId, 'en', $translationDoi);
        $document = $this->buildCrossrefDocument($translationDoi);

        $this->buildPlugin([$this->contextId, $otherContextId])->addCrossrefTranslationRelation('articlecrossrefxmlfilter::execute', [&$document]);

        $this->assertSame(0, $document->getElementsByTagNameNS(self::RELATIONS_NAMESPACE, 'intra_work_relation')->length);
    }

    private function simulateCommandLineRequestWithoutJournal(): void
    {
        $router = new PageRouter();
        $router->setApplication(Application::get());
        Application::get()->getRequest()->setRouter($router);
    }

    private function buildPlugin(array $enabledContextIds): DoiForTranslationPlugin
    {
        return new class ($enabledContextIds) extends DoiForTranslationPlugin {
            public function __construct(private array $enabledContextIds)
            {
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
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', Submission::STATUS_PUBLISHED);
        $submission->setData('locale', $locale);
        $submissionId = Repo::submission()->dao->insert($submission);

        if (!is_null($isTranslationOf)) {
            DB::table('submission_settings')->insert([
                'submission_id' => $submissionId,
                'locale' => '',
                'setting_name' => 'isTranslationOf',
                'setting_value' => (string) $isTranslationOf,
            ]);
        }

        $publication = new Publication();
        $publication->setData('status', Submission::STATUS_PUBLISHED);
        $publication->setData('version', 1);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', $locale);
        $publication->setData('title', 'Title ' . $locale, $locale);
        $publication->setData('doiId', Repo::doi()->add(Repo::doi()->newDataObject([
            'doi' => $doi,
            'contextId' => $contextId,
        ])));
        $publicationId = Repo::publication()->dao->insert($publication);

        $submission = Repo::submission()->get($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        Repo::submission()->dao->update($submission);

        return $submissionId;
    }

    private function addVersionSharingDoi(int $submissionId): void
    {
        $currentPublication = Repo::submission()->get($submissionId)->getCurrentPublication();
        $newVersion = clone $currentPublication;
        $newVersion->setData('id', null);
        $newVersion->setData('version', 2);
        Repo::publication()->dao->insert($newVersion);
    }

    private function addTitle(int $submissionId, string $locale, string $title): void
    {
        DB::table('publication_settings')->insert([
            'publication_id' => Repo::submission()->get($submissionId)->getData('currentPublicationId'),
            'locale' => $locale,
            'setting_name' => 'title',
            'setting_value' => $title,
        ]);
    }

    private function uniqueDoi(): string
    {
        return '10.1234/dft.' . uniqid();
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
}
