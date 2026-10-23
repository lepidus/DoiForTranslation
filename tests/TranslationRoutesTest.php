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
        $handler = new DoiForTranslationHandler();
        $api = $this->getMockBuilder(APIHandler::class)->disableOriginalConstructor()->onlyMethods(['addRoute'])->getMock();
        $controller = $this->getMockBuilder(PKPBaseController::class)->disableOriginalConstructor()->getMock();
        $routes = [];
        $api->expects($this->exactly(2))->method('addRoute')->willReturnCallback(
            function (...$args) use (&$routes) { $routes[] = $args; }
        );
        call_user_func_array($handler->registerRoute(...), ['APIHandler::endpoints::submissions', $controller, $api]);
        $this->assertSame('{submissionId}/doiForTranslation', $routes[0][1]);
        $this->assertSame('{submissionId}/doiForTranslation/create', $routes[1][1]);
        foreach ($routes as $route) {
            $this->assertSame([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $route[4]);
            $this->assertInstanceOf(HasAuthorizationPolicy::class, $route[5]);
        }
    }
}
