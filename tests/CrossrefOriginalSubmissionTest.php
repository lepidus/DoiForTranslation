<?php

use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;

class CrossrefOriginalSubmissionTest extends TestCase
{
    private $plugin;
    private $localContextId;
    private $foreignContextId;

    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->plugin = new DoiForTranslationPlugin();
        $this->localContextId = $this->createContext('dftCrossLocal');
        $this->foreignContextId = $this->createContext('dftCrossForeign');
    }

    public function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function testReturnsPublishedOriginalWithDoiFromSameContext(): void
    {
        $originalId = $this->createSubmission($this->localContextId, Submission::STATUS_PUBLISHED, true);

        $original = $this->plugin->getOriginalSubmissionForCrossref($originalId, $this->localContextId);

        $this->assertSame($originalId, $original->getId());
    }

    public function testIgnoresMissingOriginal(): void
    {
        $missingId = $this->createSubmission($this->localContextId, Submission::STATUS_PUBLISHED, true) + 1000;

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($missingId, $this->localContextId));
    }

    public function testIgnoresOriginalFromAnotherContext(): void
    {
        $foreignId = $this->createSubmission($this->foreignContextId, Submission::STATUS_PUBLISHED, true);

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($foreignId, $this->localContextId));
    }

    public function testIgnoresUnpublishedOriginal(): void
    {
        $queuedId = $this->createSubmission($this->localContextId, Submission::STATUS_QUEUED, true);

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($queuedId, $this->localContextId));
    }

    public function testIgnoresOriginalWithoutDoi(): void
    {
        $withoutDoiId = $this->createSubmission($this->localContextId, Submission::STATUS_PUBLISHED, false);

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($withoutDoiId, $this->localContextId));
    }

    private function createSubmission(int $contextId, int $status, bool $withDoi): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', $status);
        $submission->setData('locale', 'en');
        $submissionId = Repo::submission()->dao->insert($submission);

        $publication = new Publication();
        $publication->setData('status', $status);
        $publication->setData('version', 1);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', 'en');
        $publication->setData('title', 'Original title', 'en');
        if ($withDoi) {
            $publication->setData('doiId', $this->createDoi($contextId));
        }
        $publicationId = Repo::publication()->dao->insert($publication);

        $submission = Repo::submission()->get($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        Repo::submission()->dao->update($submission);

        return $submissionId;
    }

    private function createDoi(int $contextId): int
    {
        $doi = Repo::doi()->newDataObject([
            'doi' => '10.1234/dft.' . uniqid(),
            'contextId' => $contextId,
        ]);

        return Repo::doi()->add($doi);
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
