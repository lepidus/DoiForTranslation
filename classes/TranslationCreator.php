<?php

namespace APP\plugins\generic\doiForTranslation\classes;

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;

class TranslationCreator
{
    public function createTranslation($submissionId, $translationLocale)
    {
        return DB::transaction(function () use ($submissionId, $translationLocale) {
            $submissionDao = Repo::submission()->dao;
            $submission = Repo::submission()->get($submissionId);

            $newSubmission = clone $submission;
            $newSubmission->setData('id', null);
            $newSubmission->setData('locale', $translationLocale);
            $newSubmission->setData('isTranslationOf', $submissionId);
            $newSubmission->setData('status', Submission::STATUS_QUEUED);

            $newSubmissionId = $submissionDao->insert($newSubmission);
            $this->setTranslationOrigin($newSubmissionId, $submissionId);
            $newSubmission->setData('id', $newSubmissionId);

            $originalLocale = $submission->getData('locale');

            $publications = Repo::publication()->getCollector()
                ->filterBySubmissionIds([$submissionId])
                ->getMany();

            foreach ($publications as $publication) {
                $newPublicationId = $this->createTranslationPublication($publication, $newSubmissionId, $translationLocale, $originalLocale);

                if ($publication->getId() == $submission->getData('currentPublicationId')) {
                    $newSubmission->setData('currentPublicationId', $newPublicationId);
                    $submissionDao->update($newSubmission);
                }
            }

            return $newSubmissionId;
        });
    }

    private function setTranslationOrigin(int $translationSubmissionId, int $originalSubmissionId): void
    {
        DB::table('submission_settings')->updateOrInsert(
            [
                'submission_id' => $translationSubmissionId,
                'locale' => '',
                'setting_name' => 'isTranslationOf',
            ],
            ['setting_value' => (string) $originalSubmissionId]
        );
    }

    protected function createTranslationPublication($publication, $newSubmissionId, $translationLocale, $originalLocale)
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        $newPublication->setData('submissionId', $newSubmissionId);
        $newPublication->setData('locale', $translationLocale);
        $newPublication->setData('status', Submission::STATUS_QUEUED);
        $publicationDao = Repo::publication()->dao;
        $newPublicationId = $publicationDao->insert($newPublication);

        $authors = Repo::author()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        foreach ($authors as $author) {
            $this->createTranslationAuthor($author, $newPublicationId, $translationLocale, $originalLocale);
        }

        return $newPublicationId;
    }

    private function createTranslationAuthor($author, $newPublicationId, $translationLocale, $originalLocale)
    {
        $newAuthor = clone $author;
        $newAuthor->setData('id', null);
        $newAuthor->setData('publicationId', $newPublicationId);
        $newAuthor->setData('submissionLocale', $translationLocale);
        if (empty($newAuthor->getData('givenName', $translationLocale))) {
            $authorName = $newAuthor->getData('givenName', $originalLocale);
            $newAuthor->setData('givenName', $authorName, $translationLocale);
        }
        if (empty($newAuthor->getData('familyName', $translationLocale))) {
            $authorName = $newAuthor->getData('familyName', $originalLocale);
            $newAuthor->setData('familyName', $authorName, $translationLocale);
        }

        $authorDao = Repo::author()->dao;
        return $authorDao->insert($newAuthor);
    }
}
