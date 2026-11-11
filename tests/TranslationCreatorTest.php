<?php

import('lib.pkp.tests.DatabaseTestCase');
import('classes.core.Services');
import('classes.article.Author');
import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.classes.TranslationCreator');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;

class TranslationCreatorTest extends DatabaseTestCase
{
    private $translationCreator;
    private $submissionId;
    private $publicationId;
    private $authorId;
    private $originalLocale = 'en_US';
    private $originalTitle = "Cat species of Egypt";
    private $translationLocale = 'fr_CA';
    private $translationTitle = "Espèces de chats d'Egypte";
    private $authorEmail = 'egyptian.cat@mailinator.com';
    private $authorGivenName = 'Cat';
    private $authorFamilyName = 'Ramesses';
    private $contextId = 1;
    private $publicFiles = [];

    public function setUp(): void
    {
        parent::setUp();

        $plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', array($plugin, 'addOurFieldsToSubmissionSchema'));
        Services::get('schema')->get(SCHEMA_SUBMISSION, true);

        $this->translationCreator = new TranslationCreator();
        $this->submissionId = $this->createTestSubmission();
        $this->publicationId = $this->createTestPublication();
        $this->authorId = $this->createTestAuthor();
        $this->updateCurrentPublication();
    }

    public function tearDown(): void
    {
        foreach ($this->publicFiles as $fileName) {
            $this->createPublicFileManager()->removeContextFile($this->contextId, $fileName);
        }

        parent::tearDown();

        Services::get('schema')->get(SCHEMA_PUBLICATION, true);
        Services::get('schema')->get(SCHEMA_SUBMISSION, true);
    }

    protected function getAffectedTables()
    {
        return ['submissions', 'submission_settings', 'publications', 'publication_settings', 'authors', 'author_settings', 'citations', 'citation_settings'];
    }

    protected function getMockedRegistryKeys()
    {
        return ['hooks', 'plugins'];
    }

    private function enablePubIdPlugin($plugin): void
    {
        $plugins = & PluginRegistry::getPlugins();
        $plugins['pubIds'][get_class($plugin)] = $plugin;
    }

    private function createPublicFileManager()
    {
        import('classes.file.PublicFileManager');
        return new PublicFileManager();
    }

    private function publicFileExists(string $fileName): bool
    {
        $fileManager = $this->createPublicFileManager();
        return $fileManager->fileExists($fileManager->getContextFilesPath($this->contextId) . '/' . $fileName);
    }

    private function setPublicationData(array $data): void
    {
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($this->publicationId);
        foreach ($data as $field => $value) {
            $publication->setData($field, $value);
        }
        $publicationDao->updateObject($publication);
    }

    private function createTranslationPublication()
    {
        $translationId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);
        $translation = DAORegistry::getDAO('SubmissionDAO')->getById($translationId);

        return DAORegistry::getDAO('PublicationDAO')->getById($translation->getData('currentPublicationId'));
    }

    private function createTestAuthor()
    {
        $author = new Author();
        $author->setData('email', $this->authorEmail);
        $author->setData('givenName', $this->authorGivenName, $this->originalLocale);
        $author->setData('familyName', $this->authorFamilyName, $this->originalLocale);
        $author->setData('publicationId', $this->publicationId);
        $author->setData('submissionLocale', $this->originalLocale);

        return DAORegistry::getDAO('AuthorDAO')->insertObject($author);
    }

    private function createTestPublication()
    {
        $publication = new Publication();
        $publication->setData('status', STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('title', $this->originalTitle, $this->originalLocale);
        $publication->setData('title', $this->translationTitle, $this->translationLocale);
        $publication->setData('submissionId', $this->submissionId);
        $publication->setData('locale', $this->originalLocale);

        return DAORegistry::getDAO('PublicationDAO')->insertObject($publication);
    }

    private function createTestSubmission()
    {
        $submission = new Submission();
        $submission->setData('contextId', 1);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', $this->originalLocale);

        return DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
    }

    private function updateCurrentPublication()
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($this->submissionId);

        $submission->setData('currentPublicationId', $this->publicationId);
        $submissionDao->updateObject($submission);
    }

    public function testRollsBackWhenPublicationCreationFails(): void
    {
        $failingCreator = $this->getMockBuilder(TranslationCreator::class)
            ->onlyMethods(['createTranslationPublication'])
            ->getMock();
        $failingCreator->method('createTranslationPublication')
            ->willThrowException(new Exception('publication insert failed'));

        $translationsBefore = $this->countTranslationsOf($this->submissionId);

        try {
            $failingCreator->createTranslation($this->submissionId, $this->translationLocale);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('publication insert failed', $e->getMessage());
        }

        $this->assertSame(
            $translationsBefore,
            $this->countTranslationsOf($this->submissionId),
            'No translation submission should remain after failure'
        );
    }

    public function testRemovesCopiedCoverImagesWhenCreationFails(): void
    {
        $originalFile = 'submission_' . $this->submissionId . '_' . $this->publicationId . '_coverImage_en_US.png';
        $this->publicFiles[] = $originalFile;
        $this->createPublicFileManager()->writeContextFile($this->contextId, $originalFile, 'cover');
        $this->setPublicationData(['coverImage' => [
            $this->originalLocale => ['uploadName' => $originalFile, 'altText' => ''],
        ]]);
        $failingCreator = new class () extends TranslationCreator {
            protected function createTranslationPublication($publication, $newSubmission, $translationLocale, $originalLocale)
            {
                parent::createTranslationPublication($publication, $newSubmission, $translationLocale, $originalLocale);
                throw new Exception('failed after copying covers');
            }
        };
        $filesBefore = $this->listPublicFiles();

        try {
            $failingCreator->createTranslation($this->submissionId, $this->translationLocale);
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            $this->assertSame('failed after copying covers', $e->getMessage());
        }

        $leftovers = array_diff($this->listPublicFiles(), $filesBefore);
        $this->publicFiles = array_merge($this->publicFiles, $leftovers);
        $this->assertEmpty($leftovers);
        $this->assertTrue($this->publicFileExists($originalFile));
    }

    private function listPublicFiles(): array
    {
        return scandir($this->createPublicFileManager()->getContextFilesPath($this->contextId));
    }

    private function countTranslationsOf(int $originalSubmissionId): int
    {
        return Capsule::table('submission_settings')
            ->where('setting_name', 'isTranslationOf')
            ->where('setting_value', (string) $originalSubmissionId)
            ->count();
    }

    public function testTranslationHasIndependentPublicationIdentityAndContact(): void
    {
        import('plugins.pubIds.doi.DOIPubIdPlugin');
        $doiPlugin = new DOIPubIdPlugin();
        $this->enablePubIdPlugin($doiPlugin);
        HookRegistry::register('Schema::get::publication', [$doiPlugin, 'addToSchema']);
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);

        $this->authorId = $this->createTestAuthor();
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($this->publicationId);
        foreach ([
            'pub-id::doi' => '10.1234/dft-original',
            'doiSuffix' => 'dft-original',
            'urlPath' => 'original-path',
            'datePublished' => '2020-01-02',
            'issueId' => Capsule::table('issues')->value('issue_id'),
            'primaryContactId' => $this->authorId,
        ] as $field => $value) {
            $publication->setData($field, $value);
        }
        $publicationDao->updateObject($publication);
        $this->assertSame('10.1234/dft-original', $publicationDao->getById($this->publicationId)->getData('pub-id::doi'));
        $this->assertNotNull($publication->getData('issueId'));

        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($this->submissionId);
        $submission->setData('dateSubmitted', '2020-01-01 00:00:00');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
        $submissionDao->updateObject($submission);
        $id = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);
        $translation = $submissionDao->getById($id);
        $copy = $publicationDao->getById($translation->getData('currentPublicationId'));
        foreach (['pub-id::doi', 'doiSuffix', 'urlPath', 'datePublished', 'issueId'] as $field) {
            $this->assertNull($copy->getData($field), $field . ' must be independent of the original');
        }
        $this->assertNotEquals($submission->getData('dateSubmitted'), $translation->getData('dateSubmitted'));
        $this->assertSame(WORKFLOW_STAGE_ID_SUBMISSION, $translation->getData('stageId'));
        $contact = DAORegistry::getDAO('AuthorDAO')->getById($copy->getData('primaryContactId'));
        $this->assertNotNull($contact);
        $this->assertNotEquals($this->authorId, $contact->getId());
        $this->assertEquals($copy->getId(), $contact->getData('publicationId'));
        $this->assertSame('10.1234/dft-original', $publicationDao->getById($this->publicationId)->getData('pub-id::doi'));
    }

    public function testTranslationDoesNotInheritAnyPublicationIdentifier(): void
    {
        import('plugins.pubIds.urn.URNPubIdPlugin');
        $urnPlugin = new URNPubIdPlugin();
        $this->enablePubIdPlugin($urnPlugin);
        HookRegistry::register('Schema::get::publication', [$urnPlugin, 'addToSchema']);
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);

        $identifiers = [
            'pub-id::publisher-id' => 'dft-publisher-id',
            'pub-id::other::urn' => 'urn:nbn:de:dft-original',
            'urnSuffix' => 'dft-original',
        ];
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $publication = $publicationDao->getById($this->publicationId);
        foreach ($identifiers as $field => $value) {
            $publication->setData($field, $value);
        }
        $publicationDao->updateObject($publication);

        $translationId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);

        $translation = DAORegistry::getDAO('SubmissionDAO')->getById($translationId);
        $copy = $publicationDao->getById($translation->getData('currentPublicationId'));
        $original = $publicationDao->getById($this->publicationId);
        foreach ($identifiers as $field => $value) {
            $this->assertSame($value, $original->getData($field), $field . ' must stay on the original');
            $this->assertNull($copy->getData($field), $field . ' must be independent of the original');
        }
    }

    public function testTranslationDoesNotInheritSuffixOfThirdPartyPubIdPlugin(): void
    {
        import('plugins.pubIds.urn.URNPubIdPlugin');
        $arkPlugin = $this->createMock(URNPubIdPlugin::class);
        $arkPlugin->method('getSuffixFieldName')->willReturn('arkSuffix');
        $this->enablePubIdPlugin($arkPlugin);
        HookRegistry::register('Schema::get::publication', function ($hookName, $args) {
            $args[0]->properties->{'pub-id::ark'} = (object) ['type' => 'string', 'validation' => ['nullable']];
            $args[0]->properties->arkSuffix = (object) ['type' => 'string', 'validation' => ['nullable']];
            return false;
        });
        Services::get('schema')->get(SCHEMA_PUBLICATION, true);
        $this->setPublicationData(['pub-id::ark' => 'ark:/12345/dft-original', 'arkSuffix' => 'dft-original']);

        $copy = $this->createTranslationPublication();

        $this->assertNull($copy->getData('pub-id::ark'));
        $this->assertNull($copy->getData('arkSuffix'));
    }

    public function testTranslationHasItsOwnCitations(): void
    {
        $citationsRaw = "First reference.\nSecond reference.";
        $this->setPublicationData(['citationsRaw' => $citationsRaw]);

        $copy = $this->createTranslationPublication();

        $this->assertSame($citationsRaw, $copy->getData('citationsRaw'));
        $citations = DAORegistry::getDAO('CitationDAO')->getByPublicationId($copy->getId())->toArray();
        $this->assertCount(2, $citations);
    }

    public function testTranslationHasItsOwnCoverImageFile(): void
    {
        $originalFile = 'submission_' . $this->submissionId . '_' . $this->publicationId . '_coverImage_en_US.png';
        $this->publicFiles[] = $originalFile;
        $this->createPublicFileManager()->writeContextFile($this->contextId, $originalFile, 'cover');
        $this->setPublicationData(['coverImage' => [
            $this->originalLocale => ['uploadName' => $originalFile, 'altText' => 'A cat', 'dateUploaded' => '2020-01-02 00:00:00'],
        ]]);

        $copy = $this->createTranslationPublication();

        $coverImage = $copy->getData('coverImage', $this->originalLocale);
        $this->publicFiles[] = $coverImage['uploadName'];
        $this->assertNotEquals($originalFile, $coverImage['uploadName']);
        $this->assertTrue($this->publicFileExists($coverImage['uploadName']));
        $this->assertSame('A cat', $coverImage['altText']);

        $this->createPublicFileManager()->removeContextFile($this->contextId, $coverImage['uploadName']);
        $this->assertTrue($this->publicFileExists($originalFile));
    }

    public function testTranslationIsCreatedWithoutCoverImageWhenFileIsMissing(): void
    {
        $this->setPublicationData(['coverImage' => [
            $this->originalLocale => ['uploadName' => 'dft_missing_cover.png', 'altText' => '', 'dateUploaded' => '2020-01-02 00:00:00'],
        ]]);

        $copy = $this->createTranslationPublication();

        $this->assertEmpty($copy->getData('coverImage', $this->originalLocale));
    }

    public function testDoesNotCopyCoverImageNamedWithPathOutsideJournalFolder(): void
    {
        $secretFile = tempnam(sys_get_temp_dir(), 'dft_secret_');
        file_put_contents($secretFile, 'secret');
        $this->setPublicationData(['coverImage' => [
            $this->originalLocale => ['uploadName' => str_repeat('../', 30) . ltrim($secretFile, '/'), 'altText' => ''],
        ]]);

        try {
            $copy = $this->createTranslationPublication();
        } finally {
            unlink($secretFile);
        }
        $this->publicFiles[] = $this->expectedCoverCopyName($copy, '');

        $this->assertEmpty($copy->getData('coverImage', $this->originalLocale));
        $this->assertFalse($this->publicFileExists($this->expectedCoverCopyName($copy, '')));
    }

    public function testDoesNotCopyCoverImageWithoutImageExtension(): void
    {
        $originalFile = 'dft_cover_' . $this->publicationId . '.php';
        $this->publicFiles[] = $originalFile;
        $this->createPublicFileManager()->writeContextFile($this->contextId, $originalFile, '<?php');
        $this->setPublicationData(['coverImage' => [
            $this->originalLocale => ['uploadName' => $originalFile, 'altText' => ''],
        ]]);

        $copy = $this->createTranslationPublication();
        $this->publicFiles[] = $this->expectedCoverCopyName($copy, 'php');

        $this->assertEmpty($copy->getData('coverImage', $this->originalLocale));
        $this->assertFalse($this->publicFileExists($this->expectedCoverCopyName($copy, 'php')));
    }

    private function expectedCoverCopyName($copy, string $extension): string
    {
        return 'submission_' . $copy->getData('submissionId') . '_' . $copy->getId() . '_coverImage_' . $this->originalLocale . '.' . $extension;
    }

    public function testCreatesTranslationSubmission(): void
    {
        $translationSubmissionId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);

        $translationSubmission = DAORegistry::getDAO('SubmissionDAO')->getById($translationSubmissionId);
        $this->assertNotEquals($this->submissionId, $translationSubmissionId);
        $this->assertEquals($this->translationLocale, $translationSubmission->getData('locale'));
        $this->assertEquals($this->submissionId, $translationSubmission->getData('isTranslationOf'));

        $translationPublication = $translationSubmission->getData('publications')[0];
        $this->assertNotEquals($this->publicationId, $translationPublication->getId());
        $this->assertEquals($this->translationLocale, $translationPublication->getData('locale'));
        $this->assertEquals($this->originalTitle, $translationPublication->getData('title', $this->originalLocale));
        $this->assertEquals($this->translationTitle, $translationPublication->getData('title', $this->translationLocale));
        $this->assertEquals($translationPublication->getId(), $translationSubmission->getData('currentPublicationId'));

        $translationAuthor = $translationPublication->getData('authors')[0];
        $this->assertNotEquals($this->authorId, $translationAuthor->getId());
        $this->assertEquals($this->authorEmail, $translationAuthor->getData('email'));
        $this->assertEquals($this->authorGivenName, $translationAuthor->getData('givenName', $this->originalLocale));
        $this->assertEquals($this->authorFamilyName, $translationAuthor->getData('familyName', $this->originalLocale));
        $this->assertEquals($this->authorGivenName, $translationAuthor->getData('givenName', $this->translationLocale));
        $this->assertEquals($this->authorFamilyName, $translationAuthor->getData('familyName', $this->translationLocale));
        $this->assertEquals($this->translationLocale, $translationAuthor->getData('submissionLocale'));
    }
}
