# Laravel Business Orchestration Engine

A comprehensive Laravel package for business orchestration, including Saga Pattern, Workflow Management, Event Sourcing, Versioning, Rule Engine, and Dependency Management.

> **Note**: This package is based on battle-tested code patterns I've been using in production for years. I've packaged it to make these proven patterns easily reusable across projects.

[![Tests](https://img.shields.io/badge/tests-122%20passing-brightgreen)]()
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)]()
[![PHP](https://img.shields.io/badge/php-%5E8.1-blue)]()
[![Laravel](https://img.shields.io/badge/laravel-%5E10.0%20%7C%20%5E11.0-red)]()

## Requirements

- **PHP**: ^8.1 or higher
- **Laravel**: ^10.0 or ^11.0
- **Database**: MySQL 5.7+, PostgreSQL 10+, or SQLite 3.8+
- **Extensions**:
  - `ext-json` - JSON support for payload serialization
  - `ext-pdo` - Database connectivity

### Optional Requirements

For enhanced saga orchestration capabilities:
- **Queue Driver**: Redis, Database, or SQS for asynchronous step execution
- **Cache Driver**: Redis or Memcached for performance optimization
- **Message Queue** (optional): RabbitMQ for distributed saga coordination (see [vandarpay/orchestration-saga](https://github.com/vandarpay/orchestration-saga) for microservice orchestration)

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

// Start the saga synchronously
$saga = BusinessOrchestration::saga()->startSaga('OrderProcessing', [
    'validate' => ValidateOrderStep::class,
    'charge' => ChargePaymentStep::class,
    'ship' => ShipOrderStep::class,
], ['order_id' => 123]);

// If a step fails, completed steps will be automatically compensated
```

#### Advanced Compensation

Define compensation logic for each step to properly rollback changes:

```php
class ChargePaymentStep
{
    public function execute($payload)
    {
        $order = Order::find($payload['order_id']);
        $payment = PaymentGateway::charge($order->total);

        if (!$payment->success) {
            throw new \Exception('Payment failed');
        }

        return true;
    }

    // Define compensation logic
    public function compensate($payload)
    {
        $order = Order::find($payload['order_id']);

        // Refund the payment
        PaymentGateway::refund($order->total);

        // Update order status
        $order->update(['status' => 'payment_refunded']);
    }
}
```

#### Asynchronous Execution

Execute sagas asynchronously using Laravel queues:

```php
// Start saga asynchronously (returns immediately)
$saga = BusinessOrchestration::saga()->startSagaAsync('OrderProcessing', [
    'validate' => ValidateOrderStep::class,
    'charge' => ChargePaymentStep::class,
    'ship' => ShipOrderStep::class,
], ['order_id' => 123]);

// Check saga status later
$status = BusinessOrchestration::saga()->getSagaStatus($saga->id);

echo $status['status']; // PENDING, RUNNING, COMPLETED, COMPENSATED
echo $status['completed_steps'] . '/' . $status['total_steps'];
```

#### Resume After Crash

```php
// If your server crashes during execution, resume the saga
$sagaEngine = BusinessOrchestration::saga();
$sagaEngine->resumeSaga($sagaId);
```

#### Cancel Running Saga

```php
// Cancel a saga that's pending or running
$sagaEngine = BusinessOrchestration::saga();
$sagaEngine->cancelSaga($sagaId);
```

#### Saga Status Flow

- `PENDING` - Saga created, not started yet
- `RUNNING` - Saga currently executing
- `COMPLETED` - All steps completed successfully
- `FAILED` - A step failed (before compensation)
- `COMPENSATED` - Completed steps have been rolled back after failure
- `CANCELLED` - Saga was manually cancelled
- `COMPENSATION_FAILED` - Compensation encountered an error (step-level)

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

#### Advanced Features

##### Register Workflow Definition

```php
// Define a complete workflow with configuration
$workflow->registerWorkflow('order_approval', [
    'transitions' => [
        ['name' => 'submit', 'from' => 'draft', 'to' => 'pending'],
        ['name' => 'approve', 'from' => 'pending', 'to' => 'approved'],
        ['name' => 'reject', 'from' => 'pending', 'to' => 'rejected'],
    ]
]);
```

##### Event Hooks

```php
// Execute custom logic before transitions
$workflow->beforeTransition('approve', function($instance) {
    Log::info("Approving workflow for {$instance->model_type}");
    // Send notification, update related records, etc.
});

// Execute custom logic after transitions
$workflow->afterTransition('approve', function($instance) {
    Mail::to($user)->send(new ApprovalConfirmation());
});
```

##### Get Available Transitions

```php
$builder = $workflow->for($document);

// Get all transitions available from current state
$availableTransitions = $builder->getEnabledTransitions(['amount' => 500]);

// Returns: ['approve', 'reject', 'request_changes']
```

##### Check Workflow State

```php
// Check if model is in specific state
if ($workflow->isInState($order, 'approved')) {
    // Process approved order
}

// Get all possible states in the workflow
$allStates = $workflow->getAllStates();
// Returns: ['draft', 'pending', 'approved', 'rejected']
```

##### Force State Change

```php
// Override guards and force a state change (use carefully)
$builder->forceTransition('cancelled', 'Manual cancellation by admin');
```

> **Note**: The workflow engine is inspired by [Laravel Workflow](https://laravel-workflow.com) and [Symfony Workflow](https://symfony.com/doc/current/workflow.html) patterns, providing state machine functionality with guards, events, and transition history.

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

#### Advanced Features

##### Projectors - Create Read Models

Projectors listen to events and create read models (projections) for querying:

```php
class OrderTotalProjector
{
    // Called when MoneyAdded event is stored
    public function onMoneyAdded($event)
    {
        $account = Account::findOrFail($event->aggregate_id);
        $account->increment('balance', $event->payload['amount']);
    }

    // Called when MoneySubtracted event is stored
    public function onMoneySubtracted($event)
    {
        $account = Account::findOrFail($event->aggregate_id);
        $account->decrement('balance', $event->payload['amount']);
    }
}

// Register the projector
$es->addProjector(OrderTotalProjector::class);

// Now when you store events, projector will automatically update read models
$es->storeEvent('account-123', 'MoneyAdded', ['amount' => 100]);
```

##### Reactors - Handle Side Effects

Reactors respond to events with side effects (emails, notifications, etc.):

```php
class SendEmailReactor
{
    public function onOrderPlaced($event)
    {
        // Send confirmation email
        Mail::to($event->payload['email'])->send(new OrderConfirmation($event));
    }
}

// Register the reactor
$es->addReactor(SendEmailReactor::class);

// Reactor will handle side effects asynchronously
$es->storeEvent('order-456', 'OrderPlaced', ['email' => 'customer@example.com']);
```

##### Event Replay

Rebuild projections by replaying all events:

```php
// Replay all events through projectors
$count = $es->replay();
echo "Replayed {$count} events";

// Replay only specific aggregate
$count = $es->replay('account-123');

// Replay through specific projectors only
$count = $es->replay(null, [OrderTotalProjector::class]);
```

##### Snapshots for Performance

Create snapshots to avoid replaying thousands of events:

```php
// Create a snapshot of current state
$cart = $es->rebuildAggregate('cart-123', $reducer);
$es->snapshot('cart-123', $cart);

// Retrieve latest snapshot instead of rebuilding from all events
$cart = $es->getLatestSnapshot('cart-123');

if (!$cart) {
    // No snapshot exists, rebuild from events
    $cart = $es->rebuildAggregate('cart-123', $reducer);
}
```

##### Metadata and Event Queries

```php
// Store event with metadata
$es->storeEvent('order-789', 'OrderShipped',
    ['tracking_number' => 'ABC123'],
    ['user_id' => auth()->id(), 'ip_address' => request()->ip()]
);

// Get events by type
$shippedOrders = $es->getEventsByType('OrderShipped', 10);

// Get latest version number
$latestVersion = $es->getLatestVersion('order-789');

// Get events from specific version
$newEvents = $es->getEvents('order-789', $fromVersion = 5);
```

> **Note**: The event sourcing engine is inspired by [Spatie Laravel Event Sourcing](https://spatie.be/docs/laravel-event-sourcing), providing projectors, reactors, and aggregate root patterns for building event-sourced applications.

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

### Architecture Diagrams

#### 1. Saga Pattern Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    SAGA ORCHESTRATION                            │
└─────────────────────────────────────────────────────────────────┘

Success Flow:
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐
│ PENDING │ -> │ RUNNING │ -> │ RUNNING │ -> │COMPLETED│
│         │    │ Step 1  │    │ Step 2  │    │         │
└─────────┘    └─────────┘    └─────────┘    └─────────┘

Failure + Compensation Flow:
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌──────────────┐
│ PENDING │ -> │ RUNNING │ -> │ FAILED  │ -> │ COMPENSATED  │
│         │    │ Step 1✓ │    │ Step 2✗ │    │ Rollback 1✓  │
└─────────┘    └─────────┘    └─────────┘    └──────────────┘

Database Schema:
sagas                           saga_steps
├── id                          ├── id
├── name                        ├── saga_id (FK)
├── status                      ├── step_name
├── payload (JSON)              ├── status
├── current_step                ├── executed_at
└── timestamps                  ├── compensated_at
                                ├── error
                                └── timestamps
```

#### 2. Workflow State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                    WORKFLOW ENGINE                               │
└─────────────────────────────────────────────────────────────────┘

State Transition Graph:
                    submit
    ┌─────────┐ ─────────> ┌───────────┐
    │  draft  │            │ submitted │
    └─────────┘ <───────── └───────────┘
                   revise         │
                                  │ review
                                  v
                            ┌───────────┐
                     reject │ in_review │ approve
                    ┌───────┴───────────┴────────┐
                    │                             │
                    v                             v
              ┌──────────┐                  ┌──────────┐
              │ rejected │                  │ approved │
              └──────────┘                  └──────────┘

Database Schema:
workflow_instances              workflow_transitions
├── id                          ├── id
├── model_type                  ├── instance_id (FK)
├── model_id                    ├── from_state
├── state                       ├── to_state
└── timestamps                  ├── transition_name
                                ├── context (JSON)
                                └── timestamps
```

#### 3. Event Sourcing Stream

```
┌─────────────────────────────────────────────────────────────────┐
│                    EVENT SOURCING                                │
└─────────────────────────────────────────────────────────────────┘

Event Stream (Append-Only):
Time ─────────────────────────────────────────────────────>

┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│OrderCreated  │─>│PaymentCharged│─>│ItemsShipped  │─>│OrderDelivered│
│ v1           │  │ v2           │  │ v3           │  │ v4           │
└──────────────┘  └──────────────┘  └──────────────┘  └──────────────┘

Aggregate Rebuild:
Initial State: {}
  + OrderCreated     -> {status: 'pending', total: 100}
  + PaymentCharged   -> {status: 'paid', total: 100, payment_id: 123}
  + ItemsShipped     -> {status: 'shipped', tracking: 'ABC123'}
  + OrderDelivered   -> {status: 'delivered', delivered_at: '2024-01-01'}

Database Schema:
event_store
├── id
├── aggregate_id
├── event_type
├── event_data (JSON)
├── version
├── metadata (JSON)
└── created_at
```

#### 4. Version Control System

```
┌─────────────────────────────────────────────────────────────────┐
│                    VERSIONING ENGINE                             │
└─────────────────────────────────────────────────────────────────┘

Snapshot Timeline:
Model State: {name: "Doc1", status: "draft"}
     │
     v  snapshot()
Version 1: {name: "Doc1", status: "draft", hash: "abc123"}
     │
     │  model.update({status: "published"})
     v  snapshot()
Version 2: {name: "Doc1", status: "published", hash: "def456"}
     │
     │  model.update({name: "Doc1-Updated"})
     v  snapshot()
Version 3: {name: "Doc1-Updated", status: "published", hash: "ghi789"}
     │
     │  restore(version: 1)
     v
Restored: {name: "Doc1", status: "draft"}

Database Schema:
model_versions
├── id
├── model_type
├── model_id
├── version
├── snapshot_data (JSON)
├── hash
└── created_at
```

#### 5. Rule Engine AST Evaluation

```
┌─────────────────────────────────────────────────────────────────┐
│                    RULE ENGINE                                   │
└─────────────────────────────────────────────────────────────────┘

Rule Definition (AST):
Business Rule: "If order total > 100 AND customer_type == 'VIP', apply 20% discount"

AST Structure:
{
  type: "logical",
  operator: "AND",
  left: {
    type: "comparison",
    left: "order_total",
    op: ">",
    right: 100
  },
  right: {
    type: "comparison",
    left: "customer_type",
    op: "==",
    right: "VIP"
  }
}

Evaluation Flow:
Context: {order_total: 150, customer_type: "VIP"}
  ├─> Evaluate left: 150 > 100 = true
  ├─> Evaluate right: "VIP" == "VIP" = true
  └─> AND(true, true) = true -> Execute action

Database Schema:
rules
├── id
├── name
├── condition_ast (JSON)
├── action (JSON)
└── timestamps
```

#### 6. Sync Engine Delta Synchronization

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYNC ENGINE                                   │
└─────────────────────────────────────────────────────────────────┘

Multi-Device Sync:
Server                          Client A                    Client B
  │                                │                           │
  │ v1: INSERT {name: "Item1"}     │                           │
  ├────────────────────────────────>│ Sync from v0             │
  │                                │ Receives: [v1]            │
  │                                │                           │
  │ v2: UPDATE {status: "active"}  │                           │
  ├────────────────────────────────>│ Sync from v1             │
  │                                │ Receives: [v2]            │
  │                                │                           │
  │                                │                           ├─> Sync from v0
  │                                │                           │   Receives: [v1, v2]
  │ v3: UPDATE {price: 99}         │                           │
  ├────────────────────────────────>│ Sync from v2             │
  │                                │ Receives: [v3]            │
  ├───────────────────────────────────────────────────────────>│ Sync from v2
  │                                │                           │ Receives: [v3]

Database Schema:
sync_log
├── id
├── model_type
├── model_id
├── operation (INSERT|UPDATE|DELETE)
├── version
├── changed_fields (JSON)
└── created_at
```

#### 7. Dependency Graph System

```
┌─────────────────────────────────────────────────────────────────┐
│                    DEPENDENCY ENGINE                             │
└─────────────────────────────────────────────────────────────────┘

Dependency Graph:
┌──────────┐
│  User    │
└────┬─────┘
     │ has_many (dependency: prevent_delete)
     ├────────────┬────────────┬────────────┐
     v            v            v            v
┌─────────┐  ┌────────┐  ┌─────────┐  ┌─────────┐
│ Order   │  │Profile │  │Comments │  │Payments │
└─────────┘  └────────┘  └─────────┘  └─────────┘

Deletion Check Flow:
canDelete(User #123)?
  ├─> Check Orders: 5 orders exist -> BLOCKED
  ├─> Check Profile: 1 profile exists -> BLOCKED
  ├─> Check Comments: 12 comments exist -> BLOCKED
  └─> Check Payments: 3 payments exist -> BLOCKED

Result: Cannot delete User #123 (has dependencies)

Database Schema:
dependencies
├── id
├── source_model
├── target_model
├── dependency_type
├── created_at
└── updated_at
```

#### Overall System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     Application Layer                            │
│  (Controllers, Services, Commands)                               │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       v
┌─────────────────────────────────────────────────────────────────┐
│              BusinessOrchestration Facade                        │
│  ->saga() ->workflow() ->eventSourcing() ->version()             │
│  ->rule() ->sync() ->dependency()                                │
└──────────────────────┬───────────────────────────────────────────┘
                       │
       ┌───────────────┼───────────────┐
       v               v               v
┌────────────┐  ┌────────────┐  ┌────────────┐
│   Saga     │  │  Workflow  │  │   Event    │
│  Engine    │  │  Engine    │  │  Sourcing  │
└─────┬──────┘  └─────┬──────┘  └─────┬──────┘
      │               │               │
┌────────────┐  ┌────────────┐  ┌────────────┐
│  Version   │  │    Rule    │  │    Sync    │
│  Engine    │  │  Engine    │  │   Engine   │
└─────┬──────┘  └─────┬──────┘  └─────┬──────┘
      │               │               │
      └───────────────┼───────────────┘
                      v
┌─────────────────────────────────────────────────────────────────┐
│                    Driver Layer                                  │
│  DatabaseDriver │ RedisDriver │ QueueDriver                      │
└──────────────────────┬───────────────────────────────────────────┘
                       │
                       v
┌─────────────────────────────────────────────────────────────────┐
│                 Persistence Layer                                │
│  MySQL │ PostgreSQL │ SQLite │ Redis │ RabbitMQ                 │
└─────────────────────────────────────────────────────────────────┘
```

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
