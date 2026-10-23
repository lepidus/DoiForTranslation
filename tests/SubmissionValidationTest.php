<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

import('classes.submission.Submission');
import('lib.pkp.classes.services.interfaces.EntityWriteInterface');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

class SubmissionValidationTest extends TestCase
{
    public function setUp(): void
    {
        Capsule::connection()->beginTransaction();
    }

    public function tearDown(): void
    {
        Capsule::connection()->rollBack();
    }

    public function testAcceptsUnchangedIsTranslationOfWhenEditingThroughTheApi(): void
    {
        $submissionId = $this->createTranslationOf(20);
        $errors = [];

        (new DoiForTranslationPlugin())->rejectIsTranslationOfFromApi(
            'Submission::validate',
            [&$errors, VALIDATE_ACTION_EDIT, ['id' => $submissionId, 'isTranslationOf' => 20], ['en_US'], 'en_US']
        );

        $this->assertSame([], $errors);
    }

    public function testRejectsChangedIsTranslationOfWhenEditingThroughTheApi(): void
    {
        $submissionId = $this->createTranslationOf(20);
        $errors = [];

        (new DoiForTranslationPlugin())->rejectIsTranslationOfFromApi(
            'Submission::validate',
            [&$errors, VALIDATE_ACTION_EDIT, ['id' => $submissionId, 'isTranslationOf' => 21], ['en_US'], 'en_US']
        );

        $this->assertArrayHasKey('isTranslationOf', $errors);
    }

    public function testRejectsIsTranslationOfWhenEditingThroughTheApi(): void
    {
        $errors = [];

        (new DoiForTranslationPlugin())->rejectIsTranslationOfFromApi(
            'Submission::validate',
            [&$errors, VALIDATE_ACTION_EDIT, ['isTranslationOf' => 20], ['en_US'], 'en_US']
        );

        $this->assertArrayHasKey('isTranslationOf', $errors);
    }

    public function testRejectsIsTranslationOfWhenAddingThroughTheApi(): void
    {
        $errors = [];

        (new DoiForTranslationPlugin())->rejectIsTranslationOfFromApi(
            'Submission::validate',
            [&$errors, VALIDATE_ACTION_ADD, ['isTranslationOf' => 20], ['en_US'], 'en_US']
        );

        $this->assertArrayHasKey('isTranslationOf', $errors);
    }

    public function testAcceptsEditsWithoutIsTranslationOf(): void
    {
        $errors = [];

        (new DoiForTranslationPlugin())->rejectIsTranslationOfFromApi(
            'Submission::validate',
            [&$errors, VALIDATE_ACTION_EDIT, ['locale' => 'en_US'], ['en_US'], 'en_US']
        );

        $this->assertSame([], $errors);
    }

    private function createTranslationOf(int $originalSubmissionId): int
    {
        $submission = new Submission();
        $submission->setData('contextId', 1);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', 'en_US');
        $submissionId = DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
        Capsule::table('submission_settings')->insert([
            'submission_id' => $submissionId,
            'locale' => '',
            'setting_name' => 'isTranslationOf',
            'setting_value' => (string) $originalSubmissionId,
        ]);

        return $submissionId;
    }
}
