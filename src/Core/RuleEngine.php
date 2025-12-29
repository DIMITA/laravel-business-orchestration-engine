<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\Rule;

class RuleEngine
{
    protected array $macros = [];
    protected array $customOperators = [];
    protected array $ruleGroups = [];

    /**
     * Evaluate a rule against context
     */
    public function evaluate(Rule $rule, array $context): bool
    {
        $ast = $rule->condition_ast;
        return $this->evaluateAst($ast, $context);
    }

    /**
     * Evaluate multiple rules in a group with AND/OR logic
     */
    public function evaluateGroup(string $groupName, array $context, string $logic = 'AND'): bool
    {
        if (!isset($this->ruleGroups[$groupName])) {
            return false;
        }

        $results = [];
        foreach ($this->ruleGroups[$groupName] as $ruleId) {
            $rule = Rule::find($ruleId);
            if ($rule) {
                $results[] = $this->evaluate($rule, $context);
            }
        }

        return $logic === 'AND'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);
    }

    /**
     * Execute action if rule passes
     */
    public function executeAction($rule, array $context)
    {
        $action = $rule->action;

        if ($action instanceof \Closure || is_callable($action)) {
            return $action($context);
        }

        // Support class-based actions
        if (is_array($action) && isset($action['class'], $action['method'])) {
            $instance = new $action['class']();
            return $instance->{$action['method']}($context);
        }

        return null;
    }

    /**
     * Create a fluent rule builder
     */
    public function rule(string $name): RuleBuilder
    {
        return new RuleBuilder($name, $this);
    }

    /**
     * Create a rule with AST
     */
    public function createRule(string $name, array $conditionAst, array $action, ?int $priority = null)
    {
        return Rule::create([
            'name' => $name,
            'condition_ast' => $conditionAst,
            'action' => $action,
            'priority' => $priority ?? 0,
        ]);
    }

    /**
     * Register a macro for common rule patterns
     */
    public function macro(string $name, callable $callback): void
    {
        $this->macros[$name] = $callback;
    }

    /**
     * Execute a registered macro
     */
    public function executeMacro(string $name, array $args): mixed
    {
        if (!isset($this->macros[$name])) {
            throw new \Exception("Macro {$name} not found");
        }

        return call_user_func_array($this->macros[$name], $args);
    }

    /**
     * Register a custom operator
     */
    public function registerOperator(string $operator, callable $callback): void
    {
        $this->customOperators[$operator] = $callback;
    }

    /**
     * Add rule to a group
     */
    public function addToGroup(string $groupName, int $ruleId): void
    {
        if (!isset($this->ruleGroups[$groupName])) {
            $this->ruleGroups[$groupName] = [];
        }

        if (!in_array($ruleId, $this->ruleGroups[$groupName])) {
            $this->ruleGroups[$groupName][] = $ruleId;
        }
    }

    /**
     * Get all rules in a group
     */
    public function getGroup(string $groupName): array
    {
        return $this->ruleGroups[$groupName] ?? [];
    }

    /**
     * Batch evaluate rules with priorities
     */
    public function evaluateBatch(array $ruleIds, array $context): array
    {
        $rules = Rule::whereIn('id', $ruleIds)
            ->orderBy('priority', 'desc')
            ->get();

        $results = [];
        foreach ($rules as $rule) {
            $results[$rule->id] = [
                'name' => $rule->name,
                'passed' => $this->evaluate($rule, $context),
                'priority' => $rule->priority,
            ];
        }

        return $results;
    }

    /**
     * Get all rules that pass for given context
     */
    public function getMatchingRules(array $context): array
    {
        $allRules = Rule::all();
        $matching = [];

        foreach ($allRules as $rule) {
            if ($this->evaluate($rule, $context)) {
                $matching[] = $rule;
            }
        }

        return $matching;
    }

    /**
     * Evaluate AST node recursively
     */
    protected function evaluateAst(array $ast, array $context): bool
    {
        if ($ast['type'] === 'comparison') {
            return $this->evaluateComparison($ast, $context);
        }

        if ($ast['type'] === 'logical') {
            return $this->evaluateLogical($ast, $context);
        }

        if ($ast['type'] === 'function') {
            return $this->evaluateFunction($ast, $context);
        }

        return false;
    }

    /**
     * Evaluate comparison node
     */
    protected function evaluateComparison(array $ast, array $context): bool
    {
        // For backward compatibility with old AST format:
        // 'left' is always treated as a variable name when it's a string
        $left = $this->resolveValueAsVariable($ast['left'], $context);
        // 'right' is resolved normally (can be literal or variable)
        $right = $this->resolveValue($ast['right'], $context);
        $op = $ast['op'];

        // Check for custom operators
        if (isset($this->customOperators[$op])) {
            return $this->customOperators[$op]($left, $right, $context);
        }

        return match ($op) {
            '==' => $left == $right,
            '===' => $left === $right,
            '!=' => $left != $right,
            '!==' => $left !== $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            'in' => is_array($right) && in_array($left, $right),
            'contains' => is_array($left) && in_array($right, $left),
            'starts_with' => is_string($left) && is_string($right) && str_starts_with($left, $right),
            'ends_with' => is_string($left) && is_string($right) && str_ends_with($left, $right),
            default => false,
        };
    }

    /**
     * Evaluate logical node (AND/OR/NOT)
     */
    protected function evaluateLogical(array $ast, array $context): bool
    {
        $operator = $ast['operator'];

        if ($operator === 'NOT') {
            return !$this->evaluateAst($ast['operand'], $context);
        }

        $left = $this->evaluateAst($ast['left'], $context);

        if ($operator === 'AND') {
            return $left && $this->evaluateAst($ast['right'], $context);
        }

        if ($operator === 'OR') {
            return $left || $this->evaluateAst($ast['right'], $context);
        }

        return false;
    }

    /**
     * Evaluate function node
     */
    protected function evaluateFunction(array $ast, array $context): bool
    {
        $functionName = $ast['name'];
        $args = array_map(fn($arg) => $this->resolveValue($arg, $context), $ast['args'] ?? []);

        // Built-in functions
        return match ($functionName) {
            'empty' => empty($args[0] ?? null),
            'isset' => isset($args[0]),
            'is_null' => is_null($args[0] ?? null),
            'count' => is_array($args[0] ?? null) && count($args[0]) === ($args[1] ?? 0),
            default => false,
        };
    }

    /**
     * Resolve value as a variable name (for backward compatibility with old AST format)
     */
    protected function resolveValueAsVariable($value, array $context)
    {
        // Handle structured value format
        if (is_array($value) && isset($value['type'])) {
            if ($value['type'] === 'variable') {
                return $context[$value['name']] ?? null;
            }
            if ($value['type'] === 'literal') {
                return $value['value'];
            }
        }

        // If it's a string, treat as variable name
        if (is_string($value)) {
            return $context[$value] ?? null;
        }

        // Otherwise return as-is
        return $value;
    }

    /**
     * Resolve value from context or use literal
     */
    protected function resolveValue($value, array $context)
    {
        // Handle structured value format
        if (is_array($value) && isset($value['type'])) {
            if ($value['type'] === 'variable') {
                return $context[$value['name']] ?? null;
            }
            if ($value['type'] === 'literal') {
                return $value['value'];
            }
        }

        // If it's a string starting with $, treat as variable
        if (is_string($value) && str_starts_with($value, '$')) {
            $varName = substr($value, 1);
            return $context[$varName] ?? null;
        }

        // Otherwise treat as literal value (strings, numbers, booleans, etc.)
        return $value;
    }
}

/**
 * Fluent Rule Builder
 */
class RuleBuilder
{
    protected string $name;
    protected RuleEngine $engine;
    protected array $conditions = [];
    protected $action;
    protected int $priority = 0;

    public function __construct(string $name, RuleEngine $engine)
    {
        $this->name = $name;
        $this->engine = $engine;
    }

    public function when(string $field, string $operator, $value): self
    {
        $this->conditions[] = [
            'type' => 'comparison',
            'left' => ['type' => 'variable', 'name' => $field],
            'op' => $operator,
            'right' => ['type' => 'literal', 'value' => $value],
        ];

        return $this;
    }

    public function and(string $field, string $operator, $value): self
    {
        return $this->when($field, $operator, $value);
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function then(callable $action): Rule
    {
        $ast = count($this->conditions) === 1
            ? $this->conditions[0]
            : $this->buildLogicalAst('AND', $this->conditions);

        return $this->engine->createRule(
            $this->name,
            $ast,
            ['callable' => $action],
            $this->priority
        );
    }

    protected function buildLogicalAst(string $operator, array $conditions): array
    {
        if (count($conditions) === 1) {
            return $conditions[0];
        }

        $first = array_shift($conditions);
        $rest = $this->buildLogicalAst($operator, $conditions);

        return [
            'type' => 'logical',
            'operator' => $operator,
            'left' => $first,
            'right' => $rest,
        ];
    }
}