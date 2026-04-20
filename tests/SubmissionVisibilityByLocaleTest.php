<?php

use PHPUnit\Framework\TestCase;

import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

class SubmissionVisibilityByLocaleTest extends TestCase
{
    public function testKeepsOriginalWhenNoTranslationMatchesLocalePrecedence(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $original = $this->mockSubmission(10, 'en_US');
        $translation = $this->mockSubmission(11, 'fr_CA', 10);

        $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
            [[$original, $translation]],
            ['pt_BR', 'es_ES']
        );

        $this->assertSame([10], $visibleSubmissionIds);
    }

    public function testFallbackPrefersOriginalEvenWhenTranslationComesFirst(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $translation = $this->mockSubmission(11, 'fr_CA', 10);
        $original = $this->mockSubmission(10, 'en_US');

        $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
            [[$translation, $original]],
            ['pt_BR', 'es_ES']
        );

        $this->assertSame([10], $visibleSubmissionIds);
    }

    public function testPrefersTranslationThatMatchesLocalePrecedence(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $original = $this->mockSubmission(10, 'en_US');
        $translation = $this->mockSubmission(11, 'fr_CA', 10);

        $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
            [[$original, $translation]],
            ['pt_BR', 'fr_CA', 'en_US']
        );

        $this->assertSame([11], $visibleSubmissionIds);
    }

    public function testReturnsOneVisibleSubmissionPerTranslationGroup(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $firstOriginal = $this->mockSubmission(10, 'en_US');
        $firstTranslation = $this->mockSubmission(11, 'fr_CA', 10);
        $secondOriginal = $this->mockSubmission(20, 'es_ES');

        $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
            [[$firstOriginal, $firstTranslation], [$secondOriginal]],
            ['fr_CA', 'en_US']
        );

        $this->assertSame([11, 20], $visibleSubmissionIds);
    }

    public function testReturnsOnlyOneSubmissionWhenGroupHasDuplicateLocales(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $original = $this->mockSubmission(10, 'en_US');
        $firstTranslation = $this->mockSubmission(11, 'pt_BR', 10);
        $duplicateTranslation = $this->mockSubmission(12, 'pt_BR', 10);

        $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
            [[$original, $firstTranslation, $duplicateTranslation]],
            ['pt_BR', 'en_US']
        );

        $this->assertCount(1, $visibleSubmissionIds);
        $this->assertContains($visibleSubmissionIds[0], [11, 12]);
    }

    public function testKeepsStandaloneOriginalRegardlessOfLocalePrecedence(): void
    {
        $plugin = new DoiForTranslationPlugin();

        $original = $this->mockSubmission(10, 'fr_CA');

        foreach ([['en_US'], ['pt_BR', 'es_ES'], ['fr_CA']] as $precedence) {
            $visibleSubmissionIds = $plugin->getVisibleSubmissionIdsByLocale(
                [[$original]],
                $precedence
            );
            $this->assertSame([10], $visibleSubmissionIds);
        }
    }

    private function mockSubmission(int $id, string $locale, ?int $isTranslationOf = null)
    {
        return new class ($id, $locale, $isTranslationOf) {
            private $id;
            private $locale;
            private $isTranslationOf;

            public function __construct(int $id, string $locale, ?int $isTranslationOf)
            {
                $this->id = $id;
                $this->locale = $locale;
                $this->isTranslationOf = $isTranslationOf;
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }

            public function getData(string $key)
            {
                if ($key === 'isTranslationOf') {
                    return $this->isTranslationOf;
                }

                return null;
            }
        };
    }
}
