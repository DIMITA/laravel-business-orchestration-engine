<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class RuleTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_rule_engine()
    {
        $rule = app('business-orchestration')->rule();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\RuleEngine::class, $rule);
    }

    /** @test */
    public function it_can_evaluate_rule()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('TestRule', [
            'type' => 'comparison',
            'left' => 'amount',
            'op' => '>',
            'right' => 100
        ], ['type' => 'callback', 'callback' => function() { return 'executed'; }]);

        $this->assertTrue($ruleEngine->evaluate($rule, ['amount' => 150]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['amount' => 50]));
    }
}