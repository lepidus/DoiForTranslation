<?php

use Illuminate\Database\Capsule\Manager as Capsule;

class TranslationCreator
{
    private const COVER_IMAGE_EXTENSIONS = ['gif', 'jpg', 'png', 'ico', 'webp'];

    private $copiedCoverFiles = [];

    public function createTranslation($submissionId, $translationLocale)
    {
        $this->copiedCoverFiles = [];
        try {
            return $this->createTranslationInTransaction($submissionId, $translationLocale);
        } catch (Throwable $e) {
            $this->removeCopiedCoverFiles();
            throw $e;
        }
    }

    private function createTranslationInTransaction($submissionId, $translationLocale)
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
                $newPublicationId = $this->createTranslationPublication($publication, $newSubmission, $translationLocale, $originalLocale);

                if ($publication->getId() == $submission->getData('currentPublicationId')) {
                    $newSubmission->setData('currentPublicationId', $newPublicationId);
                    $submissionDao->updateObject($newSubmission);
                }
            }

            return $newSubmissionId;
        });
    }

    protected function createTranslationPublication($publication, $newSubmission, $translationLocale, $originalLocale)
    {
        $contextId = $newSubmission->getData('contextId');

        $newPublication = clone $publication;
        $newPublication->setData('id', null);
        foreach ($this->getFieldsNotInheritedByTranslation($newPublication, $contextId) as $field) {
            $newPublication->setData($field, null);
        }
        $newPublication->setData('submissionId', $newSubmission->getId());
        $newPublication->setData('locale', $translationLocale);
        $newPublication->setData('status', STATUS_QUEUED);
        $publicationDao = DAORegistry::getDAO('PublicationDAO');
        $newPublicationId = $publicationDao->insertObject($newPublication);

        if (!empty($publication->getData('citationsRaw'))) {
            DAORegistry::getDAO('CitationDAO')->importCitations($newPublicationId, $publication->getData('citationsRaw'));
        }

        $coverImages = array_filter((array) $publication->getData('coverImage'));
        if (!empty($coverImages)) {
            $newPublication->setData('coverImage', $this->copyCoverImages($coverImages, $newPublication, $newSubmission));
            $publicationDao->updateObject($newPublication);
        }

        foreach ($publication->getData('authors') as $author) {
            $newAuthorId = $this->createTranslationAuthor($author, $newPublicationId, $translationLocale, $originalLocale);
            if ($author->getId() == $publication->getData('primaryContactId')) {
                $newPublication->setData('primaryContactId', $newAuthorId);
                $publicationDao->updateObject($newPublication);
            }
        }

        return $newPublicationId;
    }

    private function getFieldsNotInheritedByTranslation($publication, $contextId)
    {
        $storedPubIdFields = preg_grep('/^pub-id::/', array_keys($publication->getAllData()));
        $pubIdSuffixFields = array_map(function ($pubIdPlugin) {
            return $pubIdPlugin->getSuffixFieldName();
        }, array_values(PluginRegistry::loadCategory('pubIds', true, $contextId)));

        return array_merge(
            $storedPubIdFields,
            $pubIdSuffixFields,
            ['urlPath', 'datePublished', 'issueId', 'primaryContactId', 'coverImage']
        );
    }

    private function copyCoverImages($coverImages, $newPublication, $newSubmission)
    {
        import('classes.file.PublicFileManager');
        $publicFileManager = new PublicFileManager();
        $contextId = $newSubmission->getData('contextId');
        $contextFilesPath = $publicFileManager->getContextFilesPath($contextId);

        $copiedCoverImages = [];
        foreach ($coverImages as $locale => $coverImage) {
            $uploadName = (string) ($coverImage['uploadName'] ?? '');
            $extension = $this->getCoverImageExtension($uploadName);
            $sourceFile = $contextFilesPath . '/' . $uploadName;
            if (!$extension || !$publicFileManager->fileExists($sourceFile)) {
                continue;
            }

            $copyName = sprintf(
                'submission_%d_%d_coverImage_%s.%s',
                $newSubmission->getId(),
                $newPublication->getId(),
                $locale,
                $extension
            );
            if ($publicFileManager->copyContextFile($contextId, $sourceFile, $copyName)) {
                $this->copiedCoverFiles[] = [$contextId, $copyName];
                $copiedCoverImages[$locale] = array_merge($coverImage, ['uploadName' => $copyName]);
            }
        }

        return $copiedCoverImages;
    }

    private function removeCopiedCoverFiles()
    {
        import('classes.file.PublicFileManager');
        $publicFileManager = new PublicFileManager();
        foreach ($this->copiedCoverFiles as [$contextId, $fileName]) {
            $publicFileManager->removeContextFile($contextId, $fileName);
        }
        $this->copiedCoverFiles = [];
    }

    private function getCoverImageExtension(string $uploadName): ?string
    {
        if ($uploadName !== basename($uploadName)) {
            return null;
        }

        $extension = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
        return in_array($extension, self::COVER_IMAGE_EXTENSIONS, true) ? $extension : null;
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
