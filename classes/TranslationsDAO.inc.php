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
use Illuminate\Support\Collection;

class TranslationsDAO extends DAO
{
    public function getTranslations(int $submissionId, bool $onlyPublished = false): array
    {
        $query = Capsule::table('submission_settings AS sub_s')
            ->leftJoin('submissions AS sub', 'sub.submission_id', '=', 'sub_s.submission_id')
            ->select('sub_s.submission_id AS id', 'sub.locale')
            ->where('sub_s.setting_name', '=', 'isTranslationOf')
            ->where('sub_s.setting_value', '=', $submissionId);

        if ($onlyPublished) {
            $query->where('sub.status', '=', STATUS_PUBLISHED);
        }

        $result = $query->get();
        $translations = [];

        foreach ($result->toArray() as $row) {
            $translations[] = get_object_vars($row);
        }

        return $translations;
    }

    public function getTitle(int $submissionId, string $locale = null): string
    {
        $result = Capsule::table('submissions')
            ->where('submission_id', '=', $submissionId)
            ->select('current_publication_id', 'locale')
            ->first();
        $result = get_object_vars($result);

        $publicationId = $result['current_publication_id'];
        if (is_null($locale)) {
            $locale = $result['locale'];
        }

        $prefixResultQuery = $this->retrieveArticleNameSetting($publicationId, 'prefix', $locale);
        $titleResultQuery = $this->retrieveArticleNameSetting($publicationId, 'title', $locale);
        $subtitleResultQuery = $this->retrieveArticleNameSetting($publicationId, 'subtitle', $locale);

        if (!is_null($titleResultQuery)) {
            $title = get_object_vars($titleResultQuery)['title'];
            $prefix = get_object_vars($prefixResultQuery)['prefix'] ?? null;
            $fullTitle = $title;
            if ($prefix) {
                $fullTitle = $prefix . ' ' . $title;
            }
            $subtitle = get_object_vars($subtitleResultQuery)['subtitle'] ?? null;
            if ($subtitle) {
                return PKPString::concatTitleFields([$fullTitle, $subtitle]);
            }

            return $fullTitle;
        }

        return '';
    }

    private function retrieveArticleNameSetting($publicationId, $settingName, $locale)
    {
        return Capsule::table('publication_settings')
            ->where('publication_id', '=', $publicationId)
            ->where('setting_name', '=', $settingName)
            ->where('locale', '=', $locale)
            ->select("setting_value as $settingName")
            ->first();
    }
}
