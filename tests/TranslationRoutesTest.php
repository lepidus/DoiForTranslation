<?php

use APP\plugins\generic\doiForTranslation\api\v1\doiForTranslation\DoiForTranslationHandler;
use PHPUnit\Framework\TestCase;
use PKP\core\PKPBaseController;
use PKP\handler\APIHandler;
use PKP\plugins\interfaces\HasAuthorizationPolicy;
use PKP\security\Role;

class TranslationRoutesTest extends TestCase
{
    public function testRoutesUseSubmissionAuthorizationWithoutReferenceWarnings(): void
    {
        $routes = $this->getRegisteredRoutes();
        $this->assertSame('{submissionId}/doiForTranslation', $routes[0][1]);
        $this->assertSame('{submissionId}/doiForTranslation/create', $routes[1][1]);
        foreach ($routes as $route) {
            $this->assertSame([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $route[4]);
            $this->assertInstanceOf(HasAuthorizationPolicy::class, $route[5]);
        }
    }
    public function testRoutesAreNotAddedToSubmissionFilesEndpoints(): void
    {
        $handler = new DoiForTranslationHandler();
        $api = $this->getMockBuilder(APIHandler::class)->disableOriginalConstructor()->onlyMethods(['addRoute'])->getMock();
        $api->expects($this->never())->method('addRoute');

        $handler->registerRoute('APIHandler::endpoints::submissions', $this->createController('submissions/{submissionId}/files'), $api);
    }

    public function testSiteAdministratorCanPassBothRouteRoleChecksWithoutJournalRoles(): void
    {
        $this->assertRoleResponse(Role::ROLE_ID_SITE_ADMIN, 200);
    }

    public function testAuthorsAssistantsAndReviewersCannotPassRouteRoleChecks(): void
    {
        foreach ([Role::ROLE_ID_AUTHOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER] as $role) {
            $this->assertRoleResponse($role, 401);
        }
    }

    private function assertRoleResponse(int $role, int $status): void
    {
        $user = $this->createMock(\PKP\user\User::class);
        $user->method('hasRole')->willReturnCallback(fn ($requestedRole, $contextId) =>
            $requestedRole === $role && ($role !== Role::ROLE_ID_SITE_ADMIN || $contextId === \APP\core\Application::SITE_CONTEXT_ID));
        $context = new \APP\journal\Journal();
        $context->setData('id', 1);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('context', $context);
        foreach ($this->getRegisteredRoutes() as $route) {
            $response = (new \PKP\middleware\HasRoles())->handle(
                $request,
                fn () => response()->json(['allowed' => true]),
                implode('|', $route[4])
            );
            $this->assertSame($status, $response->getStatusCode());
        }
    }

    private function getRegisteredRoutes(): array
    {
        $handler = new DoiForTranslationHandler();
        $api = $this->getMockBuilder(APIHandler::class)->disableOriginalConstructor()->onlyMethods(['addRoute'])->getMock();
        $controller = $this->createController('submissions');
        $routes = [];
        $api->expects($this->exactly(2))->method('addRoute')->willReturnCallback(
            function (...$args) use (&$routes) { $routes[] = $args; }
        );
        call_user_func_array($handler->registerRoute(...), ['APIHandler::endpoints::submissions', $controller, $api]);
        return $routes;
    }

    private function createController(string $handlerPath): PKPBaseController
    {
        $controller = $this->getMockBuilder(PKPBaseController::class)->disableOriginalConstructor()->getMock();
        $controller->method('getHandlerPath')->willReturn($handlerPath);
        return $controller;
    }

}
