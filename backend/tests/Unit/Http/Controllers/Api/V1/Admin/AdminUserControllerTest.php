<?php

namespace Tests\Unit\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Requests\Admin\UpdateUserBanRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    private $adminUserService;
    private $controller;
    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUserService = Mockery::mock(AdminUserService::class);
        $this->controller = new AdminUserController($this->adminUserService);
        $this->user = new User();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_paginated_users()
    {
        $paginator = new LengthAwarePaginator(
            [new User()],
            1,
            15,
            1
        );

        $this->adminUserService
            ->shouldReceive('paginateUsers')
            ->once()
            ->with('test', 15)
            ->andReturn($paginator);

        $request = new Request(['search' => 'test']);
        $response = $this->controller->index($request);

        $this->assertCount(1, $response->resource);
        $this->assertEquals(1, $response->resource->total());
        $this->assertEquals(15, $response->resource->perPage());
    }

    #[Test]
    public function it_sets_user_role()
    {
        $targetUser = new User(['id' => 2]);
        $actor = new User(['id' => 1]);

        $this->adminUserService
            ->shouldReceive('setRole')
            ->once()
            ->with(
                $targetUser,
                'moderator',
                $actor
            )
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'data' => [
                        'id' => 2,
                        'role' => 'moderator'
                    ]
                ]
            ]);

        // Create a proper request with validation
        $request = new UpdateUserRoleRequest();
        $request->merge(['role' => 'moderator']);
        $request->setUserResolver(fn() => $actor);
        
        // Mock the validated method
        $request = \Mockery::mock($request);
        $request->shouldReceive('validated')->andReturn(['role' => 'moderator']);

        // Call the controller method
        $response = $this->controller->setRole($request, $targetUser);

        // Assert the response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('moderator', $response->getData()->data->role);
    }

    #[Test]
    public function it_prevents_self_role_change()
    {
        $user = new User(['id' => 1, 'role' => 'admin']);

        $this->adminUserService
            ->shouldReceive('setRole')
            ->once()
            ->with(
                $user,
                'user',
                $user
            )
            ->andReturn([
                'ok' => false,
                'status' => 422,
                'payload' => ['message' => 'You cannot remove your own admin role.']
            ]);

        // Create a proper request with validation
        $request = new UpdateUserRoleRequest();
        $request->merge(['role' => 'user']);
        $request->setUserResolver(fn() => $user);
        
        // Mock the validated method
        $request = \Mockery::mock($request);
        $request->shouldReceive('validated')->andReturn(['role' => 'user']);

        // Call the controller method
        $response = $this->controller->setRole($request, $user);

        // Assert the response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('You cannot remove your own admin role.', $response->getData()->message);
    }

    #[Test]
    public function it_bans_user()
    {
        $targetUser = new User(['id' => 2]);
        $actor = new User(['id' => 1]);

        $this->adminUserService
            ->shouldReceive('setBanned')
            ->once()
            ->with(
                $targetUser,
                true,
                $actor
            )
            ->andReturn([
                'ok' => true,
                'status' => 200,
                'payload' => [
                    'data' => [
                        'id' => 2,
                        'banned' => true
                    ]
                ]
            ]);

        // Create a proper request with validation
        $request = new UpdateUserBanRequest();
        $request->merge(['banned' => true]);
        $request->setUserResolver(fn() => $actor);
        
        // Mock the validated method
        $request = \Mockery::mock($request);
        $request->shouldReceive('validated')->andReturn(['banned' => true]);

        // Call the controller method
        $response = $this->controller->setBanned($request, $targetUser);

        // Assert the response
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getData()->data->banned);
    }

    #[Test]
    public function it_prevents_self_ban()
    {
        $user = new User(['id' => 1]);

        $this->adminUserService
            ->shouldReceive('setBanned')
            ->once()
            ->with(
                $user,
                true,
                $user
            )
            ->andReturn([
                'ok' => false,
                'status' => 422,
                'payload' => ['message' => 'You cannot ban your own account.']
            ]);

        // Create a proper request with validation
        // Create a proper request with validation
        $request = new UpdateUserBanRequest();
        $request->merge(['banned' => true]);
        $request->setUserResolver(fn() => $user);
        
        // Mock the validated method
        $request = \Mockery::mock($request);
        $request->shouldReceive('validated')->andReturn(['banned' => true]);

        // Call the controller method
        $response = $this->controller->setBanned($request, $user);

        // Assert the response
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('You cannot ban your own account.', $response->getData()->message);
    }

    #[Test]
    public function it_handles_custom_per_page_parameter()
    {
        $paginator = new LengthAwarePaginator([], 0, 50, 1);

        $this->adminUserService
            ->shouldReceive('paginateUsers')
            ->once()
            ->with('', 50)
            ->andReturn($paginator);

        $request = new Request(['per_page' => '50']);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertEquals(50, $response->resource->perPage());
    }

    #[Test]
    public function it_validates_per_page_parameter()
    {
        // Test minimum value (1)
        $minPaginator = new LengthAwarePaginator([], 0, 1, 1);
        $this->adminUserService
            ->shouldReceive('paginateUsers')
            ->once()
            ->with('', 1)
            ->andReturn($minPaginator);

        $request = new Request(['per_page' => '0']);
        $response = $this->controller->index($request);
        $this->assertEquals(1, $response->resource->perPage());

        // Test maximum value (100)
        $maxPaginator = new LengthAwarePaginator([], 0, 100, 1);
        $this->adminUserService
            ->shouldReceive('paginateUsers')
            ->once()
            ->with('', 100)
            ->andReturn($maxPaginator);

        $request = new Request(['per_page' => '200']);
        $response = $this->controller->index($request);
        $this->assertEquals(100, $response->resource->perPage());
    }
}
