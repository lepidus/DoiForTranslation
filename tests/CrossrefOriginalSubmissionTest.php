<?php

import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class CrossrefOriginalSubmissionTest extends TestCase
{
    private $plugin;
    private $localContextId = 1;
    private $foreignContextId = 999;

    public function setUp(): void
    {
        Capsule::connection()->beginTransaction();
        $this->plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', [$this->plugin, 'addOurFieldsToSubmissionSchema']);
    }

    public function tearDown(): void
    {
        Capsule::connection()->rollBack();
    }

    public function testReturnsPublishedOriginalWithDoiFromSameContext(): void
    {
        $originalId = $this->createSubmission($this->localContextId, STATUS_PUBLISHED, '10.9999/dft.original');

        $original = $this->plugin->getOriginalSubmissionForCrossref($originalId, $this->localContextId);

        $this->assertSame($originalId, (int) $original->getId());
    }

    public function testIgnoresMissingOriginal(): void
    {
        $missingId = $this->createSubmission($this->localContextId, STATUS_PUBLISHED, '10.9999/dft.missing') + 1000;

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($missingId, $this->localContextId));
    }

    public function testIgnoresOriginalFromAnotherContext(): void
    {
        $foreignId = $this->createSubmission($this->foreignContextId, STATUS_PUBLISHED, '10.9999/dft.foreign');

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($foreignId, $this->localContextId));
    }

    public function testIgnoresUnpublishedOriginal(): void
    {
        $queuedId = $this->createSubmission($this->localContextId, STATUS_QUEUED, '10.9999/dft.queued');

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($queuedId, $this->localContextId));
    }

    public function testIgnoresOriginalWithoutDoi(): void
    {
        $withoutDoiId = $this->createSubmission($this->localContextId, STATUS_PUBLISHED, null);

        $this->assertNull($this->plugin->getOriginalSubmissionForCrossref($withoutDoiId, $this->localContextId));
    }

    private function createSubmission(int $contextId, int $status, ?string $doi): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', $status);
        $submission->setData('locale', 'en_US');
        $submissionId = $submissionDao->insertObject($submission);

        $publication = new Publication();
        $publication->setData('status', $status);
        $publication->setData('version', 1);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', 'en_US');
        $publication->setData('title', 'Original title', 'en_US');
        $publicationId = DAORegistry::getDAO('PublicationDAO')->insertObject($publication);

        if (!is_null($doi)) {
            Capsule::table('publication_settings')->insert([
                'publication_id' => $publicationId,
                'locale' => '',
                'setting_name' => 'pub-id::doi',
                'setting_value' => $doi,
            ]);
        }

        $submission = $submissionDao->getById($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->updateObject($submission);

        return $submissionId;
    }
}
