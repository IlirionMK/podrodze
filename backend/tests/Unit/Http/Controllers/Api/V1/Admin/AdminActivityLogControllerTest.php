<?php

namespace Tests\Unit\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\AdminActivityLogController;
use App\Models\ActivityLog;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminActivityLogControllerTest extends TestCase
{
    private $adminActivityLogService;
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminActivityLogService = Mockery::mock(AdminActivityLogService::class);
        $this->controller = new AdminActivityLogController($this->adminActivityLogService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_paginated_activity_logs()
    {
        // Create a mock paginator
        $paginator = new LengthAwarePaginator(
            [new ActivityLog()], // items
            1, // total
            20, // per page
            1  // current page
        );

        // Set up the service mock
        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return is_array($filters) &&
                       isset($filters['search']) &&
                       $filters['search'] === 'test';
            }), 20)
            ->andReturn($paginator);

        // Create a mock request
        $request = new Request(['search' => 'test']);

        // Call the controller method
        $response = $this->controller->index($request);

        // Assert the response
        $this->assertCount(1, $response->resource);
    }

    #[Test]
    public function it_handles_empty_search_parameter()
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1);

        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return is_array($filters) &&
                       isset($filters['search']) &&
                       $filters['search'] === '';
            }), 20)
            ->andReturn($paginator);

        $request = new Request();
        $response = $this->controller->index($request);

        $this->assertCount(0, $response->resource);
    }

    #[Test]
    public function it_handles_custom_per_page_parameter()
    {
        $paginator = new LengthAwarePaginator([], 0, 50, 1);

        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return is_array($filters);
            }), 50)
            ->andReturn($paginator);

        $request = new Request(['per_page' => '50']);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertCount(0, $response->resource);
    }

    #[Test]
    public function it_validates_per_page_parameter()
    {
        // Test minimum value (1)
        $minPaginator = new LengthAwarePaginator([], 0, 1, 1);
        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return is_array($filters);
            }), 1)
            ->andReturn($minPaginator);

        $request = new Request(['per_page' => '0']);
        $response = $this->controller->index($request);
        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertCount(0, $response->resource);

        // Test maximum value (200)
        $maxPaginator = new LengthAwarePaginator([], 0, 200, 1);
        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return is_array($filters);
            }), 200)
            ->andReturn($maxPaginator);

        $request = new Request(['per_page' => '300']);
        $response = $this->controller->index($request);
        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertCount(0, $response->resource);
    }

    #[Test]
    public function it_filters_by_user_id()
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1);

        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) {
                return isset($filters['user_id']) && $filters['user_id'] === '123';
            }), 20)
            ->andReturn($paginator);

        $request = new Request(['user_id' => '123']);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertCount(0, $response->resource);
    }

    #[Test]
    public function it_filters_by_date_range()
    {
        $paginator = new LengthAwarePaginator([], 0, 20, 1);
        $fromDate = '2023-01-01';
        $toDate = '2023-12-31';

        $this->adminActivityLogService
            ->shouldReceive('paginate')
            ->once()
            ->with(Mockery::on(function ($filters) use ($fromDate, $toDate) {
                return isset($filters['from']) && $filters['from'] === $fromDate &&
                       isset($filters['to']) && $filters['to'] === $toDate;
            }), 20)
            ->andReturn($paginator);

        $request = new Request([
            'from' => $fromDate,
            'to' => $toDate
        ]);
        $response = $this->controller->index($request);

        $this->assertInstanceOf(\Illuminate\Http\Resources\Json\ResourceCollection::class, $response);
        $this->assertCount(0, $response->resource);
    }
}
