<?php

import('lib.pkp.classes.security.authorization.AuthorizationDecisionManager');
import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
import('lib.pkp.classes.security.authorization.internal.SubmissionCompletePolicy');
import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
import('lib.pkp.classes.security.authorization.StageRolePolicy');

/**
 * Policies for creating a translation: only editors who can take decisions
 * on the original (complete) submission in its current stage.
 */
class CreateTranslationAuthorizer
{
    public function getPolicies($request, array &$args, array $roleAssignments): array
    {
        return array_merge(
            [
                new SubmissionAccessPolicy($request, $args, $roleAssignments),
                new SubmissionCompletePolicy($request, $args),
            ],
            $this->getStagePolicies($request)
        );
    }

    public function getStagePolicies($request): array
    {
        return [
            new UserAccessibleWorkflowStageRequiredPolicy($request),
            new StageRolePolicy([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], null, false),
        ];
    }

    /**
     * Whether the current user may create a translation of a submission they
     * already access, applying the same completeness and stage policies as the API.
     */
    public function canCreateTranslation($request, $submission, array $userRoles): bool
    {
        if ($submission->getData('submissionProgress')) {
            return false;
        }

        $decisionManager = new AuthorizationDecisionManager();
        $decisionManager->setDecisionIfNoPolicyApplies(AUTHORIZATION_DENY);
        $decisionManager->_authorizedContext = [
            ASSOC_TYPE_SUBMISSION => $submission,
            ASSOC_TYPE_USER_ROLES => $userRoles,
        ];
        foreach ($this->getStagePolicies($request) as $policy) {
            $decisionManager->addPolicy($policy);
        }

        return $decisionManager->decide() === AUTHORIZATION_PERMIT;
    }
}
