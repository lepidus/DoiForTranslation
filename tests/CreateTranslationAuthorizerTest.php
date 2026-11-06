<?php

import('lib.pkp.classes.core.PKPRequest');
import('lib.pkp.classes.core.Registry');
import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('classes.journal.Journal');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.api.v1.doiForTranslation.CreateTranslationAuthorizer');

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

class CreateTranslationAuthorizerTest extends TestCase
{
    private $contextId = 1;
    private $submissionId;
    private $userGroupIds = [];
    private $requestBackup;

    public function setUp(): void
    {
        parent::setUp();
        Capsule::connection()->beginTransaction();
        $this->requestBackup = Registry::get('request', true, null);
        $this->submissionId = $this->createSubmission(0);
    }

    public function tearDown(): void
    {
        Registry::set('request', $this->requestBackup);
        Capsule::connection()->rollBack();
        parent::tearDown();
    }

    public function testKeepsSubmissionAccessPolicyFirst(): void
    {
        $policies = $this->getPolicies($this->createMock(PKPRequest::class));

        $this->assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
    }

    public function testRequiresCompleteSubmission(): void
    {
        $policies = $this->getPolicies($this->createMock(PKPRequest::class));

        $this->assertInstanceOf(SubmissionCompletePolicy::class, $policies[1]);
    }

    public function testPermitsAssignedSectionEditor(): void
    {
        $this->assertPermitted(ROLE_ID_SUB_EDITOR, [[ROLE_ID_SUB_EDITOR, false]]);
    }

    public function testDeniesRecommendOnlySectionEditor(): void
    {
        $this->assertDenied(ROLE_ID_SUB_EDITOR, [[ROLE_ID_SUB_EDITOR, true]]);
    }

    public function testDeniesSectionEditorAssignedOnlyAsAuthor(): void
    {
        $this->assertDenied(ROLE_ID_SUB_EDITOR, [[ROLE_ID_AUTHOR, false]], [ROLE_ID_AUTHOR]);
    }

    public function testPermitsUnassignedManager(): void
    {
        $this->assertPermitted(ROLE_ID_MANAGER, []);
    }

    public function testDeniesRecommendOnlyManager(): void
    {
        $this->assertDenied(ROLE_ID_MANAGER, [[ROLE_ID_MANAGER, true]]);
    }

    public function testDeniesIncompleteSubmission(): void
    {
        $incompleteSubmissionId = $this->createSubmission(1);
        $policy = $this->getMockBuilder(SubmissionCompletePolicy::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDataObjectId'])
            ->getMock();

        $policy->method('getDataObjectId')->willReturn($incompleteSubmissionId);
        $this->assertSame(AUTHORIZATION_DENY, $policy->dataObjectEffect());

        $completePolicy = $this->getMockBuilder(SubmissionCompletePolicy::class)
            ->disableOriginalConstructor()
            ->setMethods(['getDataObjectId'])
            ->getMock();
        $completePolicy->method('getDataObjectId')->willReturn($this->submissionId);
        $this->assertSame(AUTHORIZATION_PERMIT, $completePolicy->dataObjectEffect());
    }

    private function assertPermitted(int $globalRole, array $assignments, array $extraRoles = []): void
    {
        $this->assertSame(AUTHORIZATION_PERMIT, $this->decide($globalRole, $assignments, $extraRoles));
    }

    private function assertDenied(int $globalRole, array $assignments, array $extraRoles = []): void
    {
        $this->assertSame(AUTHORIZATION_DENY, $this->decide($globalRole, $assignments, $extraRoles));
    }

    private function decide(int $globalRole, array $assignments, array $extraRoles): int
    {
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $user = $userDao->newDataObject();
        $username = 'dft' . uniqid();
        $user->setUsername($username);
        $user->setPassword('unused');
        $user->setEmail($username . '@example.com');
        $user->setDateRegistered(Core::getCurrentDate());
        $user->setId($userDao->insertObject($user));

        foreach ($assignments as [$roleId, $recommendOnly]) {
            $this->assign($user->getId(), $roleId, $recommendOnly);
        }

        $journal = $this->getMockBuilder(Journal::class)
            ->setMethods(['getId'])
            ->getMock();
        $journal->method('getId')->willReturn($this->contextId);
        $request = $this->getMockBuilder(PKPRequest::class)
            ->disableOriginalConstructor()
            ->setMethods(['getContext', 'getUser'])
            ->getMock();
        $request->method('getContext')->willReturn($journal);
        $request->method('getUser')->willReturn($user);
        // StageRolePolicy reads the user from the application request.
        Registry::set('request', $request);

        $decisionManager = new AuthorizationDecisionManager();
        $decisionManager->setDecisionIfNoPolicyApplies(AUTHORIZATION_DENY);
        $decisionManager->_authorizedContext = [
            ASSOC_TYPE_SUBMISSION => DAORegistry::getDAO('SubmissionDAO')->getById($this->submissionId),
            ASSOC_TYPE_USER_ROLES => array_merge([$globalRole], $extraRoles),
        ];

        foreach ($this->getPolicies($request) as $policy) {
            if ($policy instanceof UserAccessibleWorkflowStageRequiredPolicy || $policy instanceof StageRolePolicy) {
                $decisionManager->addPolicy($policy);
            }
        }

        return $decisionManager->decide();
    }

    private function getPolicies($request): array
    {
        $args = [];

        return (new CreateTranslationAuthorizer())->getPolicies($request, $args, []);
    }

    private function createSubmission(int $submissionProgress): int
    {
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        $submission = $submissionDao->newDataObject();
        $submission->setData('contextId', $this->contextId);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', 'en_US');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
        $submission->setData('submissionProgress', $submissionProgress);

        return (int) $submissionDao->insertObject($submission);
    }

    private function assign(int $userId, int $roleId, bool $recommendOnly): void
    {
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignmentDao->build($this->submissionId, $this->getUserGroupId($roleId), $userId, $recommendOnly);
    }

    private function getUserGroupId(int $roleId): int
    {
        if (isset($this->userGroupIds[$roleId])) {
            return $this->userGroupIds[$roleId];
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroup = $userGroupDao->newDataObject();
        $userGroup->setContextId($this->contextId);
        $userGroup->setRoleId($roleId);
        $userGroup->setDefault(false);
        $userGroup->setShowTitle(true);
        $userGroup->setPermitSelfRegistration(false);
        $userGroup->setPermitMetadataEdit(false);
        $userGroupId = (int) $userGroupDao->insertObject($userGroup);

        $stages = [
            WORKFLOW_STAGE_ID_SUBMISSION,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            WORKFLOW_STAGE_ID_EDITING,
            WORKFLOW_STAGE_ID_PRODUCTION,
        ];
        foreach ($stages as $stageId) {
            $userGroupDao->assignGroupToStage($this->contextId, $userGroupId, $stageId);
        }

        return $this->userGroupIds[$roleId] = $userGroupId;
    }
}
