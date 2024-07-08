<?php

import('lib.pkp.tests.DatabaseTestCase');
import('classes.article.Author');
import('classes.publication.Publication');
import('classes.submission.Submission');
import('plugins.generic.DoiForTranslation.classes.TranslationCreator');
import('plugins.generic.DoiForTranslation.DoiForTranslationPlugin');

class TranslationCreatorTest extends DatabaseTestCase
{
    private $translationCreator;
    private $submissionId;
    private $publicationId;
    private $authorId;
    private $originalLocale = 'en_US';
    private $originalTitle = "Cat species of Egypt";
    private $translationLocale = 'fr_CA';
    private $translationTitle = "Espèces de chats d'Egypte";
    private $authorEmail = 'egyptian.cat@mailinator.com';
    private $authorGivenName = 'Cat';
    private $authorFamilyName = 'Ramesses';

    public function setUp(): void
    {
        parent::setUp();

        $plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', array($plugin, 'addOurFieldsToSubmissionSchema'));

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
        $author->setData('email', $this->authorEmail);
        $author->setData('givenName', $this->authorGivenName, $this->originalLocale);
        $author->setData('familyName', $this->authorFamilyName, $this->originalLocale);
        $author->setData('publicationId', $this->publicationId);
        $author->setData('submissionLocale', $this->originalLocale);

        return DAORegistry::getDAO('AuthorDAO')->insertObject($author);
    }

    private function createTestPublication()
    {
        $publication = new Publication();
        $publication->setData('status', STATUS_QUEUED);
        $publication->setData('version', 1);
        $publication->setData('title', $this->originalTitle, $this->originalLocale);
        $publication->setData('title', $this->translationTitle, $this->translationLocale);
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

    public function testCreatesTranslationSubmission(): void
    {
        $translationSubmissionId = $this->translationCreator->createTranslation($this->submissionId, $this->translationLocale);

        $translationSubmission = DAORegistry::getDAO('SubmissionDAO')->getById($translationSubmissionId);
        $this->assertNotEquals($this->submissionId, $translationSubmissionId);
        $this->assertEquals($this->translationLocale, $translationSubmission->getData('locale'));
        $this->assertEquals($this->submissionId, $translationSubmission->getData('isTranslationOf'));

        $translationPublication = $translationSubmission->getData('publications')[0];
        $this->assertNotEquals($this->publicationId, $translationPublication->getId());
        $this->assertEquals($this->translationLocale, $translationPublication->getData('locale'));
        $this->assertEquals($this->originalTitle, $translationPublication->getData('title', $this->originalLocale));
        $this->assertEquals($this->translationTitle, $translationPublication->getData('title', $this->translationLocale));
        $this->assertEquals($translationPublication->getId(), $translationSubmission->getData('currentPublicationId'));

        $translationAuthor = $translationPublication->getData('authors')[0];
        $this->assertNotEquals($this->authorId, $translationAuthor->getId());
        $this->assertEquals($this->authorEmail, $translationAuthor->getData('email'));
        $this->assertEquals($this->authorGivenName, $translationAuthor->getData('givenName', $this->originalLocale));
        $this->assertEquals($this->authorFamilyName, $translationAuthor->getData('familyName', $this->originalLocale));
        $this->assertEquals($this->translationLocale, $translationAuthor->getData('submissionLocale'));
    }
}
