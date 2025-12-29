<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Rule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class RuleEngineAdvancedTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_create_rule_with_fluent_builder()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->rule('FluentTest')
            ->when('amount', '>', 1000)
            ->then(function($ctx) {
                return ['discount' => 10];
            });

        $this->assertInstanceOf(Rule::class, $rule);
        $this->assertEquals('FluentTest', $rule->name);
    }

    /** @test */
    public function fluent_builder_evaluates_correctly()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->rule('DiscountRule')
            ->when('total', '>', 500)
            ->then(fn($ctx) => ['discount' => 15]);

        $this->assertTrue($ruleEngine->evaluate($rule, ['total' => 750]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['total' => 300]));
    }

    /** @test */
    public function fluent_builder_supports_multiple_conditions()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->rule('MultiCondition')
            ->when('amount', '>', 100)
            ->and('customer_type', '==', 'premium')
            ->then(fn($ctx) => ['approved' => true]);

        $this->assertTrue($ruleEngine->evaluate($rule, ['amount' => 200, 'customer_type' => 'premium']));
        $this->assertFalse($ruleEngine->evaluate($rule, ['amount' => 200, 'customer_type' => 'regular']));
        $this->assertFalse($ruleEngine->evaluate($rule, ['amount' => 50, 'customer_type' => 'premium']));
    }

    /** @test */
    public function it_supports_extended_operators()
    {
        $ruleEngine = app('business-orchestration')->rule();

        // Test ===
        $rule1 = $ruleEngine->createRule('StrictEqual', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'value'],
            'op' => '===',
            'right' => ['type' => 'literal', 'value' => 5]
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule1, ['value' => 5]));
        $this->assertFalse($ruleEngine->evaluate($rule1, ['value' => '5']));

        // Test in
        $rule2 = $ruleEngine->createRule('InOperator', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'role'],
            'op' => 'in',
            'right' => ['type' => 'literal', 'value' => ['admin', 'moderator']]
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule2, ['role' => 'admin']));
        $this->assertTrue($ruleEngine->evaluate($rule2, ['role' => 'moderator']));
        $this->assertFalse($ruleEngine->evaluate($rule2, ['role' => 'user']));
    }

    /** @test */
    public function it_supports_string_operators()
    {
        $ruleEngine = app('business-orchestration')->rule();

        // Test starts_with
        $rule1 = $ruleEngine->createRule('StartsWithOperator', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'email'],
            'op' => 'starts_with',
            'right' => ['type' => 'literal', 'value' => 'admin@']
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule1, ['email' => 'admin@example.com']));
        $this->assertFalse($ruleEngine->evaluate($rule1, ['email' => 'user@example.com']));

        // Test ends_with
        $rule2 = $ruleEngine->createRule('EndsWithOperator', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'email'],
            'op' => 'ends_with',
            'right' => ['type' => 'literal', 'value' => '@company.com']
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule2, ['email' => 'john@company.com']));
        $this->assertFalse($ruleEngine->evaluate($rule2, ['email' => 'john@other.com']));
    }

    /** @test */
    public function it_can_register_and_use_custom_operators()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $ruleEngine->registerOperator('divisible_by', function($left, $right, $context) {
            return $left % $right === 0;
        });

        $rule = $ruleEngine->createRule('DivisibleRule', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'quantity'],
            'op' => 'divisible_by',
            'right' => ['type' => 'literal', 'value' => 12]
        ], ['action' => 'bulk_discount'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule, ['quantity' => 24]));
        $this->assertTrue($ruleEngine->evaluate($rule, ['quantity' => 36]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['quantity' => 25]));
    }

    /** @test */
    public function it_can_create_and_evaluate_rule_groups()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('Rule1', [
            'type' => 'comparison',
            'left' => 'value1',
            'op' => '>',
            'right' => 50
        ], ['action' => 'test'], 0);

        $rule2 = $ruleEngine->createRule('Rule2', [
            'type' => 'comparison',
            'left' => 'value2',
            'op' => '==',
            'right' => 'active'
        ], ['action' => 'test'], 0);

        $ruleEngine->addToGroup('test_group', $rule1->id);
        $ruleEngine->addToGroup('test_group', $rule2->id);

        // Test AND logic
        $passedAnd = $ruleEngine->evaluateGroup('test_group', ['value1' => 100, 'value2' => 'active'], 'AND');
        $this->assertTrue($passedAnd);

        $failedAnd = $ruleEngine->evaluateGroup('test_group', ['value1' => 100, 'value2' => 'inactive'], 'AND');
        $this->assertFalse($failedAnd);

        // Test OR logic
        $passedOr = $ruleEngine->evaluateGroup('test_group', ['value1' => 100, 'value2' => 'inactive'], 'OR');
        $this->assertTrue($passedOr);
    }

    /** @test */
    public function it_can_get_rules_in_a_group()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('Rule1', ['type' => 'comparison', 'left' => 'a', 'op' => '>', 'right' => 1], ['action' => 'test'], 0);
        $rule2 = $ruleEngine->createRule('Rule2', ['type' => 'comparison', 'left' => 'b', 'op' => '>', 'right' => 2], ['action' => 'test'], 0);

        $ruleEngine->addToGroup('my_group', $rule1->id);
        $ruleEngine->addToGroup('my_group', $rule2->id);

        $group = $ruleEngine->getGroup('my_group');

        $this->assertCount(2, $group);
        $this->assertContains($rule1->id, $group);
        $this->assertContains($rule2->id, $group);
    }

    /** @test */
    public function it_evaluates_rules_by_priority()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('LowPriority', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 10
        ], ['action' => 'test'], 5);

        $rule2 = $ruleEngine->createRule('HighPriority', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 10
        ], ['action' => 'test'], 100);

        $rule3 = $ruleEngine->createRule('MediumPriority', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 10
        ], ['action' => 'test'], 50);

        $results = $ruleEngine->evaluateBatch([$rule1->id, $rule2->id, $rule3->id], ['value' => 20]);

        $priorities = array_column($results, 'priority');
        $this->assertEquals([100, 50, 5], $priorities);
    }

    /** @test */
    public function it_can_get_matching_rules()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('Match1', [
            'type' => 'comparison',
            'left' => 'age',
            'op' => '>',
            'right' => 18
        ], ['action' => 'test'], 0);

        $rule2 = $ruleEngine->createRule('Match2', [
            'type' => 'comparison',
            'left' => 'country',
            'op' => '==',
            'right' => 'FR'
        ], ['action' => 'test'], 0);

        $rule3 = $ruleEngine->createRule('NoMatch', [
            'type' => 'comparison',
            'left' => 'premium',
            'op' => '==',
            'right' => true
        ], ['action' => 'test'], 0);

        $matching = $ruleEngine->getMatchingRules(['age' => 25, 'country' => 'FR', 'premium' => false]);

        $this->assertCount(2, $matching);
        $matchingIds = array_map(fn($r) => $r->id, $matching);
        $this->assertContains($rule1->id, $matchingIds);
        $this->assertContains($rule2->id, $matchingIds);
        $this->assertNotContains($rule3->id, $matchingIds);
    }

    /** @test */
    public function it_supports_logical_and_operation()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('LogicalAND', [
            'type' => 'logical',
            'operator' => 'AND',
            'left' => [
                'type' => 'comparison',
                'left' => ['type' => 'variable', 'name' => 'a'],
                'op' => '>',
                'right' => ['type' => 'literal', 'value' => 10]
            ],
            'right' => [
                'type' => 'comparison',
                'left' => ['type' => 'variable', 'name' => 'b'],
                'op' => '<',
                'right' => ['type' => 'literal', 'value' => 20]
            ]
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule, ['a' => 15, 'b' => 10]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['a' => 5, 'b' => 10]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['a' => 15, 'b' => 25]));
    }

    /** @test */
    public function it_supports_logical_or_operation()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('LogicalOR', [
            'type' => 'logical',
            'operator' => 'OR',
            'left' => [
                'type' => 'comparison',
                'left' => ['type' => 'variable', 'name' => 'is_admin'],
                'op' => '==',
                'right' => ['type' => 'literal', 'value' => true]
            ],
            'right' => [
                'type' => 'comparison',
                'left' => ['type' => 'variable', 'name' => 'is_moderator'],
                'op' => '==',
                'right' => ['type' => 'literal', 'value' => true]
            ]
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule, ['is_admin' => true, 'is_moderator' => false]));
        $this->assertTrue($ruleEngine->evaluate($rule, ['is_admin' => false, 'is_moderator' => true]));
        $this->assertTrue($ruleEngine->evaluate($rule, ['is_admin' => true, 'is_moderator' => true]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['is_admin' => false, 'is_moderator' => false]));
    }

    /** @test */
    public function it_supports_logical_not_operation()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('LogicalNOT', [
            'type' => 'logical',
            'operator' => 'NOT',
            'operand' => [
                'type' => 'comparison',
                'left' => ['type' => 'variable', 'name' => 'banned'],
                'op' => '==',
                'right' => ['type' => 'literal', 'value' => true]
            ]
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule, ['banned' => false]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['banned' => true]));
    }

    /** @test */
    public function it_supports_function_based_rules()
    {
        $ruleEngine = app('business-orchestration')->rule();

        // Test empty function
        $rule1 = $ruleEngine->createRule('EmptyFunction', [
            'type' => 'function',
            'name' => 'empty',
            'args' => [['type' => 'variable', 'name' => 'cart']]
        ], ['action' => 'show_empty_cart'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule1, ['cart' => []]));
        $this->assertTrue($ruleEngine->evaluate($rule1, ['cart' => null]));
        $this->assertFalse($ruleEngine->evaluate($rule1, ['cart' => ['item1']]));

        // Test is_null function
        $rule2 = $ruleEngine->createRule('IsNullFunction', [
            'type' => 'function',
            'name' => 'is_null',
            'args' => [['type' => 'variable', 'name' => 'value']]
        ], ['action' => 'handle_null'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule2, ['value' => null]));
        $this->assertFalse($ruleEngine->evaluate($rule2, ['value' => 0]));
        $this->assertFalse($ruleEngine->evaluate($rule2, ['value' => '']));
    }

    /** @test */
    public function fluent_builder_supports_priority()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->rule('PriorityRule')
            ->when('value', '>', 100)
            ->priority(50)
            ->then(fn($ctx) => ['result' => 'high']);

        $this->assertEquals(50, $rule->priority);
    }

    /** @test */
    public function it_can_register_and_execute_macros()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $ruleEngine->macro('premium_check', function($ruleEngine) {
            return $ruleEngine->rule('PremiumMacro')
                ->when('tier', '==', 'premium')
                ->and('active', '==', true);
        });

        $builder = $ruleEngine->executeMacro('premium_check', [$ruleEngine]);

        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\RuleBuilder::class, $builder);
    }

    /** @test */
    public function macro_throws_exception_when_not_found()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Macro nonexistent not found');

        $ruleEngine->executeMacro('nonexistent', []);
    }

    /** @test */
    public function it_supports_contains_operator()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule = $ruleEngine->createRule('ContainsOperator', [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => 'tags'],
            'op' => 'contains',
            'right' => ['type' => 'literal', 'value' => 'featured']
        ], ['action' => 'test'], 0);

        $this->assertTrue($ruleEngine->evaluate($rule, ['tags' => ['new', 'featured', 'sale']]));
        $this->assertFalse($ruleEngine->evaluate($rule, ['tags' => ['new', 'sale']]));
    }

    /** @test */
    public function batch_evaluate_returns_detailed_results()
    {
        $ruleEngine = app('business-orchestration')->rule();

        $rule1 = $ruleEngine->createRule('Rule1', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '>',
            'right' => 10
        ], ['action' => 'test'], 5);

        $rule2 = $ruleEngine->createRule('Rule2', [
            'type' => 'comparison',
            'left' => 'value',
            'op' => '<',
            'right' => 100
        ], ['action' => 'test'], 3);

        $results = $ruleEngine->evaluateBatch([$rule1->id, $rule2->id], ['value' => 50]);

        $this->assertArrayHasKey($rule1->id, $results);
        $this->assertArrayHasKey($rule2->id, $results);
        $this->assertEquals('Rule1', $results[$rule1->id]['name']);
        $this->assertTrue($results[$rule1->id]['passed']);
        $this->assertEquals(5, $results[$rule1->id]['priority']);
        $this->assertTrue($results[$rule2->id]['passed']);
    }
}
