<?php

namespace Tests\Unit\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\TripUserController;
use App\Http\Requests\InviteTripRequest;
use App\Interfaces\TripInterface;
use App\Models\Trip;
use App\Models\User;
use App\DTO\Trip\Invite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TripUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private $tripService;
    private $controller;
    private $owner;
    private $member;
    private $trip;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock authorization to bypass all checks
        $this->mock(\Illuminate\Foundation\Auth\Access\Gate::class, function ($mock) {
            $response = Mockery::mock(\Illuminate\Auth\Access\Response::class);
            $response->shouldReceive('authorize')->andReturn(true);
            $response->shouldReceive('denied')->andReturn(false);
            $response->shouldReceive('message')->andReturn(null);
            $response->shouldReceive('code')->andReturn(null);

            $mock->shouldReceive('authorize')->andReturn($response);
            $mock->shouldReceive('check')->andReturn(true);
            $mock->shouldReceive('allows')->andReturn(true);
            $mock->shouldReceive('denies')->andReturn(false);
            $mock->shouldReceive('forUser')->andReturnSelf();
            $mock->shouldReceive('policy')->andReturnSelf();
            $mock->shouldReceive('before')->andReturnSelf();
        });

        $this->tripService = Mockery::mock(TripInterface::class);

        // Create a partial mock of the controller to bypass authorization
        $this->controller = Mockery::mock(TripUserController::class, [$this->tripService])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Mock the authorize method to do nothing
        $this->controller->shouldReceive('authorize')->andReturnNull();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->trip = Trip::factory()->create(['owner_id' => $this->owner->id]);

        $this->actingAs($this->owner);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_invites_a_user_to_trip()
    {
        $inviteData = [
            'email' => $this->member->email,
            'role' => 'member',
            'message' => 'Join my trip!'
        ];

        $request = Mockery::mock(InviteTripRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($inviteData);
        $request->shouldReceive('user')->once()->andReturn($this->owner);

        $invite = new Invite(
            trip_id: $this->trip->id,
            name: $this->trip->name,
            start_date: $this->trip->start_date,
            end_date: $this->trip->end_date,
            role: $inviteData['role'],
            status: 'pending',
            owner: $this->owner
        );

        $this->tripService
            ->shouldReceive('inviteUser')
            ->once()
            ->with(
                $this->trip,
                $this->owner,
                $inviteData
            )
            ->andReturn($invite);

        $response = $this->controller->invite($request, $this->trip);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Invitation sent successfully.', $responseData['message']);
    }

    #[Test]
    public function it_removes_a_member_from_trip()
    {
        $this->trip->members()->attach($this->member->id, ['role' => 'member']);

        $this->tripService
            ->shouldReceive('removeMember')
            ->once()
            ->with($this->trip, $this->member, $this->owner)
            ->andReturn(true);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->owner);

        $response = $this->controller->destroy($request, $this->trip, $this->member);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Member removed.', $responseData['message']);
    }

    #[Test]
    public function it_updates_member_role()
    {
        $this->trip->members()->attach($this->member->id, ['role' => 'member']);

        $updateData = ['role' => 'editor'];
        $request = new Request($updateData);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->owner);
        $request->shouldReceive('validate')->once()->andReturn(['role' => 'editor']);

        $this->tripService
            ->shouldReceive('updateMemberRole')
            ->once()
            ->with($this->trip, $this->member, 'editor', $this->owner)
            ->andReturn(true);

        $response = $this->controller->update($request, $this->trip, $this->member);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Role updated.', $responseData['message']);
    }

    #[Test]
    public function it_lists_trip_members()
    {
        $request = new Request();

        $members = collect([
            $this->owner,
            $this->member
        ]);

        $this->tripService
            ->shouldReceive('listMembers')
            ->once()
            ->with($this->trip)
            ->andReturn($members);

        $response = $this->controller->index($request, $this->trip);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_invalid_role_update()
    {
        $this->trip->members()->attach($this->member->id, ['role' => 'member']);

        $request = Mockery::mock(Request::class);

        $validationException = \Illuminate\Validation\ValidationException::withMessages(['role' => ['Invalid role']]);
        $validationException->response = new \Illuminate\Http\JsonResponse(['errors' => ['role' => ['Invalid role']]], 422);
        
        $request->shouldReceive('validate')->once()->andThrow($validationException);

        try {
            $response = $this->controller->update($request, $this->trip, $this->member);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $response = $e->response;
        }

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $responseData);
    }

    #[Test]
    public function it_accepts_trip_invitation()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->member);

        $this->tripService
            ->shouldReceive('acceptInvite')
            ->once()
            ->with($this->trip, $this->member)
            ->andReturn();

        $response = $this->controller->accept($request, $this->trip);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Invitation accepted.', $responseData['message']);
    }

    #[Test]
    public function it_handles_accept_invitation_error()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->member);

        $this->tripService
            ->shouldReceive('acceptInvite')
            ->once()
            ->with($this->trip, $this->member)
            ->andThrow(new \DomainException('Invitation already processed'));

        $response = $this->controller->accept($request, $this->trip);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invitation already processed', $responseData['error']);
    }

    #[Test]
    public function it_declines_trip_invitation()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->member);

        $this->tripService
            ->shouldReceive('declineInvite')
            ->once()
            ->with($this->trip, $this->member)
            ->andReturn();

        $response = $this->controller->decline($request, $this->trip);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Invitation declined.', $responseData['message']);
    }

    #[Test]
    public function it_handles_decline_invitation_error()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->member);

        $this->tripService
            ->shouldReceive('declineInvite')
            ->once()
            ->with($this->trip, $this->member)
            ->andThrow(new \DomainException('Invitation not found'));

        $response = $this->controller->decline($request, $this->trip);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invitation not found', $responseData['error']);
    }

    #[Test]
    public function it_lists_user_invites()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->member);

        $expectedInvites = new Collection([
            new Invite(
                trip_id: 1,
                name: 'Weekend Trip',
                start_date: '2025-07-01',
                end_date: '2025-07-15',
                role: 'member',
                status: 'pending',
                owner: $this->owner
            ),
            new Invite(
                trip_id: 2,
                name: 'City Tour',
                start_date: '2025-08-01',
                end_date: '2025-08-05',
                role: 'member',
                status: 'pending',
                owner: $this->owner
            )
        ]);

        $this->tripService
            ->shouldReceive('listUserInvites')
            ->once()
            ->with($this->member)
            ->andReturn($expectedInvites);

        $response = $this->controller->myInvites($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals('Weekend Trip', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_lists_sent_invites()
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('user')->once()->andReturn($this->owner);

        $expectedInvites = new Collection([
            new Invite(
                trip_id: 3,
                name: 'Friend Trip',
                start_date: '2025-09-01',
                end_date: '2025-09-03',
                role: 'member',
                status: 'pending',
                owner: $this->owner
            ),
            new Invite(
                trip_id: 4,
                name: 'Colleague Trip',
                start_date: '2025-10-01',
                end_date: '2025-10-02',
                role: 'member',
                status: 'accepted',
                owner: $this->owner
            )
        ]);

        $this->tripService
            ->shouldReceive('listSentInvites')
            ->once()
            ->with($this->owner)
            ->andReturn($expectedInvites);

        $response = $this->controller->sentInvites($request);
        $responseData = json_decode($response->getContent(), true);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', $responseData);
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals('Friend Trip', $responseData['data'][0]['name']);
    }
}
