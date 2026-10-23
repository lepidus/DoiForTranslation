<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

class SubmissionValidationTest extends TestCase
{
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
}
