<?php

import('plugins.generic.doiForTranslation.classes.TranslationLocaleValidator');

use PHPUnit\Framework\TestCase;

class TranslationLocaleValidatorTest extends TestCase
{
    private $validator;

    public function setUp(): void
    {
        $this->validator = new TranslationLocaleValidator();
    }

    public function testRejectsEmptyLocale(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            '',
            'en_US',
            false,
            ['en_US', 'pt_BR'],
            []
        ));
    }

    public function testRejectsLocaleEqualToOriginal(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'en_US',
            'en_US',
            false,
            ['en_US', 'pt_BR'],
            []
        ));
    }

    public function testRejectsWhenSubmissionIsItselfATranslation(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'pt_BR',
            'en_US',
            true,
            ['en_US', 'pt_BR'],
            []
        ));
    }

    public function testRejectsLocaleNotSupportedByContext(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'kl_GL',
            'en_US',
            false,
            ['en_US', 'pt_BR', 'es_ES'],
            []
        ));
    }

    public function testRejectsLocaleAlreadyUsedByAnotherTranslation(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'pt_BR',
            'en_US',
            false,
            ['en_US', 'pt_BR', 'es_ES'],
            ['pt_BR']
        ));
    }

    public function testAcceptsSupportedLocaleNotUsedYet(): void
    {
        $this->assertTrue($this->validator->isAvailable(
            'es_ES',
            'en_US',
            false,
            ['en_US', 'pt_BR', 'es_ES'],
            ['pt_BR']
        ));
    }
}
