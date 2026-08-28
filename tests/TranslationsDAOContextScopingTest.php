<?php

use APP\facades\Repo;
use APP\plugins\generic\doiForTranslation\classes\TranslationsDAO;
use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\plugins\Hook;

class TranslationsDAOContextScopingTest extends TestCase
{
    private $dao;
    private $localContextId = 1;
    private $foreignContextId = 999;

    public function setUp(): void
    {
        DB::beginTransaction();
        $this->localContextId = $this->createContext('dftLocal');
        $this->foreignContextId = $this->createContext('dftForeign');
        $plugin = new DoiForTranslationPlugin();
        Hook::add('Schema::get::submission', $plugin->addOurFieldsToSubmissionSchema(...));
        $this->dao = new TranslationsDAO();
    }

    public function tearDown(): void
    {
        DB::rollBack();
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
        $firstTranslationId = $this->createSubmission($this->localContextId, $firstOriginalId, 'pt_BR', Submission::STATUS_PUBLISHED);
        $this->createSubmission($this->localContextId, $firstOriginalId, 'es');
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
        $firstSubmissionId = $this->createSubmission($this->localContextId, null, 'en');
        $secondSubmissionId = $this->createSubmission($this->localContextId, null, 'es');

        $firstPublicationId = $this->createPublication($firstSubmissionId, 'en', [
            ['settingName' => 'prefix', 'locale' => 'pt_BR', 'value' => 'Dossiê'],
            ['settingName' => 'title', 'locale' => 'pt_BR', 'value' => 'Gatos do Nilo'],
            ['settingName' => 'subtitle', 'locale' => 'pt_BR', 'value' => 'Uma revisão'],
        ]);
        $secondPublicationId = $this->createPublication($secondSubmissionId, 'es', [
            ['settingName' => 'title', 'locale' => 'es', 'value' => 'Felinos del desierto'],
        ]);

        $this->updateCurrentPublication($firstSubmissionId, $firstPublicationId);
        $this->updateCurrentPublication($secondSubmissionId, $secondPublicationId);

        $titles = $this->dao->getTitlesBySubmissionIds(
            [$firstSubmissionId, $secondSubmissionId],
            [
                $firstSubmissionId => 'pt_BR',
                $secondSubmissionId => 'es',
            ]
        );

        $this->assertSame('Dossiê Gatos do Nilo: Uma revisão', $titles[$firstSubmissionId]);
        $this->assertSame('Felinos del desierto', $titles[$secondSubmissionId]);
    }

    private function createSubmission(int $contextId, int $isTranslationOf = null, string $locale = 'en', int $status = Submission::STATUS_QUEUED): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', $status);
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
        return $submissionId;
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

    private function createPublication(int $submissionId, string $locale, array $settings): int
    {
        $publication = new Publication();
        $publication->setData('status', Submission::STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('submissionId', $submissionId);
        $publication->setData('locale', $locale);

        foreach ($settings as $setting) {
            $publication->setData($setting['settingName'], $setting['value'], $setting['locale']);
        }

        return Repo::publication()->dao->insert($publication);
    }

    private function updateCurrentPublication(int $submissionId, int $publicationId): void
    {
        $submissionDao = Repo::submission()->dao;
        $submission = Repo::submission()->get($submissionId);
        $submission->setData('currentPublicationId', $publicationId);
        $submissionDao->update($submission);
    }
}
