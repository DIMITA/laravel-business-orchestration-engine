# Laravel Business Orchestration Engine

A comprehensive Laravel package for business orchestration, including Saga Pattern, Workflow Management, Event Sourcing, Versioning, Rule Engine, and Dependency Management.

> **Note**: This package is based on battle-tested code patterns I've been using in production for years. I've packaged it to make these proven patterns easily reusable across projects.

[![Tests](https://img.shields.io/badge/tests-122%20passing-brightgreen)]()
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)]()
[![PHP](https://img.shields.io/badge/php-%5E8.1-blue)]()
[![Laravel](https://img.shields.io/badge/laravel-%5E10.0%20%7C%20%5E11.0-red)]()

## Table of Contents

- [Installation](#installation)
- [Features](#features)
- [Usage Guide](#usage-guide)
  - [1. Saga Pattern](#1-saga-pattern)
  - [2. Workflow Engine](#2-workflow-engine)
  - [3. Event Sourcing](#3-event-sourcing)
  - [4. Versioning](#4-versioning)
  - [5. Rule Engine](#5-rule-engine)
  - [6. Sync Engine](#6-sync-engine)
  - [7. Dependency Engine](#7-dependency-engine)
- [Real-World Use Cases](#real-world-use-cases)
- [Architecture](#architecture)
- [Testing](#testing)

## Installation

Install the package via Composer:

```bash
composer require dimita/laravel-business-orchestration-engine
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --provider="Dimita\\BusinessOrchestration\\BusinessOrchestrationServiceProvider"
php artisan migrate
```

## Features

### ✨ 7 Powerful Engines

| Engine | Description | Use Case |
|--------|-------------|----------|
| **Saga Pattern** | Distributed transactions with automatic compensation | Complex business processes (orders, payments) |
| **Workflow Engine** | State machine with guards and transitions | Document validation, approval processes |
| **Event Sourcing** | Append-only event store | Audit trail, historical state reconstruction |
| **Versioning** | Immutable model snapshots | Change history, rollback capability |
| **Rule Engine** | Business rule evaluation via AST | Dynamic discounts, business validation |
| **Sync Engine** | Multi-device synchronization | Offline-first apps, mobile sync |
| **Dependency Engine** | Business constraint management | Pre-delete validation, dependency graphs |

## Usage Guide

### 1. Saga Pattern

**When to use?** When you have a business transaction involving multiple services and need to rollback (compensate) on failure.

#### Simple Example - Order Processing

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

// Define your saga steps
class ValidateOrderStep
{
    public function execute($payload)
    {
        $order = Order::find($payload['order_id']);

        if (!$order->isValid()) {
            throw new \Exception('Invalid order');
        }

        return true;
    }
}

class ChargePaymentStep
{
    public function execute($payload)
    {
        $order = Order::find($payload['order_id']);

        // Charge payment
        $payment = PaymentGateway::charge($order->total);

        if (!$payment->success) {
            throw new \Exception('Payment failed');
        }

        return true;
    }
}

class ShipOrderStep
{
    public function execute($payload)
    {
        $order = Order::find($payload['order_id']);

        // Ship the order
        ShippingService::ship($order);

        return true;
    }
}

// Start the saga
$saga = BusinessOrchestration::saga()->startSaga('OrderProcessing', [
    'validate' => ValidateOrderStep::class,
    'charge' => ChargePaymentStep::class,
    'ship' => ShipOrderStep::class,
], ['order_id' => 123]);

// If a step fails, completed steps will be automatically compensated
// The saga will have status 'COMPENSATED'
```

#### Resume After Crash

```php
// If your server crashes during execution
// You can resume the saga from its last state

$sagaEngine = BusinessOrchestration::saga();

// Resume saga by ID
$sagaEngine->resumeSaga($sagaId);
```

#### Saga Status Flow

- `PENDING` - Saga created, not started yet
- `RUNNING` - Saga currently executing
- `COMPLETED` - All steps completed successfully
- `FAILED` - A step failed (before compensation)
- `COMPENSATED` - Completed steps have been rolled back after failure

---

### 2. Workflow Engine

**When to use?** When you need a state machine to manage transitions between different business states.

#### Simple Example - Contract Validation

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$workflow = BusinessOrchestration::workflow();

// Define possible transitions
$workflow->defineTransition('submit', 'draft', 'submitted');
$workflow->defineTransition('review', 'submitted', 'in_review');
$workflow->defineTransition('approve', 'in_review', 'approved');
$workflow->defineTransition('reject', 'in_review', 'rejected');
$workflow->defineTransition('revise', 'rejected', 'draft');

// Use workflow on a model
$contract = Contract::find(1);
$builder = $workflow->for($contract);

// Check if transition is possible
if ($builder->can('approve')) {
    $builder->apply('approve');
}

// Get current state
echo $builder->getState(); // 'approved'
```

#### With Guards (Conditions)

```php
// Define transition with condition
$workflow->defineTransition(
    'auto_approve',
    'submitted',
    'approved',
    'return $context["amount"] < 1000;' // Guard expression
);

// Transition only possible if amount < 1000
```

#### Complex Workflow - Ticket Management

```php
// Draft -> Open -> In Progress -> (Resolved | Closed)
//                     ↓
//                  On Hold

$workflow->defineTransition('open', 'draft', 'open');
$workflow->defineTransition('start', 'open', 'in_progress');
$workflow->defineTransition('hold', 'in_progress', 'on_hold');
$workflow->defineTransition('resume', 'on_hold', 'in_progress');
$workflow->defineTransition('resolve', 'in_progress', 'resolved');
$workflow->defineTransition('close', 'resolved', 'closed');

$ticket = Ticket::find(1);
$builder = $workflow->for($ticket);

// Apply transitions through lifecycle
$builder->apply('open');
$builder->apply('start');
$builder->apply('hold');
$builder->apply('resume');
$builder->apply('resolve');
$builder->apply('close');
```

---

### 3. Event Sourcing

**When to use?** When you need to keep a complete history of all changes and be able to rebuild state at any point in time.

#### Simple Example - Shopping Cart

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$es = BusinessOrchestration::eventSourcing();

// Store events
$es->storeEvent('cart-123', 'CartCreated', [
    'user_id' => 456,
    'created_at' => now()
]);

$es->storeEvent('cart-123', 'ItemAdded', [
    'product_id' => 789,
    'quantity' => 2,
    'price' => 29.99
]);

$es->storeEvent('cart-123', 'ItemAdded', [
    'product_id' => 101,
    'quantity' => 1,
    'price' => 49.99
]);

$es->storeEvent('cart-123', 'CartCheckedOut', [
    'total' => 109.97,
    'payment_method' => 'credit_card'
]);

// Rebuild cart state
$cart = $es->rebuildAggregate('cart-123', function($state, $event) {
    switch ($event['event_type']) {
        case 'CartCreated':
            return [
                'user_id' => $event['payload']['user_id'],
                'items' => [],
                'total' => 0,
                'status' => 'active'
            ];

        case 'ItemAdded':
            $state['items'][] = $event['payload'];
            $state['total'] += $event['payload']['price'] * $event['payload']['quantity'];
            return $state;

        case 'CartCheckedOut':
            $state['status'] => 'checked_out';
            return $state;

        default:
            return $state;
    }
});

print_r($cart);
// Array (
//     'user_id' => 456,
//     'items' => [...],
//     'total' => 109.97,
//     'status' => 'checked_out'
// )
```

#### Retrieve All Events

```php
$events = $es->getEvents('cart-123');

foreach ($events as $event) {
    echo "{$event['event_type']} at version {$event['version']}\n";
}
```

---

### 4. Versioning

**When to use?** When you need to keep snapshots of your models to rollback or view history.

#### Simple Example - Document Versioning

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$version = BusinessOrchestration::version();

$document = Document::find(1);

// Create snapshot before modification
$version->snapshot($document);

// Modify document
$document->content = 'New content';
$document->save();

// Create another snapshot
$version->snapshot($document);

// Modify again
$document->content = 'Even newer content';
$document->save();

// Create third snapshot
$version->snapshot($document);

// View all versions
$versions = $version->getVersions($document);
// 3 versions available

// Restore to version 2
$version->restore($document, 2);

echo $document->content; // 'New content'
```

#### Use Case - Contract Audit Trail

```php
$contract = Contract::find(1);

// Create snapshot at each important change
$contract->status = 'draft';
$contract->save();
$version->snapshot($contract);

$contract->status = 'submitted';
$contract->save();
$version->snapshot($contract);

$contract->status = 'approved';
$contract->amount = 50000;
$contract->save();
$version->snapshot($contract);

// View complete history
$versions = $version->getVersions($contract);

foreach ($versions as $v) {
    echo "Version {$v['version']}: Status = {$v['snapshot']['status']}\n";
}
```

---

### 5. Rule Engine

**When to use?** When you have business rules that change frequently and you want to manage them without modifying code.

#### Simple Example - Discount Rules

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$ruleEngine = BusinessOrchestration::rule();

// Create rule: If amount > 500, apply discount
$discountRule = $ruleEngine->createRule('BigOrderDiscount', [
    'type' => 'comparison',
    'left' => 'amount',
    'op' => '>',
    'right' => 500
], ['type' => 'discount', 'value' => 10]);

// Evaluate rule
$order = ['amount' => 750, 'customer_id' => 123];

if ($ruleEngine->evaluate($discountRule, $order)) {
    echo "Discount applicable!";
    // Apply discount
}
```

#### Rules with Custom Actions

```php
// Create rule with callable action
$rule = $ruleEngine->createRule('VIPCustomerRule', [
    'type' => 'comparison',
    'left' => 'customer_tier',
    'op' => '==',
    'right' => 'VIP'
], ['type' => 'action', 'name' => 'apply_vip_benefits']);

// Execute dynamic action
$ruleObject = new stdClass();
$ruleObject->action = function($context) {
    // Send VIP email
    Mail::to($context['email'])->send(new VIPWelcome());

    // Apply discount
    return ['discount' => 20];
};

if ($ruleEngine->evaluate($rule, $customer)) {
    $result = $ruleEngine->executeAction($ruleObject, $customer);
}
```

#### Supported Operators

```php
// Numeric comparisons
'>' // Greater than
'<' // Less than
'==' // Equal to

// Examples
$rule1 = $ruleEngine->createRule('AgeCheck', [
    'type' => 'comparison',
    'left' => 'age',
    'op' => '>',
    'right' => 18
], ['type' => 'allow']);

$rule2 = $ruleEngine->createRule('StockCheck', [
    'type' => 'comparison',
    'left' => 'stock',
    'op' => '<',
    'right' => 10
], ['type' => 'reorder']);
```

---

### 6. Sync Engine

**When to use?** To synchronize data between multiple devices (mobile app, web, etc.) with offline support.

#### Simple Example - Mobile Sync

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$sync = BusinessOrchestration::sync();

// On server, log each change
$task = Task::find(1);

$sync->logChange($task, 'INSERT', [
    'title' => 'New task',
    'status' => 'pending'
]);

// Later, modification
$task->status = 'in_progress';
$task->save();

$sync->logChange($task, 'UPDATE', [
    'status' => 'in_progress'
]);

// Another modification
$task->status = 'completed';
$task->save();

$sync->logChange($task, 'UPDATE', [
    'status' => 'completed'
]);
```

#### Retrieving Changes (Mobile Client)

```php
// Mobile client requests changes since last sync
$lastSyncVersion = 5; // Version from last sync

$deltas = $sync->getDeltas(
    'App\\Models\\Task',
    $taskId,
    $lastSyncVersion
);

// Client receives only changes after version 5
foreach ($deltas as $delta) {
    echo "Version {$delta['version']}: {$delta['operation']}\n";
    // Apply changes locally
    applyChange($delta);
}
```

#### Offline Scenario

```php
// 1. Mobile app syncs
$clientVersion = 0;
$deltas = $sync->getDeltas('App\\Models\\Task', 1, $clientVersion);
// Receives all modifications

// 2. Client goes offline and makes local modifications
// Modifications stored locally

// 3. Client comes back online
// Send local modifications to server
foreach ($localChanges as $change) {
    $sync->logChange($model, $change['operation'], $change['fields']);
}

// 4. Get new changes from server
$newClientVersion = 15; // Version after upload
$newDeltas = $sync->getDeltas('App\\Models\\Task', 1, $newClientVersion);
```

---

### 7. Dependency Engine

**When to use?** To manage business dependencies and prevent deletions that would violate constraints.

#### Simple Example - Prevent Deletion

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$dep = BusinessOrchestration::dependency();

// Define that Category cannot be deleted if Products exist
$dep->addDependency(
    'App\\Models\\Product',
    'App\\Models\\Category',
    'prevent_delete'
);

// Before deleting a category
$categoryId = 5;

if (!$dep->checkDeletion('App\\Models\\Category', $categoryId)) {
    return response()->json([
        'error' => 'Cannot delete category with existing products'
    ], 422);
}

// Otherwise, delete
Category::destroy($categoryId);
```

#### Complex Dependency Graph

```php
// Define dependency graph
$dep->addDependency('App\\Models\\OrderItem', 'App\\Models\\Order', 'cascade_delete');
$dep->addDependency('App\\Models\\Order', 'App\\Models\\Customer', 'prevent_delete');
$dep->addDependency('App\\Models\\Product', 'App\\Models\\Category', 'prevent_delete');
$dep->addDependency('App\\Models\\OrderItem', 'App\\Models\\Product', 'prevent_delete');

// Get all dependencies for a model
$dependencies = $dep->getDependencies('App\\Models\\Product');

// Before deletion, check all constraints
if (!$dep->checkDeletion('App\\Models\\Customer', $customerId)) {
    throw new \Exception('Customer has active orders');
}
```

#### Dependency Rule Types

```php
// Prevent delete - Block deletion
$dep->addDependency('Child', 'Parent', 'prevent_delete');

// Cascade delete - Delete children
$dep->addDependency('Child', 'Parent', 'cascade_delete');

// Soft delete - Soft delete children
$dep->addDependency('Child', 'Parent', 'soft_delete');

// Custom rule - Custom business rule
$dep->addDependency('Child', 'Parent', 'custom_business_rule');
```

---

## Real-World Use Cases

### E-commerce - Complete Order Process

```php
// 1. Saga for order processing
$saga = BusinessOrchestration::saga()->startSaga('OrderProcessing', [
    'validate_cart' => ValidateCartStep::class,
    'reserve_stock' => ReserveStockStep::class,
    'charge_payment' => ChargePaymentStep::class,
    'create_shipment' => CreateShipmentStep::class,
    'send_confirmation' => SendConfirmationStep::class,
], ['order_id' => $order->id]);

// 2. Workflow for order status
$workflow = BusinessOrchestration::workflow();
$workflow->defineTransition('pay', 'pending', 'paid');
$workflow->defineTransition('ship', 'paid', 'shipped');
$workflow->defineTransition('deliver', 'shipped', 'delivered');

$builder = $workflow->for($order);
$builder->apply('pay');

// 3. Event Sourcing for audit
$es = BusinessOrchestration::eventSourcing();
$es->storeEvent("order-{$order->id}", 'OrderCreated', $order->toArray());
$es->storeEvent("order-{$order->id}", 'PaymentReceived', $payment->toArray());
$es->storeEvent("order-{$order->id}", 'OrderShipped', $shipment->toArray());

// 4. Versioning for order history
$version = BusinessOrchestration::version();
$version->snapshot($order); // At each important step

// 5. Business rules for discounts
$rule = BusinessOrchestration::rule()->createRule('FirstOrderDiscount', [
    'type' => 'comparison',
    'left' => 'order_count',
    'op' => '==',
    'right' => 1
], ['discount' => 15]);

// 6. Sync for customer mobile app
$sync = BusinessOrchestration::sync();
$sync->logChange($order, 'UPDATE', ['status' => 'shipped']);

// 7. Dependencies to prevent invalid deletions
$dep = BusinessOrchestration::dependency();
$dep->addDependency('App\\Models\\OrderItem', 'App\\Models\\Order', 'cascade_delete');
```

### SaaS - Subscription Management

```php
// Subscription workflow
$workflow = BusinessOrchestration::workflow();
$workflow->defineTransition('activate', 'trial', 'active');
$workflow->defineTransition('cancel', 'active', 'cancelled');
$workflow->defineTransition('suspend', 'active', 'suspended');
$workflow->defineTransition('reactivate', 'suspended', 'active');

// Event sourcing for billing history
$es = BusinessOrchestration::eventSourcing();
$es->storeEvent("subscription-{$sub->id}", 'SubscriptionStarted', [...]);
$es->storeEvent("subscription-{$sub->id}", 'PaymentProcessed', [...]);
$es->storeEvent("subscription-{$sub->id}", 'UpgradedToPro', [...]);

// Rules for automatic upgrades
$upgradeRule = $ruleEngine->createRule('AutoUpgrade', [
    'type' => 'comparison',
    'left' => 'usage',
    'op' => '>',
    'right' => 80
], ['action' => 'suggest_upgrade']);
```

## Architecture

### Package Structure

```
src/
├── Core/                    # Main engines
│   ├── SagaEngine.php
│   ├── WorkflowEngine.php
│   ├── EventSourcingEngine.php
│   ├── VersionEngine.php
│   ├── RuleEngine.php
│   ├── SyncEngine.php
│   └── DependencyEngine.php
├── Models/                  # Eloquent models
│   ├── Saga.php
│   ├── SagaStep.php
│   ├── WorkflowInstance.php
│   ├── WorkflowTransition.php
│   ├── EventStore.php
│   ├── ModelVersion.php
│   ├── SyncLog.php
│   ├── Rule.php
│   └── Dependency.php
├── Drivers/                 # Multi-driver support
│   ├── DatabaseDriver.php
│   ├── RedisDriver.php
│   └── QueueDriver.php
└── BusinessOrchestration.php # Main facade
```

### Design Principles

- **Human-friendly logic**: Clear and intuitive API
- **Production-ready**: Error handling, compensation, crash recovery
- **Persistent**: Everything saved to database
- **Extensible**: Multi-driver support (DB, Redis, Queue)
- **Testable**: 122 tests, 100% coverage

## Testing

The package includes 122 tests covering all use cases:

```bash
# Run all tests
vendor/bin/phpunit

# With details
vendor/bin/phpunit --testdox

# Result
OK (122 tests, 252 assertions)
```

### Test Coverage by Engine

- **SagaEngine**: 16 tests (compensation, resume, states)
- **WorkflowEngine**: 16 tests (transitions, guards, states)
- **RuleEngine**: 18 tests (evaluation, AST, actions)
- **DependencyEngine**: 17 tests (graphs, constraints)
- **EventSourcingEngine**: 16 tests (events, rebuild, versioning)
- **VersionEngine**: 19 tests (snapshots, restore, audit)
- **SyncEngine**: 20 tests (deltas, offline, incremental)

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Dimita\\BusinessOrchestration\\BusinessOrchestrationServiceProvider"
```

Available in `config/business-orchestration.php`:

```php
return [
    'drivers' => [
        'default' => 'database',

        'database' => [
            'connection' => null, // null = default connection
        ],

        'redis' => [
            'connection' => 'default',
        ],

        'queue' => [
            'connection' => 'default',
        ],
    ],
];
```

## Support

- **Documentation**: This README
- **Issues**: [GitHub Issues](https://github.com/dimita/laravel-business-orchestration-engine/issues)
- **Tests**: 100% coverage, 122 tests

## License

MIT License - Free for commercial and open-source projects.

## Changelog

### v1.0.0 (2025)
- ✅ Saga Pattern with automatic compensation
- ✅ Workflow Engine with guards
- ✅ Event Sourcing with rebuild
- ✅ Versioning with snapshots
- ✅ Rule Engine with AST
- ✅ Sync Engine for multi-device
- ✅ Dependency Engine for business constraints
- ✅ 122 tests with 100% coverage
- ✅ Production-ready with complete error handling

---

**Made with ❤️ for the Laravel community**

*Based on production-tested patterns I've refined over years of building enterprise applications.*
