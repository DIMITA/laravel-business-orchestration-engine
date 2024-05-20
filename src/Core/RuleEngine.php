<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\Rule;

class RuleEngine
{
    public function evaluate(Rule $rule, array $context): bool
    {
        // Simple AST evaluation - in real, implement a proper evaluator
        $ast = $rule->condition_ast;

        return $this->evaluateAst($ast, $context);
    }

    protected function evaluateAst(array $ast, array $context): bool
    {
        // Very basic implementation
        if ($ast['type'] === 'comparison') {
            $left = $context[$ast['left']] ?? null;
            $right = $ast['right'];
            $op = $ast['op'];

            return match ($op) {
                '==' => $left == $right,
                '>' => $left > $right,
                '<' => $left < $right,
                default => false,
            };
        }

        return false;
    }

    public function executeAction(Rule $rule, array $context)
    {
        // Execute action - assume it's a callable or class
        $action = $rule->action;

        if (is_callable($action)) {
            $action($context);
        }
    }

    public function createRule(string $name, array $conditionAst, array $action)
    {
        return Rule::create([
            'name' => $name,
            'condition_ast' => $conditionAst,
            'action' => $action,
        ]);
    }
}