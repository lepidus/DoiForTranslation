<?php

namespace APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation;

use PKP\core\PKPRequest;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\authorization\internal\SubmissionCompletePolicy;
use PKP\security\authorization\internal\UserAccessibleWorkflowStageRequiredPolicy;
use PKP\security\authorization\StageRolePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class CreateTranslationAuthorizer implements HasAuthorizationPolicy
{
    public function getPolicies(PKPRequest $request, array &$args, array $roleAssignments): array
    {
        return [
            new SubmissionAccessPolicy($request, $args, $roleAssignments),
            new SubmissionCompletePolicy($request, $args),
            new UserAccessibleWorkflowStageRequiredPolicy($request),
            new StageRolePolicy([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], null, false),
        ];
    }
}
