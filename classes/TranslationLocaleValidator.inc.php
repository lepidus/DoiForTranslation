<?php

class TranslationLocaleValidator
{
    public function isAvailable(
        string $translationLocale,
        string $originalLocale,
        bool $isTranslation,
        array $supportedLocales,
        array $existingTranslationLocales
    ): bool {
        if ($translationLocale === '') {
            return false;
        }

        if ($isTranslation) {
            return false;
        }

        if ($translationLocale === $originalLocale) {
            return false;
        }

        if (!in_array($translationLocale, $supportedLocales, true)) {
            return false;
        }

        if (in_array($translationLocale, $existingTranslationLocales, true)) {
            return false;
        }

        return true;
    }
}
