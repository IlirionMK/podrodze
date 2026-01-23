<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test that the base controller can be instantiated through a concrete implementation.
     *
     * @return void
     */
    public function test_controller_can_be_instantiated()
    {
        // Create a concrete implementation of the abstract Controller class
        $controller = new class extends Controller {
            // Empty implementation for testing
        };

        $this->assertInstanceOf(Controller::class, $controller);
        $this->assertInstanceOf(\Illuminate\Routing\Controller::class, $controller);
    }

    /**
     * Test that the controller uses the expected traits.
     *
     * @return void
     */
    public function test_controller_uses_expected_traits()
    {
        $traits = class_uses(Controller::class);
        
        $this->assertContains('Illuminate\Foundation\Auth\Access\AuthorizesRequests', $traits);
        $this->assertContains('Illuminate\Foundation\Bus\DispatchesJobs', $traits);
        $this->assertContains('Illuminate\Foundation\Validation\ValidatesRequests', $traits);
    }

    /**
     * Test that the OpenAPI annotations are present.
     *
     * @return void
     */
    public function test_openapi_annotations_are_present()
    {
        $reflection = new \ReflectionClass(Controller::class);
        $docComment = $reflection->getDocComment();

        $this->assertStringContainsString('@OA\Info', $docComment);
        $this->assertStringContainsString('@OA\Server', $docComment);
        $this->assertStringContainsString('Podrodze API', $docComment);
        $this->assertStringContainsString('http://localhost:8081/api/v1', $docComment);
    }
}
