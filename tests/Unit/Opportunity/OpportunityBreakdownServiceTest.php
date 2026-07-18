<?php

namespace Tests\Unit\Opportunity;

use Domains\Opportunity\Data\OpportunityBreakdownData;
use Domains\Opportunity\Services\OpportunityBreakdownService;
use Domains\Trend\Models\Trend;
use Tests\TestCase;

class OpportunityBreakdownServiceTest extends TestCase
{
    public function test_it_returns_breakdown_data(): void
    {
        $trend = Trend::factory()->make([
            'score' => 100,
            'growth_rate' => 50,
            'velocity' => 20,
            'authority_score' => 80,
        ]);

        $service =
            app(OpportunityBreakdownService::class);

        $breakdown =
            $service->calculate($trend);

        $this->assertInstanceOf(
            OpportunityBreakdownData::class,
            $breakdown
        );

        $this->assertGreaterThan(
            0,
            $breakdown->opportunityScore
        );
    }

    public function test_it_calculates_contributions_correctly(): void
    {
        $trend = Trend::factory()->make([
            'score' => 100,
            'growth_rate' => 0,
            'velocity' => 0,
            'authority_score' => 50,
        ]);

        $service =
            app(OpportunityBreakdownService::class);

        $breakdown =
            $service->calculate($trend);

        $this->assertEquals(
            40.0,
            $breakdown->trendContribution
        );

        $this->assertEquals(
            5.0,
            $breakdown->authorityContribution
        );

        $this->assertGreaterThan(
            0,
            $breakdown->opportunityScore
        );
    }

    public function test_opportunity_score_equals_sum_of_contributions(): void
    {
        $trend = Trend::factory()->make([
            'score' => 120,
            'growth_rate' => 40,
            'velocity' => 10,
            'authority_score' => 70,
        ]);

        $service =
            app(OpportunityBreakdownService::class);

        $breakdown =
            $service->calculate($trend);

        $expected =
            round(
                $breakdown->trendContribution
                + $breakdown->momentumContribution
                + $breakdown->authorityContribution,
                2
            );

        $this->assertEquals(
            $expected,
            $breakdown->opportunityScore
        );
    }
}
