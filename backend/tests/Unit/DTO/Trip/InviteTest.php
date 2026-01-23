<?php

namespace Tests\Unit\DTO\Trip;

use App\DTO\Trip\Invite;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InviteTest extends TestCase
{
    public function test_it_creates_invite()
    {
        $owner = $this->createMock(User::class);
        $owner->id = 1;
        $owner->name = 'Owner';
        $owner->email = 'owner@example.com';
        
        $invite = new Invite(
            trip_id: 1,
            name: 'Test Trip',
            start_date: '2025-01-01',
            end_date: '2025-01-07',
            role: 'member',
            status: 'pending',
            owner: $owner
        );

        $this->assertEquals(1, $invite->trip_id);
        $this->assertEquals('Test Trip', $invite->name);
        $this->assertEquals('2025-01-01', $invite->start_date);
        $this->assertEquals('2025-01-07', $invite->end_date);
        $this->assertEquals('member', $invite->role);
        $this->assertEquals('pending', $invite->status);
        $this->assertSame($owner, $invite->owner);
    }

    public function test_it_creates_from_pivot()
    {
        // Create simple test objects using anonymous classes that extend the models
        $owner = new class extends User {
            public $id = 1;
            public $name = 'Owner';
            public $email = 'owner@example.com';
        };
        
        $user = new class extends User {
            public $id = 2;
            public $name = 'Member';
        };
        
        $trip = new class extends Trip {
            public $id = 1;
            public $name = 'Test Trip';
            public $start_date = '2025-01-01';
            public $end_date = '2025-01-07';
            public $owner;
            
            public function __construct() {
                parent::__construct();
                $this->owner = new class extends User {
                    public $id = 1;
                    public $name = 'Owner';
                    public $email = 'owner@example.com';
                };
            }
            
            public function members() {
                return new class {
                    public function where($field, $value) {
                        return $this;
                    }
                    
                    public function first() {
                        return (object) [
                            'pivot' => (object) [
                                'role' => 'member',
                                'status' => 'pending'
                            ]
                        ];
                    }
                };
            }
        };
        
        // Create the invite from pivot
        $invite = Invite::fromPivot($trip, $user);
        
        // Assertions
        $this->assertEquals(1, $invite->trip_id);
        $this->assertEquals('Test Trip', $invite->name);
        $this->assertEquals('2025-01-01', $invite->start_date);
        $this->assertEquals('2025-01-07', $invite->end_date);
        $this->assertEquals('member', $invite->role);
        $this->assertEquals('pending', $invite->status);
    }

    public function test_it_creates_from_model()
    {
        $trip = new class extends Trip {
            public $id = 1;
            public $name = 'Test Trip';
            public $start_date = '2025-01-01';
            public $end_date = '2025-01-07';
            public $owner;
            public $pivot;
            
            public function __construct() {
                parent::__construct();
                $this->owner = new class extends User {
                    public $id = 1;
                    public $name = 'Owner';
                    public $email = 'owner@example.com';
                };
                $this->pivot = (object) [
                    'role' => 'member',
                    'status' => 'pending'
                ];
            }
        };
        
        // Create invite from model
        $invite = Invite::fromModel($trip);
        
        // Assertions
        $this->assertEquals(1, $invite->trip_id);
        $this->assertEquals('Test Trip', $invite->name);
        $this->assertEquals('2025-01-01', $invite->start_date);
        $this->assertEquals('2025-01-07', $invite->end_date);
        $this->assertEquals('member', $invite->role);
        $this->assertEquals('pending', $invite->status);
    }

    public function test_it_handles_missing_pivot()
    {
        $user = new class extends User {
            public $id = 2;
            public $name = 'Member';
        };
        
        $trip = new class extends Trip {
            public $id = 1;
            public $name = 'Test Trip';
            public $start_date = '2025-01-01';
            public $end_date = '2025-01-07';
            public $owner;
            
            public function __construct() {
                parent::__construct();
                $this->owner = new class extends User {
                    public $id = 1;
                    public $name = 'Owner';
                    public $email = 'owner@example.com';
                };
            }
            
            public function members() {
                return new class {
                    public function where($field, $value) {
                        return $this;
                    }
                    
                    public function first() {
                        return null;
                    }
                };
            }
        };
        
        // Create invite from pivot
        $invite = Invite::fromPivot($trip, $user);
        
        // Assertions - should use defaults for role and status
        $this->assertEquals('member', $invite->role);
        $this->assertEquals('pending', $invite->status);
    }

    public function test_it_serializes_to_json()
    {
        $owner = new class extends User {
            public $id = 1;
            public $name = 'Owner';
            public $email = 'owner@example.com';
        };
        
        $invite = new Invite(
            trip_id: 1,
            name: 'Test Trip',
            start_date: '2025-01-01',
            end_date: '2025-01-07',
            role: 'member',
            status: 'pending',
            owner: $owner
        );

        $expected = [
            'trip_id' => 1,
            'name' => 'Test Trip',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-07',
            'role' => 'member',
            'status' => 'pending',
            'owner' => [
                'id' => 1,
                'name' => 'Owner',
                'email' => 'owner@example.com',
            ],
        ];

        $this->assertEquals($expected, $invite->jsonSerialize());
    }
}
