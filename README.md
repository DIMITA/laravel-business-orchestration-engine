# Laravel Business Orchestration Engine

A comprehensive Laravel package for business orchestration, including Saga Pattern, Workflow Management, Event Sourcing, Versioning, Rule Engine, and Dependency Management.

## Installation

```bash
composer require dimita/laravel-business-orchestration-engine
```

Publish the config and migrations:

```bash
php artisan vendor:publish --provider="Dimita\\BusinessOrchestration\\BusinessOrchestrationServiceProvider"
php artisan migrate
```

## Features

- **Saga Pattern**: Orchestrated transactions with compensation
- **Workflow Engine**: State machines with guards
- **Event Sourcing**: Append-only event store
- **Versioning**: Immutable snapshots
- **Rule Engine**: AST-based rule evaluation
- **Sync Engine**: Multi-device synchronization
- **Dependency Engine**: Business constraint management

## Usage

### Saga Pattern

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$saga = BusinessOrchestration::saga()->startSaga('OrderProcessing', [
    'validate_order' => ValidateOrderStep::class,
    'charge_payment' => ChargePaymentStep::class,
    'ship_order' => ShipOrderStep::class,
], ['order_id' => 123]);
```

### Workflow

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$workflow = BusinessOrchestration::workflow();

$workflow->defineTransition('approve', 'pending', 'approved');
$workflow->defineTransition('reject', 'pending', 'rejected');

$builder = $workflow->for($contract);

if ($builder->can('approve')) {
    $builder->apply('approve');
}
```

### Event Sourcing

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$es = BusinessOrchestration::eventSourcing();

$es->storeEvent('order-123', 'OrderCreated', ['amount' => 100]);

$aggregate = $es->rebuildAggregate('order-123', new OrderAggregate());
```

### Versioning

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$version = BusinessOrchestration::version();

$version->snapshot($model);

$version->restore($model, 1);
```

### Rule Engine

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$ruleEngine = BusinessOrchestration::rule();

$rule = $ruleEngine->createRule('DiscountRule', [
    'type' => 'comparison',
    'left' => 'amount',
    'op' => '>',
    'right' => 500
], function($context) {
    // apply discount
});

if ($ruleEngine->evaluate($rule, ['amount' => 600])) {
    $ruleEngine->executeAction($rule, ['amount' => 600]);
}
```

### Sync Engine

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$sync = BusinessOrchestration::sync();

$sync->logChange($model, 'UPDATE', ['status' => 'shipped']);

$deltas = $sync->getDeltas('App\\Models\\Order', 123, 5);
```

### Dependency Engine

```php
use Dimita\BusinessOrchestration\BusinessOrchestration;

$dep = BusinessOrchestration::dependency();

$dep->addDependency('App\\Models\\Order', 'App\\Models\\Product', 'cannot_delete_if_orders_exist');

if (!$dep->checkDeletion('App\\Models\\Product', 456)) {
    throw new Exception('Cannot delete product with existing orders');
}
```

## Architecture

The package provides a clean, human-friendly API with persistent state management, crash recovery, and multi-driver support (Database, Redis, Queue).

Each engine is designed for production use with proper error handling and compensation mechanisms.

## Changelog

- v1.0.0: Initial release with all core engines
- Added Executable contract for saga steps