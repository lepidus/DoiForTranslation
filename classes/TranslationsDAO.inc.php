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
    public function getTranslations(int $submissionId): array
    {
        $result = Capsule::table('submission_settings AS sub_s')
            ->leftJoin('submissions AS sub', 'sub.submission_id', '=', 'sub_s.submission_id')
            ->select('sub_s.submission_id AS id', 'sub.locale')
            ->where('sub_s.setting_name', '=', 'isTranslationOf')
            ->where('sub_s.setting_value', '=', $submissionId)
            ->get();

        $translations = [];
        foreach($result->toArray() as $row) {
            $translations[] = get_object_vars($row);
        }

        return $translations;
    }
}
