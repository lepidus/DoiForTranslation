<?php

/**
 * @file plugins/generic/doiForTranslation/classes/TranslationsDAO.inc.php
 *
 * @class TranslationsDAO
 * @ingroup plugins_generic_DoiForTranslation
 *
 * Operations for retrieving data of translations
 */

import('lib.pkp.classes.db.DAO');

use Illuminate\Database\Capsule\Manager as Capsule;

class TranslationsDAO extends DAO
{
    public function getTranslations(int $submissionId, int $contextId, bool $onlyPublished = false): array
    {
        $groupedTranslations = $this->getTranslationsBySubmissionIds([$submissionId], $contextId, $onlyPublished);

        return $groupedTranslations[$submissionId] ?? [];
    }

    public function getTranslationsBySubmissionIds(array $submissionIds, int $contextId, bool $onlyPublished = false): array
    {
        $submissionIds = array_values(array_unique(array_map('intval', $submissionIds)));
        $groupedTranslations = [];

        foreach ($submissionIds as $submissionId) {
            $groupedTranslations[$submissionId] = [];
        }

        if (empty($submissionIds)) {
            return $groupedTranslations;
        }

        $query = Capsule::table('submission_settings AS sub_s')
            ->join('submissions AS sub', 'sub.submission_id', '=', 'sub_s.submission_id')
            ->select('sub_s.submission_id AS id', 'sub.locale', 'sub_s.setting_value AS original_submission_id')
            ->where('sub_s.setting_name', '=', 'isTranslationOf')
            ->whereIn('sub_s.setting_value', array_map('strval', $submissionIds))
            ->where('sub.context_id', '=', $contextId);

        if ($onlyPublished) {
            $query->where('sub.status', '=', STATUS_PUBLISHED);
        }

        foreach ($query->get()->toArray() as $row) {
            $translation = get_object_vars($row);
            $originalSubmissionId = (int) $translation['original_submission_id'];

            if (!array_key_exists($originalSubmissionId, $groupedTranslations)) {
                continue;
            }

            $groupedTranslations[$originalSubmissionId][] = [
                'id' => (int) $translation['id'],
                'locale' => $translation['locale'],
            ];
        }

        return $groupedTranslations;
    }

    public function getTitle(int $submissionId, string $locale = null): string
    {
        $titles = $this->getTitlesBySubmissionIds(
            [$submissionId],
            is_null($locale) ? [] : [$submissionId => $locale]
        );

        return $titles[$submissionId] ?? '';
    }

    public function getTitlesBySubmissionIds(array $submissionIds, array $localesBySubmissionId = []): array
    {
        $submissionIds = array_values(array_unique(array_map('intval', $submissionIds)));
        $titlesBySubmissionId = [];

        foreach ($submissionIds as $submissionId) {
            $titlesBySubmissionId[$submissionId] = '';
        }

        if (empty($submissionIds)) {
            return $titlesBySubmissionId;
        }

        $submissions = Capsule::table('submissions')
            ->whereIn('submission_id', $submissionIds)
            ->select('submission_id', 'current_publication_id', 'locale')
            ->get();

        $publicationIds = [];
        $locales = [];
        $submissionRows = [];

        foreach ($submissions->toArray() as $row) {
            $submission = get_object_vars($row);
            $submissionId = (int) $submission['submission_id'];
            $publicationId = $submission['current_publication_id'];

            if (empty($publicationId)) {
                continue;
            }

            $effectiveLocale = $localesBySubmissionId[$submissionId] ?? $submission['locale'];
            $publicationIds[] = $publicationId;
            $locales[] = $effectiveLocale;
            $submissionRows[$submissionId] = [
                'publicationId' => (int) $publicationId,
                'locale' => $effectiveLocale,
            ];
        }

        if (empty($publicationIds)) {
            return $titlesBySubmissionId;
        }

        $publicationSettings = Capsule::table('publication_settings')
            ->whereIn('publication_id', array_values(array_unique($publicationIds)))
            ->whereIn('setting_name', ['prefix', 'title', 'subtitle'])
            ->whereIn('locale', array_values(array_unique($locales)))
            ->select('publication_id', 'setting_name', 'locale', 'setting_value')
            ->get();

        $settingsByPublicationAndLocale = [];

        foreach ($publicationSettings->toArray() as $row) {
            $setting = get_object_vars($row);
            $settingsByPublicationAndLocale[(int) $setting['publication_id']][$setting['locale']][$setting['setting_name']] = $setting['setting_value'];
        }

        foreach ($submissionRows as $submissionId => $submission) {
            $publicationSettings = $settingsByPublicationAndLocale[$submission['publicationId']][$submission['locale']] ?? [];
            $title = $publicationSettings['title'] ?? null;

            if (is_null($title) || $title === '') {
                continue;
            }

            $fullArticleTitle = $title;
            if (!empty($publicationSettings['prefix'])) {
                $fullArticleTitle = $publicationSettings['prefix'] . ' ' . $title;
            }

            $titlesBySubmissionId[$submissionId] = !empty($publicationSettings['subtitle'])
                ? PKPString::concatTitleFields([$fullArticleTitle, $publicationSettings['subtitle']])
                : $fullArticleTitle;
        }

        return $titlesBySubmissionId;
    }
}
