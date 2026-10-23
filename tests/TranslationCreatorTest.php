<?php

use APP\author\Author;
use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\classes\TranslationCreator;
use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\plugins\Hook;

class TranslationCreatorTest extends TestCase
{
    private $translationCreator;
    private $submissionId;
    private $publicationId;
    private $authorId;
    private $originalLocale = 'en';
    private $originalTitle = 'Cat species of Egypt';
    private $translationLocale = 'fr_CA';
    private $translationTitle = "Espèces de chats d'Egypte";
    private $authorEmail = 'egyptian.cat@mailinator.com';
    private $authorGivenName = 'Cat';
    private $authorFamilyName = 'Ramesses';
    private $contextId;

    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $plugin = new DoiForTranslationPlugin();
        Hook::add('Schema::get::submission', $plugin->addOurFieldsToSubmissionSchema(...));

        $this->contextId = $this->createContext();
        $this->translationCreator = new TranslationCreator();
        $this->submissionId = $this->createTestSubmission();
        $this->publicationId = $this->createTestPublication();
        $this->authorId = $this->createTestAuthor();
        $this->updateCurrentPublication();
    }

    public function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function createTestAuthor()
    {
        $author = new Author();
        $author->setData('email', $this->authorEmail);
        $author->setData('givenName', $this->authorGivenName, $this->originalLocale);
        $author->setData('familyName', $this->authorFamilyName, $this->originalLocale);
        $author->setData('publicationId', $this->publicationId);
        $author->setData('submissionLocale', $this->originalLocale);

        return Repo::author()->dao->insert($author);
    }

    private function createTestPublication()
    {
        $publication = new Publication();
        $publication->setData('status', Submission::STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('title', $this->originalTitle, $this->originalLocale);
        $publication->setData('title', $this->translationTitle, $this->translationLocale);
        $publication->setData('submissionId', $this->submissionId);
        $publication->setData('locale', $this->originalLocale);

        return Repo::publication()->dao->insert($publication);
    }

    private function createTestSubmission()
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $submission->setData('status', Submission::STATUS_QUEUED);
        $submission->setData('locale', $this->originalLocale);
        $submission->setData('stageId', WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);

        return Repo::submission()->dao->insert($submission);
    }

    private function createContext(): int
    {
        return DB::table('journals')->insertGetId([
            'path' => 'dftCreator' . uniqid(),
            'seq' => 0,
            'primary_locale' => 'en',
            'enabled' => 1,
        ]);
    }

    private function updateCurrentPublication()
    {
        $submissionDao = Repo::submission()->dao;
        $submission = Repo::submission()->get($this->submissionId);

        $submission->setData('currentPublicationId', $this->publicationId);
        $submissionDao->update($submission);
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

    private function countTranslationsOf(int $originalSubmissionId): int
    {
        return DB::table('submission_settings')
            ->where('setting_name', 'isTranslationOf')
            ->where('setting_value', (string) $originalSubmissionId)
            ->count();
    }

    public function testTranslationHasIndependentPublicationIdentityAndContact(): void
    {
        $doiId = DB::table('dois')->insertGetId(['doi' => '10.1234/original', 'context_id' => $this->contextId]);
        $issueId = DB::table('issues')->insertGetId(['journal_id' => $this->contextId]);
        $this->authorId = $this->createTestAuthor();
        $publication = Repo::publication()->get($this->publicationId);
        foreach (['issueId' => $issueId, 'doiId' => $doiId, 'urlPath' => 'original-path', 'datePublished' => '2020-01-02', 'primaryContactId' => $this->authorId] as $key => $value) {
            $publication->setData($key, $value);
        }
        Repo::publication()->dao->update($publication);
        $submission = Repo::submission()->get($this->submissionId);
        $submission->setData('dateSubmitted', '2020-01-01 00:00:00');
        Repo::submission()->dao->update($submission);

        $id = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);
        $translation = Repo::submission()->get($id);
        $copy = $translation->getCurrentPublication();
        $this->assertNull($copy->getData('doiId'));
        $this->assertNull($copy->getData('urlPath'));
        $this->assertNull($copy->getData('datePublished'));
        $this->assertNull($copy->getData('issueId'));
        $this->assertNotEquals($submission->getData('dateSubmitted'), $translation->getData('dateSubmitted'));
        $contact = Repo::author()->get($copy->getData('primaryContactId'));
        $this->assertNotNull($contact);
        $this->assertNotEquals($this->authorId, $contact->getId());
        $this->assertEquals($copy->getId(), $contact->getData('publicationId'));
        $this->assertEquals($doiId, Repo::publication()->get($this->publicationId)->getData('doiId'));
    }

    public function testTranslationRetainsEditorialAssignmentsAndRecordsItsCreation(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'dftEditor' . uniqid(), 'email' => uniqid() . '@example.org',
            'password' => 'unused', 'date_registered' => '2020-01-01',
        ]);
        $groupId = DB::table('user_groups')->insertGetId([
            'context_id' => $this->contextId, 'role_id' => \PKP\security\Role::ROLE_ID_SUB_EDITOR,
        ]);
        Repo::stageAssignment()->build($this->submissionId, $groupId, $userId, true, false);
        $translationId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);
        $assignment = \PKP\stageAssignment\StageAssignment::withSubmissionIds([$translationId])->first();
        $this->assertNotNull($assignment);
        $this->assertEquals($userId, $assignment->userId);
        $this->assertEquals($groupId, $assignment->userGroupId);
        $this->assertTrue((bool) $assignment->recommendOnly);
        $this->assertFalse((bool) $assignment->canChangeMetadata);
        $this->assertSame(1, DB::table('event_log')->where('assoc_id', $translationId)
            ->where('message', 'plugins.generic.doiForTranslation.event.created')->count());
    }

    public function testKeepsTheCopiedCurrentPublicationWhenOriginalHasOtherVersions(): void
    {
        $otherPublicationId = $this->createTestPublication();
        $other = Repo::publication()->get($otherPublicationId);
        $other->setData('version', 2);
        $other->setData('title', 'Another version', $this->originalLocale);
        Repo::publication()->dao->update($other);

        $id = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);
        $translation = Repo::submission()->get($id);
        $this->assertSame($this->originalTitle, $translation->getCurrentPublication()->getData('title', $this->originalLocale));
    }

    public function testCreatesTranslationSubmission(): void
    {
        $translationSubmissionId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);

        $translationSubmission = Repo::submission()->get($translationSubmissionId);
        $this->assertNotEquals($this->submissionId, $translationSubmissionId);
        $this->assertEquals($this->translationLocale, $translationSubmission->getData('locale'));
        $this->assertSame(WORKFLOW_STAGE_ID_SUBMISSION, $translationSubmission->getData('stageId'));
        $this->assertSame($this->submissionId, (int) DB::table('submission_settings')
            ->where('submission_id', $translationSubmissionId)
            ->where('setting_name', 'isTranslationOf')
            ->value('setting_value'));

        $translationPublication = Repo::publication()->get($translationSubmission->getData('currentPublicationId'));
        $this->assertNotEquals($this->publicationId, $translationPublication->getId());
        $this->assertEquals($this->translationLocale, $translationPublication->getData('locale'));
        $this->assertEquals($this->originalTitle, $translationPublication->getData('title', $this->originalLocale));
        $this->assertEquals($this->translationTitle, $translationPublication->getData('title', $this->translationLocale));
        $this->assertEquals($translationPublication->getId(), $translationSubmission->getData('currentPublicationId'));

        $translationAuthor = Repo::author()->getCollector()
            ->filterByPublicationIds([$translationPublication->getId()])
            ->getMany()
            ->first();
        $this->assertNotEquals($this->authorId, $translationAuthor->getId());
        $this->assertEquals($this->authorEmail, $translationAuthor->getData('email'));
        $this->assertEquals($this->authorGivenName, $translationAuthor->getData('givenName', $this->originalLocale));
        $this->assertEquals($this->authorFamilyName, $translationAuthor->getData('familyName', $this->originalLocale));
        $this->assertEquals($this->authorGivenName, $translationAuthor->getData('givenName', $this->translationLocale));
        $this->assertEquals($this->authorFamilyName, $translationAuthor->getData('familyName', $this->translationLocale));
        $this->assertEquals($this->translationLocale, $translationAuthor->getData('submissionLocale'));
    }
}
