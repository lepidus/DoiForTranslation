<?php

/**
 * @file plugins/generic/submissionsTranslation/classes/TranslationsDAO.inc.php
 *
 * @class TranslationsDAO
 * @ingroup plugins_generic_submissionsTranslation
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

        if($onlyPublished) {
            $query->where('sub.status', '=', STATUS_PUBLISHED);
        }

        $result = $query->get();
        $translations = [];

        foreach($result->toArray() as $row) {
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
        if(is_null($locale)) {
            $locale = $result['locale'];
        }

        $result = Capsule::table('publication_settings')
            ->where('publication_id', '=', $publicationId)
            ->where('setting_name', '=', 'title')
            ->where('locale', '=', $locale)
            ->select('setting_value as title')
            ->first();

        if(!is_null($result)) {
            return get_object_vars($result)['title'];
        }

        return '';
    }
}
