<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\PreferenceController;
use App\Interfaces\PreferenceServiceInterface;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private $preferenceService;
    private $controller;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->preferenceService = Mockery::mock(PreferenceServiceInterface::class);
        $this->controller = new PreferenceController($this->preferenceService);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_user_preferences()
    {
        $preferences = new \App\DTO\Preference\Preference(
            categories: [
                ['id' => 1, 'name' => 'Museum', 'slug' => 'museum']
            ],
            user: [
                'museum' => 5
            ]
        );

        $this->preferenceService
            ->shouldReceive('getPreferences')
            ->once()
            ->with($this->user)
            ->andReturn($preferences);

        $request = new Request();
        $request->setUserResolver(fn() => $this->user);

        $response = $this->controller->index($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }

    #[Test]
    public function it_updates_user_preferences()
    {
        $category = Category::factory()->create(['include_in_preferences' => true]);
        $requestData = [
            'preferences' => [
                $category->slug => 2 // Score between 0-2 as expected by the controller
            ]
        ];

        $dto = new \App\DTO\Preference\Preference(
            categories: [
                ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug]
            ],
            user: [
                $category->slug => 2
            ]
        );

        $this->preferenceService
            ->shouldReceive('updatePreferences')
            ->once()
            ->with(
                Mockery::on(fn($user) => $user->id === $this->user->id),
                $requestData['preferences']
            )
            ->andReturn($dto);

        $request = new Request($requestData);
        $request->setUserResolver(fn() => $this->user);
        $response = $this->controller->update($request);

        $responseData = json_decode($response->getContent(), true);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Preferences updated', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($category->slug, $responseData['data']['categories'][0]['slug']);
    }

    #[Test]
    public function it_validates_preferences_data()
    {
        $category = Category::factory()->create(['include_in_preferences' => true]);
        
        // Test with valid data
        $request = new Request([
            'preferences' => [
                $category->slug => 2 // Valid score (0-2)
            ]
        ]);
        $request->setUserResolver(fn() => $this->user);

        $dto = new \App\DTO\Preference\Preference(
            categories: [
                ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug]
            ],
            user: [
                $category->slug => 2
            ]
        );

        $this->preferenceService
            ->shouldReceive('updatePreferences')
            ->once()
            ->with($this->user, [$category->slug => 2])
            ->andReturn($dto);

        $response = $this->controller->update($request);
        $this->assertEquals(200, $response->getStatusCode());

        // Test with invalid data
        $invalidRequest = new Request([
            'preferences' => [
                $category->slug => 3 // Invalid score (>2)
            ]
        ]);
        $invalidRequest->setUserResolver(fn() => $this->user);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->update($invalidRequest);
    }

    #[Test]
    public function it_handles_update_errors()
    {
        $category = Category::factory()->create(['include_in_preferences' => true]);
        
        $requestData = [
            'preferences' => [
                $category->slug => 2
            ]
        ];

        $this->preferenceService
            ->shouldReceive('updatePreferences')
            ->once()
            ->andThrow(new \DomainException('Update failed'));

        $request = new Request($requestData);
        $request->setUserResolver(fn() => $this->user);

        $response = $this->controller->update($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Update failed', $responseData['error']);
    }
}
