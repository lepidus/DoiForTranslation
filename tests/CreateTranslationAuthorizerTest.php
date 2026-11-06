<?php

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation\CreateTranslationAuthorizer;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\security\authorization\AuthorizationDecisionManager;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\user\User;

class CreateTranslationAuthorizerTest extends TestCase
{
    private int $contextId;
    private int $submissionId;
    private array $userGroupIds = [];

    public function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->contextId = $this->createContext();
        $this->submissionId = $this->createSubmission();
    }

    public function tearDown(): void
    {
        Registry::delete('user');
        DB::rollBack();
        parent::tearDown();
    }

    public function testKeepsSubmissionAccessPolicy(): void
    {
        $policies = $this->getPolicies($this->createMock(PKPRequest::class));

        $this->assertInstanceOf(SubmissionAccessPolicy::class, $policies[0]);
    }

    public function testPermitsAssignedSectionEditor(): void
    {
        $this->assertPermitted(Role::ROLE_ID_SUB_EDITOR, [[Role::ROLE_ID_SUB_EDITOR, false]]);
    }

    public function testDeniesRecommendOnlySectionEditor(): void
    {
        $this->assertDenied(Role::ROLE_ID_SUB_EDITOR, [[Role::ROLE_ID_SUB_EDITOR, true]]);
    }

    public function testDeniesSectionEditorAssignedOnlyAsAuthor(): void
    {
        $this->assertDenied(Role::ROLE_ID_SUB_EDITOR, [[Role::ROLE_ID_AUTHOR, false]], [Role::ROLE_ID_AUTHOR]);
    }

    public function testPermitsUnassignedManager(): void
    {
        $this->assertPermitted(Role::ROLE_ID_MANAGER, []);
    }

    public function testPermitsManagerAssignedAsSectionEditor(): void
    {
        $this->assertPermitted(
            Role::ROLE_ID_MANAGER,
            [[Role::ROLE_ID_SUB_EDITOR, false]],
            [Role::ROLE_ID_SUB_EDITOR]
        );
    }

    public function testDeniesRecommendOnlyManager(): void
    {
        $this->assertDenied(Role::ROLE_ID_MANAGER, [[Role::ROLE_ID_MANAGER, true]]);
    }

    public function testPermitsUnassignedSiteAdministrator(): void
    {
        $this->assertPermitted(Role::ROLE_ID_SITE_ADMIN, []);
    }

    private function assertPermitted(int $globalRole, array $assignments, array $extraRoles = []): void
    {
        $this->assertSame(
            AuthorizationPolicy::AUTHORIZATION_PERMIT,
            $this->decide($globalRole, $assignments, $extraRoles)
        );
    }

    private function assertDenied(int $globalRole, array $assignments, array $extraRoles = []): void
    {
        $this->assertSame(
            AuthorizationPolicy::AUTHORIZATION_DENY,
            $this->decide($globalRole, $assignments, $extraRoles)
        );
    }

    private function decide(int $globalRole, array $assignments, array $extraRoles): int
    {
        $user = new User();
        $user->setData('id', $this->createUser());
        Registry::set('user', $user);

        foreach ($assignments as [$roleId, $recommendOnly]) {
            $this->assign($user->getId(), $roleId, $recommendOnly);
        }

        $journal = new Journal();
        $journal->setData('id', $this->contextId);
        $request = $this->createMock(PKPRequest::class);
        $request->method('getContext')->willReturn($journal);
        $request->method('getUser')->willReturn($user);

        $decisionManager = new AuthorizationDecisionManager();
        $decisionManager->setDecisionIfNoPolicyApplies(AuthorizationPolicy::AUTHORIZATION_DENY);
        $decisionManager->_authorizedContext = [
            Application::ASSOC_TYPE_SUBMISSION => Repo::submission()->get($this->submissionId),
            Application::ASSOC_TYPE_USER_ROLES => [$globalRole, ...$extraRoles],
        ];

        foreach ($this->getPolicies($request) as $policy) {
            if ($policy instanceof UserAccessibleWorkflowStageRequiredPolicy || $policy instanceof StageRolePolicy) {
                $decisionManager->addPolicy($policy);
            }
        }

        return $decisionManager->decide();
    }

    private function getPolicies(PKPRequest $request): array
    {
        $args = [];

        return (new CreateTranslationAuthorizer())->getPolicies($request, $args, []);
    }

    private function createContext(): int
    {
        return DB::table('journals')->insertGetId([
            'path' => 'dftAuthorizer' . uniqid(),
            'seq' => 0,
            'primary_locale' => 'en',
            'enabled' => 1,
        ]);
    }

    private function createSubmission(): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $this->contextId);
        $submission->setData('status', Submission::STATUS_QUEUED);
        $submission->setData('locale', 'en');
        $submission->setData('stageId', WORKFLOW_STAGE_ID_SUBMISSION);

        return Repo::submission()->dao->insert($submission);
    }

    private function createUser(): int
    {
        $username = 'dft' . uniqid();

        return DB::table('users')->insertGetId([
            'username' => $username,
            'password' => 'unused',
            'email' => $username . '@example.com',
            'date_registered' => '2026-01-01 00:00:00',
        ]);
    }

    private function assign(int $userId, int $roleId, bool $recommendOnly): void
    {
        DB::table('stage_assignments')->insert([
            'submission_id' => $this->submissionId,
            'user_group_id' => $this->getUserGroupId($roleId),
            'user_id' => $userId,
            'date_assigned' => '2026-01-01 00:00:00',
            'recommend_only' => (int) $recommendOnly,
        ]);
    }

    private function getUserGroupId(int $roleId): int
    {
        if (isset($this->userGroupIds[$roleId])) {
            return $this->userGroupIds[$roleId];
        }

        $userGroupId = DB::table('user_groups')->insertGetId([
            'context_id' => $this->contextId,
            'role_id' => $roleId,
        ]);

        DB::table('user_group_stage')->insert(array_map(
            fn (int $stageId) => [
                'context_id' => $this->contextId,
                'user_group_id' => $userGroupId,
                'stage_id' => $stageId,
            ],
            [WORKFLOW_STAGE_ID_SUBMISSION, WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION]
        ));

        return $this->userGroupIds[$roleId] = $userGroupId;
    }
}
