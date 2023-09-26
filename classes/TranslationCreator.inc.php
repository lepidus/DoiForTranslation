<?php

class TranslationCreator
{
    public function createTranslationAuthor($author, $publicationId, $translationLocale): int
    {
        $newAuthor = clone $author;
        $newAuthor->setData('id', null);
        $newAuthor->setData('publicationId', $publicationId);
        $newAuthor->setData('submissionLocale', $translationLocale);

        $authorDao = DAORegistry::getDAO('AuthorDAO');
        return $authorDao->insertObject($newAuthor);
    }
}
