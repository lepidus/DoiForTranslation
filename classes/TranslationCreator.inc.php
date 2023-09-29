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

        $newSubmissionId = $submissionDao->insertObject($newSubmission);
        $newSubmission->setData('id', $newSubmissionId);

        foreach($submission->getData('publications') as $publication) {
            $newPublicationId = $this->createTranslationPublication($publication, $newSubmissionId, $translationLocale);

            if($publication->getId() == $submission->getData('currentPublicationId')) {
                $newSubmission->setData('currentPublicationId', $newPublicationId);
                $submissionDao->updateObject($newSubmission);
            }
        }

        return $newSubmissionId;
    }

    private function createTranslationPublication($publication, $newSubmissionId, $translationLocale)
    {
        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        $newPublication->setData('submissionId', $newSubmissionId);
        $newPublication->setData('locale', $translationLocale);

        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $newPublicationId = $publicationDao->insertObject($newPublication);

        foreach($publication->getData('authors') as $author) {
            $this->createTranslationAuthor($author, $newPublicationId, $translationLocale);
        }

        return $newPublicationId;
    }

    private function createTranslationAuthor($author, $newPublicationId, $translationLocale)
    {
        $newAuthor = clone $author;
        $newAuthor->setData('id', null);
        $newAuthor->setData('publicationId', $newPublicationId);
        $newAuthor->setData('submissionLocale', $translationLocale);

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        return $authorDao->insertObject($newAuthor);
    }
}
