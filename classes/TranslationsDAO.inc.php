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
            ->select('sub_s.submission_id')
            ->where('sub_s.setting_name', '=', 'isTranslationOf')
            ->where('sub_s.setting_value', '=', $submissionId)
            ->get();

        $translationsIds = [];
        foreach($result as $row) {
            $translationsIds[] = $row->{'submission_id'};
        }

        return $translationsIds;
    }
}
