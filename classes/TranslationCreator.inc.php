<?php

class TranslationCreator
{
    public function createTranslation($submissionId, $translationLocale)
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($submissionId);

        $newSubmission = clone $submission;
        $newSubmission->setData('id', null);
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
    }

    private function createTranslationPublication($publication, $newSubmissionId, $translationLocale, $originalLocale)
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        $newPublication->setData('submissionId', $newSubmissionId);
        $newPublication->setData('locale', $translationLocale);
        $newPublication->setData('status', STATUS_QUEUED);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $newPublicationId = $publicationDao->insertObject($newPublication);

        foreach ($publication->getData('authors') as $author) {
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
        if (empty($author->getData('givenName', $translationLocale))) {
            $authorName = $author->getData('givenName', $originalLocale);
            $author->setData('givenName', $authorName, $translationLocale);
        }
        if (empty($author->getData('familyName', $translationLocale))) {
            $authorName = $author->getData('familyName', $originalLocale);
            $author->setData('familyName', $authorName, $translationLocale);
        }

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        return $authorDao->insertObject($newAuthor);
    }
}
