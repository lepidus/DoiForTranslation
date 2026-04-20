<?php

import('lib.pkp.tests.DatabaseTestCase');
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('lib.pkp.classes.core.PKPComponentRouter');
import('lib.pkp.classes.core.Dispatcher');
import('lib.pkp.classes.core.PKPRequest');
import('classes.journal.Journal');
import('classes.submission.Submission');
import('plugins.generic.doiForTranslation.api.v1.doiForTranslation.DoiForTranslationHandler');
import('plugins.generic.doiForTranslation.DoiForTranslationPlugin');

class DoiForTranslationHandlerAuthorizationTest extends DatabaseTestCase
{
    protected function getAffectedTables()
    {
        return ['submissions', 'submission_settings'];
    }

    public function setUp(): void
    {
        parent::setUp();
        $plugin = new DoiForTranslationPlugin();
        HookRegistry::register('Schema::get::submission', [$plugin, 'addOurFieldsToSubmissionSchema']);
    }

    public function testDeniesAuthorizationWhenSubmissionBelongsToAnotherContext(): void
    {
        $foreignContextId = 999;
        $localContextId = 1;
        $foreignSubmissionId = $this->createSubmissionInContext($foreignContextId);

        $args = ['submissionId' => $foreignSubmissionId];
        $roleAssignments = [ROLE_ID_MANAGER => ['createTranslation']];

        $handler = new DoiForTranslationHandler();
        $request = $this->buildRequestForContext($localContextId, $handler);
        $handler->addPolicy($this->userRolesInjectionPolicy([ROLE_ID_MANAGER]), true);

        $authorized = $handler->authorize($request, $args, $roleAssignments);

        $this->assertFalse($authorized, 'Handler must deny requests targeting submissions from another context');
    }

    private function createSubmissionInContext(int $contextId): int
    {
        $submission = new Submission();
        $submission->setData('contextId', $contextId);
        $submission->setData('status', STATUS_QUEUED);
        $submission->setData('locale', 'en_US');
        return DAORegistry::getDAO('SubmissionDAO')->insertObject($submission);
    }

    private function buildRequestForContext(int $contextId, PKPHandler $handler)
    {
        $context = $this->getMockBuilder(Journal::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId'])
            ->getMock();
        $context->method('getId')->willReturn($contextId);

        $dispatcher = $this->getMockBuilder(Dispatcher::class)
            ->disableOriginalConstructor()
            ->setMethods(['handle404'])
            ->getMock();

        $router = $this->getMockBuilder(PKPComponentRouter::class)
            ->disableOriginalConstructor()
            ->setMethods(['getContext', 'getHandler', 'getRequestedOp'])
            ->getMock();
        $router->method('getContext')->willReturn($context);
        $router->method('getHandler')->willReturn($handler);
        $router->method('getRequestedOp')->willReturn('createTranslation');

        $request = $this->getMockBuilder(PKPRequest::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRouter', 'getContext', 'getDispatcher', 'getUser', 'getServerHost'])
            ->getMock();
        $request->method('getRouter')->willReturn($router);
        $request->method('getContext')->willReturn($context);
        $request->method('getDispatcher')->willReturn($dispatcher);
        $request->method('getUser')->willReturn(null);
        $request->method('getServerHost')->willReturn('localhost');

        return $request;
    }

    private function userRolesInjectionPolicy(array $roles): AuthorizationPolicy
    {
        $policy = $this->getMockBuilder(AuthorizationPolicy::class)
            ->setMethods(['effect'])
            ->getMock();
        $policy->method('effect')
            ->willReturnCallback(function () use ($policy, $roles) {
                $policy->addAuthorizedContextObject(ASSOC_TYPE_USER_ROLES, $roles);
                return AUTHORIZATION_PERMIT;
            });
        return $policy;
    }
}
