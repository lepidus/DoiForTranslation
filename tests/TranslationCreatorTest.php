<?php

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.services.PKPSchemaService');
import('classes.article.Author');
import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.submissionsTranslation.classes.TranslationCreator');

class TranslationCreatorTest extends DatabaseTestCase
{
    private $translationCreator;
    private $submissionId;
    private $publicationId;
    private $authorId;
    private $originalLocale = 'en_US';
    private $translationLocale = 'fr_CA';

    public function setUp(): void
    {
        parent::setUp();
        $this->translationCreator = new TranslationCreator();
        $this->submissionId = $this->createTestSubmission();
        $this->publicationId = $this->createTestPublication();
        $this->authorId = $this->createTestAuthor();
        $this->updateCurrentPublication();
    }

    protected function getAffectedTables()
    {
        return ['submissions', 'submission_settings', 'publications', 'publication_settings', 'authors', 'author_settings'];
    }

    private function createTestAuthor()
    {
        $author = new Author();
        $author->setData('email', 'egyptian.cat@mailinator.com');
        $author->setData('givenName', 'Cat', $this->originalLocale);
        $author->setData('familyName', 'Ramesses', $this->originalLocale);
        $author->setData('publicationId', $this->publicationId);
        $author->setData('submissionLocale', $this->originalLocale);

        return DAORegistry::getDAO('AuthorDAO')->insertObject($author);
    }

    private function createTestPublication()
    {
        $publication = new Publication();
        $publication->setData('status', STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('title', 'Cat species of Egypt', $this->originalLocale);
        $publication->setData('submissionId', $this->submissionId);
        $publication->setData('locale', $this->originalLocale);

        return DAORegistry::getDAO('PublicationDAO')->insertObject($publication);
    }

    private function createTestSubmission()
    {
        $submission = new Submission();
        $submission->setData('contextId', 1);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', $this->originalLocale);

        return DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
    }

    private function updateCurrentPublication()
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO');
        $submission = $submissionDao->getById($this->submissionId);

        $submission->setData('currentPublicationId', $this->publicationId);
        $submissionDao->updateObject($submission);
    }
}
