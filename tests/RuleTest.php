<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Rule;
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

    /** @test */
    public function rule_can_evaluate_greater_than_comparison()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('GreaterThanRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 50
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['value' => 100]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['value' => 30]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['value' => 50]));
    }

    /** @test */
    public function rule_can_evaluate_less_than_comparison()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('LessThanRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '<',
            'right' => 50
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['value' => 30]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['value' => 100]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['value' => 50]));
    }

    /** @test */
    public function rule_can_evaluate_equality_comparison()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('EqualityRule', [
            'type' => 'comparison',
            'left' => 'status',
            'op' => '==',
            'right' => 'active'
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['status' => 'active']));
        $this->assertFalse($ruleEngine->evaluate($rule, ['status' => 'inactive']));
    }

    /** @test */
    public function rule_handles_missing_context_value()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('MissingValueRule', [
            'type' => 'comparison',
            'left' => 'missing_key',
            'op' => '>',
            'right' => 50
        ], ['type' => 'none']);

        $this->assertFalse($ruleEngine->evaluate($rule, ['other_key' => 100]));
    }

    /** @test */
    public function rule_can_evaluate_numeric_comparisons()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('NumericRule', [
            'type' => 'comparison',
            'left' => 'amount',
            'op' => '>',
            'right' => 1000
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['amount' => 1500.50]));
        $this->assertTrue($ruleEngine->evaluate($rule, ['amount' => 2000]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['amount' => 999.99]));
    }

    /** @test */
    public function rule_can_be_created_and_stored()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('StoredRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 100
        ], ['type' => 'action']);

        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals('StoredRule', $rule->name);
        $this->assertIsArray($rule->condition_ast);
        $this->assertIsArray($rule->action);
    }

    /** @test */
    public function rule_stores_condition_ast_correctly()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $conditionAst = [
            'type' => 'comparison',
            'left' => 'amount',
            'op' => '>',
            'right' => 500
        ];

        $rule = $ruleEngine->createRule('AstRule', $conditionAst, ['type' => 'none']);

        $this->assertEquals($conditionAst, $rule->condition_ast);
    }

    /** @test */
    public function rule_can_execute_callable_action()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('CallableRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 50
        ], ['type' => 'action', 'name' => 'test_action']);

        // Test that action is stored as array
        $this->assertIsArray($rule->action);
        $this->assertEquals('test_action', $rule->action['name']);

        // For callable actions, the engine can accept closures at runtime
        // Using stdClass to avoid model casts
        $testRule = new \stdClass();
        $testRule->action = function() {
            return 'action executed';
        };

        $result = $ruleEngine->executeAction($testRule, ['value' => 100]);

        $this->assertEquals('action executed', $result);
    }

    /** @test */
    public function rule_action_receives_context()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('ContextRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 50
        ], ['type' => 'action', 'name' => 'context_action']);

        // Test that action is stored as array
        $this->assertIsArray($rule->action);
        $this->assertEquals('context_action', $rule->action['name']);

        // For callable actions, the engine can accept closures at runtime
        // Using stdClass to avoid model casts
        $testRule = new \stdClass();
        $testRule->action = function($context) {
            return $context;
        };

        $context = ['value' => 100, 'name' => 'test'];
        $result = $ruleEngine->executeAction($testRule, $context);

        $this->assertEquals($context, $result);
    }

    /** @test */
    public function rule_handles_unknown_operator()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('UnknownOpRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => 'unknown',
            'right' => 50
        ], ['type' => 'none']);

        $this->assertFalse($ruleEngine->evaluate($rule, ['value' => 100]));
    }

    /** @test */
    public function multiple_rules_can_be_created_and_evaluated_independently()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('Rule1', [
            'type' => 'comparison',
            'left' => 'amount',
            'op' => '>',
            'right' => 100
        ], ['type' => 'none']);

        $rule2 = $ruleEngine->createRule('Rule2', [
            'type' => 'comparison',
            'left' => 'status',
            'op' => '==',
            'right' => 'active'
        ], ['type' => 'none']);

        $context1 = ['amount' => 150];
        $context2 = ['status' => 'active'];

        $this->assertTrue($ruleEngine->evaluate($rule1, $context1));
        $this->assertTrue($ruleEngine->evaluate($rule2, $context2));
    }

    /** @test */
    public function rule_can_handle_complex_context_data()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('ComplexContextRule', [
            'type' => 'comparison',
            'left' => 'order_total',
            'op' => '>',
            'right' => 1000
        ], ['type' => 'none']);

        $context = [
            'order_id' => 123,
            'order_total' => 1500.00,
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ],
            'items' => ['item1', 'item2', 'item3']
        ];

        $this->assertTrue($ruleEngine->evaluate($rule, $context));
    }

    /** @test */
    public function rule_evaluation_with_string_comparison()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('StringComparisonRule', [
            'type' => 'comparison',
            'left' => 'name',
            'op' => '==',
            'right' => 'John'
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['name' => 'John']));
        $this->assertFalse($ruleEngine->evaluate($rule, ['name' => 'Jane']));
    }

    /** @test */
    public function rule_can_evaluate_zero_values()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('ZeroValueRule', [
            'type' => 'comparison',
            'left' => 'count',
            'op' => '==',
            'right' => 0
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['count' => 0]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['count' => 1]));
    }

    /** @test */
    public function rule_can_evaluate_negative_numbers()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('NegativeNumberRule', [
            'type' => 'comparison',
            'left' => 'balance',
            'op' => '<',
            'right' => 0
        ], ['type' => 'none']);

        $this->assertTrue($ruleEngine->evaluate($rule, ['balance' => -50]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['balance' => 50]));
    }

    /** @test */
    public function rule_persists_in_database()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('PersistedRule', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 100
        ], ['type' => 'action']);

        $this->assertDatabaseHas('rules', [
            'name' => 'PersistedRule'
        ]);

        $retrievedRule = Rule::where('name', 'PersistedRule')->first();
        $this->assertNotNull($retrievedRule);
        $this->assertEquals($rule->id, $retrievedRule->id);
    }
}