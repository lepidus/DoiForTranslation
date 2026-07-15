<?php

use APP\plugins\generic\doiForTranslation\classes\TranslationLocaleValidator;
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
            'en',
            false,
            ['en', 'pt_BR'],
            []
        ));
    }

    public function testRejectsLocaleEqualToOriginal(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'en',
            'en',
            false,
            ['en', 'pt_BR'],
            []
        ));
    }

    public function testRejectsWhenSubmissionIsItselfATranslation(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'pt_BR',
            'en',
            true,
            ['en', 'pt_BR'],
            []
        ));
    }

    public function testRejectsLocaleNotSupportedByContext(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'kl_GL',
            'en',
            false,
            ['en', 'pt_BR', 'es'],
            []
        ));
    }

    public function testRejectsLocaleAlreadyUsedByAnotherTranslation(): void
    {
        $this->assertFalse($this->validator->isAvailable(
            'pt_BR',
            'en',
            false,
            ['en', 'pt_BR', 'es'],
            ['pt_BR']
        ));
    }

    public function testAcceptsSupportedLocaleNotUsedYet(): void
    {
        $this->assertTrue($this->validator->isAvailable(
            'es',
            'en',
            false,
            ['en', 'pt_BR', 'es'],
            ['pt_BR']
        ));
    }
}
