<?php

namespace APP\plugins\generic\doiForTranslation\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class TranslationCreator
{
    public function createTranslation($submissionId, $translationLocale)
    {
        return DB::transaction(function () use ($submissionId, $translationLocale) {
            $submissionDao = Repo::submission()->dao;
            $submission = Repo::submission()->get($submissionId);

            $newSubmission = clone $submission;
            $newSubmission->setData('id', null);
            $newSubmission->setData('currentPublicationId', null);
            $newSubmission->setData('dateSubmitted', Core::getCurrentDate());
            $newSubmission->stampLastActivity();
            $newSubmission->stampModified();
            $newSubmission->setData('locale', $translationLocale);
            $newSubmission->setData('isTranslationOf', $submissionId);
            $newSubmission->setData('status', Submission::STATUS_QUEUED);
            $newSubmission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

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
                }
            }

            $submissionDao->update($newSubmission);
            $this->copyEditorialAssignments($submissionId, $newSubmissionId);
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $newSubmissionId,
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
                'userId' => Application::get()->getRequest()->getUser()?->getId(),
                'message' => 'plugins.generic.doiForTranslation.event.created',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
            ]);
            $eventLog->setData('originalSubmissionId', $submissionId);
            Repo::eventLog()->add($eventLog);

            return $newSubmissionId;
        });
    }

    private function copyEditorialAssignments(int $originalId, int $translationId): void
    {
        $assignments = StageAssignment::withSubmissionIds([$originalId])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->select('stage_assignments.*')->get();
        foreach ($assignments as $assignment) {
            Repo::stageAssignment()->build(
                $translationId,
                $assignment->userGroupId,
                $assignment->userId,
                (bool) $assignment->recommendOnly,
                (bool) $assignment->canChangeMetadata
            );
        }
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
        foreach (['doiId', 'urlPath', 'datePublished', 'issueId', 'primaryContactId'] as $field) {
            $newPublication->setData($field, null);
        }
        $newPublication->setData('submissionId', $newSubmissionId);
        $newPublication->setData('locale', $translationLocale);
        $newPublication->setData('status', Submission::STATUS_QUEUED);
        $newPublicationId = Repo::publication()->add($newPublication);

        $authors = Repo::author()->getCollector()
            ->filterByPublicationIds([$publication->getId()])
            ->getMany();

        foreach ($authors as $author) {
            $newAuthorId = $this->createTranslationAuthor($author, $newPublicationId, $translationLocale, $originalLocale);
            if ($author->getId() == $publication->getData('primaryContactId')) {
                Repo::publication()->edit(Repo::publication()->get($newPublicationId), ['primaryContactId' => $newAuthorId]);
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

        return Repo::author()->add($newAuthor);
    }
}
