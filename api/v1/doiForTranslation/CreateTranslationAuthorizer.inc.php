<?php

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
        return [
            new SubmissionAccessPolicy($request, $args, $roleAssignments),
            new SubmissionCompletePolicy($request, $args),
            new UserAccessibleWorkflowStageRequiredPolicy($request),
            new StageRolePolicy([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], null, false),
        ];
    }
}
