<?php

namespace Tests\Unit\Interfaces\Ai;

use App\Interfaces\Ai\AiPlaceReasonerInterface;
use Mockery;
use Tests\TestCase;

class AiPlaceReasonerInterfaceTest extends TestCase
{
    private AiPlaceReasonerInterface $reasoner;
    private array $candidates;
    private array $preferences;
    private array $context;
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->reasoner = Mockery::mock(AiPlaceReasonerInterface::class);
        
        $this->candidates = [
            [
                'source' => 'test_source',
                'name' => 'Test Place',
                'category' => 'Museum',
                'rating' => 4.5,
                'lat' => 52.2297,
                'lon' => 21.0122,
                'distance_m' => 1000
            ]
        ];
        
        $this->preferences = ['preference1' => 'value1'];
        $this->context = ['context1' => 'value1'];
        $this->locale = 'en';
    }

    public function test_rank_and_explain_returns_expected_structure()
    {
        $expectedResult = [
            [
                'score' => 0.95,
                'reason' => 'This place matches your preferences',
                'estimated_visit_minutes' => 120
            ]
        ];
        
        $this->reasoner->shouldReceive('rankAndExplain')
            ->once()
            ->with(
                $this->candidates,
                $this->preferences,
                $this->context,
                $this->locale
            )
            ->andReturn($expectedResult);
            
        $result = $this->reasoner->rankAndExplain(
            $this->candidates,
            $this->preferences,
            $this->context,
            $this->locale
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey('score', $result[0]);
        $this->assertArrayHasKey('reason', $result[0]);
        $this->assertArrayHasKey('estimated_visit_minutes', $result[0]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
