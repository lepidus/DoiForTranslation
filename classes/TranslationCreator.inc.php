<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class TranslationCreator
{
    public function createTranslation($submissionId, $translationLocale)
    {
        return Capsule::connection()->transaction(function () use ($submissionId, $translationLocale) {
            $submissionDao = DAORegistry::getDAO('SubmissionDAO');
            $submission = $submissionDao->getById($submissionId);

            $newSubmission = clone $submission;
            $newSubmission->setData('id', null);
            $newSubmission->setData('currentPublicationId', null);
            $newSubmission->setData('dateSubmitted', Core::getCurrentDate());
            $newSubmission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
            $newSubmission->stampModified();
            $newSubmission->stampLastActivity();
            $newSubmission->setData('locale', $translationLocale);
            $newSubmission->setData('isTranslationOf', $submissionId);
            $newSubmission->setData('status', STATUS_QUEUED);

            $newSubmissionId = $submissionDao->insertObject($newSubmission);
            $newSubmission->setData('id', $newSubmissionId);

            $originalLocale = $submission->getData('locale');

            foreach ($submission->getData('publications') as $publication) {
                $newPublicationId = $this->createTranslationPublication($publication, $newSubmissionId, $translationLocale, $originalLocale);

                if ($publication->getId() == $submission->getData('currentPublicationId')) {
                    $newSubmission->setData('currentPublicationId', $newPublicationId);
                    $submissionDao->updateObject($newSubmission);
                }
            }

            return $newSubmissionId;
        });
    }

    protected function createTranslationPublication($publication, $newSubmissionId, $translationLocale, $originalLocale)
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        foreach (['pub-id::doi', 'doiSuffix', 'urlPath', 'datePublished', 'issueId', 'primaryContactId'] as $field) {
            $newPublication->setData($field, null);
        }
        $newPublication->setData('submissionId', $newSubmissionId);
        $newPublication->setData('locale', $translationLocale);
        $newPublication->setData('status', STATUS_QUEUED);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $newPublicationId = $publicationDao->insertObject($newPublication);

        foreach ($publication->getData('authors') as $author) {
            $newAuthorId = $this->createTranslationAuthor($author, $newPublicationId, $translationLocale, $originalLocale);
            if ($author->getId() == $publication->getData('primaryContactId')) {
                $newPublication->setData('primaryContactId', $newAuthorId);
                $publicationDao->updateObject($newPublication);
            }
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

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        return $authorDao->insertObject($newAuthor);
    }
}
