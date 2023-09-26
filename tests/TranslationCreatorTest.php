<?php

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.services.PKPSchemaService');
import('classes.article.Author');
import('plugins.generic.submissionsTranslation.classes.TranslationCreator');

class TranslationCreatorTest extends DatabaseTestCase
{
    private $translationCreator;
    private $originalLocale = 'en_US';
    private $translationLocale = 'fr_CA';

    public function setUp(): void
    {
        parent::setUp();
        $this->translationCreator = new TranslationCreator();
    }

    protected function getAffectedTables()
    {
        return ['authors', 'author_settings'];
    }

    private function createTestAuthor($publicationId, $submissionLocale)
    {
        $author = new Author();
        $author->setData('email', 'egyptian.cat@mailinator.com');
        $author->setData('givenName', 'Cat');
        $author->setData('familyName', 'Ramesses');
        $author->setData('publicationId', $publicationId);
        $author->setData('submissionLocale', $submissionLocale);

        DAORegistry::getDAO('AuthorDAO')->insertObject($author);
        return $author;
    }

    public function testCreatesTranslationAuthors(): void
    {
        $originalPublicationId = 1234;
        $author = $this->createTestAuthor($originalPublicationId, $this->originalLocale);

        $translationPublicationId = 2345;
        $newAuthorId = $this->translationCreator->createTranslationAuthor($author, $translationPublicationId, $this->translationLocale);
        $translationAuthor = DAORegistry::getDAO('AuthorDAO')->getById($newAuthorId);

        $this->assertEquals($author->getData('email'), $translationAuthor->getData('email'));
        $this->assertEquals($author->getData('givenName'), $translationAuthor->getData('givenName'));
        $this->assertEquals($author->getData('familyName'), $translationAuthor->getData('familyName'));
        $this->assertEquals($translationPublicationId, $translationAuthor->getData('publicationId'));
        $this->assertEquals($this->translationLocale, $translationAuthor->getData('submissionLocale'));
    }
}
