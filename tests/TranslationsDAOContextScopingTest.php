<?php

import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.classes.TranslationsDAO');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class TranslationsDAOContextScopingTest extends TestCase
{
    private $dao;
    private $localContextId = 1;
    private $foreignContextId = 999;

    public function setUp(): void
    {
        Capsule::connection()->beginTransaction();
        $plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', [$plugin, 'addOurFieldsToSubmissionSchema']);
        $this->dao = new TranslationsDAO();
    }

    public function tearDown(): void
    {
        Capsule::connection()->rollBack();
    }

    public function testGetTranslationsIgnoresSubmissionsFromOtherContext(): void
    {
        $originalId = $this->createSubmission($this->localContextId);
        $legitimateTranslationId = $this->createSubmission($this->localContextId, $originalId);
        $rogueCrossContextId = $this->createSubmission($this->foreignContextId, $originalId);

        $translations = $this->dao->getTranslations($originalId, $this->localContextId);

        $ids = array_column($translations, 'id');
        $this->assertContains($legitimateTranslationId, $ids);
        $this->assertNotContains($rogueCrossContextId, $ids);
    }

    public function testGetTranslationsBySubmissionIdsReturnsTranslationsGroupedByOriginal(): void
    {
        $firstOriginalId = $this->createSubmission($this->localContextId);
        $secondOriginalId = $this->createSubmission($this->localContextId);
        $firstTranslationId = $this->createSubmission($this->localContextId, $firstOriginalId, 'pt_BR', STATUS_PUBLISHED);
        $this->createSubmission($this->localContextId, $firstOriginalId, 'es_ES');
        $queuedSecondTranslationId = $this->createSubmission($this->localContextId, $secondOriginalId, 'fr_CA');
        $this->createSubmission($this->foreignContextId, $firstOriginalId, 'de_DE');

        $groupedTranslations = $this->dao->getTranslationsBySubmissionIds(
            [$firstOriginalId, $secondOriginalId],
            $this->localContextId
        );

        $this->assertCount(2, $groupedTranslations[$firstOriginalId]);
        $this->assertSame([$queuedSecondTranslationId], array_column($groupedTranslations[$secondOriginalId], 'id'));

        $publishedOnly = $this->dao->getTranslationsBySubmissionIds(
            [$firstOriginalId, $secondOriginalId],
            $this->localContextId,
            true
        );

        $this->assertSame([], $publishedOnly[$secondOriginalId]);
        $this->assertSame([$firstTranslationId], array_column($publishedOnly[$firstOriginalId], 'id'));
    }

    public function testGetTitlesBySubmissionIdsUsesRequestedLocales(): void
    {
        $firstSubmissionId = $this->createSubmission($this->localContextId, null, 'en_US');
        $secondSubmissionId = $this->createSubmission($this->localContextId, null, 'es_ES');

        $firstPublicationId = $this->createPublication($firstSubmissionId, 'en_US', [
            ['settingName' => 'prefix', 'locale' => 'pt_BR', 'value' => 'Dossiê'],
            ['settingName' => 'title', 'locale' => 'pt_BR', 'value' => 'Gatos do Nilo'],
            ['settingName' => 'subtitle', 'locale' => 'pt_BR', 'value' => 'Uma revisão'],
        ]);
        $secondPublicationId = $this->createPublication($secondSubmissionId, 'es_ES', [
            ['settingName' => 'title', 'locale' => 'es_ES', 'value' => 'Felinos del desierto'],
        ]);

        $this->updateCurrentPublication($firstSubmissionId, $firstPublicationId);
        $this->updateCurrentPublication($secondSubmissionId, $secondPublicationId);

        $titles = $this->dao->getTitlesBySubmissionIds(
            [$firstSubmissionId, $secondSubmissionId],
            [
                $firstSubmissionId => 'pt_BR',
                $secondSubmissionId => 'es_ES',
            ]
        );

        $this->assertSame('Dossiê Gatos do Nilo: Uma revisão', $titles[$firstSubmissionId]);
        $this->assertSame('Felinos del desierto', $titles[$secondSubmissionId]);
    }

    private function createSubmission(int $contextId, int $isTranslationOf = null, string $locale = 'en_US', int $status = STATUS_QUEUED): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', $status);
        $submission->setData('locale', $locale);
        if (!is_null($isTranslationOf)) {
            $submission->setData('isTranslationOf', $isTranslationOf);
        }
        return DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
    }

    private function createPublication(int $submissionId, string $locale, array $settings): int
    {
        $publication = new Publication();
        $publication->setData('status', STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', $locale);

        foreach ($settings as $setting) {
            $publication->setData($setting['settingName'], $setting['value'], $setting['locale']);
        }

        return DAORegistry::getDAO('PublicationDAO')->insertObject($publication);
    }

    private function updateCurrentPublication(int $submissionId, int $publicationId): void
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->updateObject($submission);
    }
}
