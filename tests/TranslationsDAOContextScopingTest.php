<?php

import('lib.pkp.tests.DatabaseTestCase');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.classes.TranslationsDAO');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

class TranslationsDAOContextScopingTest extends DatabaseTestCase
{
    private $dao;
    private $localContextId = 1;
    private $foreignContextId = 999;

    protected function getAffectedTables()
    {
        return ['submissions', 'submission_settings'];
    }

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', [$plugin, 'addOurFieldsToSubmissionSchema']);
        $this->dao = new TranslationsDAO();
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

    private function createSubmission(int $contextId, int $isTranslationOf = null): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', 'en_US');
        if (!is_null($isTranslationOf)) {
            $submission->setData('isTranslationOf', $isTranslationOf);
        }
        return DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
    }
}
