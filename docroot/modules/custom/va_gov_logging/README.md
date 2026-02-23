# VA.gov Logging Module

Standardized logging templates and helper utilities for VA.gov CMS integration engineers.

## Why Use This?

- **Consistent formatting**: All log messages follow the same structure with `@placeholder` syntax.
- **Correct severity levels**: Each method's docblock tells you exactly what alert it triggers.
- **Privacy by default**: Automatic PII sanitization prevents accidental username logging.
- **Task lifecycle**: Built-in start/complete/fail methods for scheduled tasks and migrations.
- **Works everywhere**: Injectable service for OOP code, static helper for `.module` files.

## Alert Routing Quick Reference

| Method | Drupal Level | Alert Action |
|--------|-------------|-------------|
| `emergency()` | Emergency | PagerDuty (Urgent) |
| `alert()` | Alert | PagerDuty (Urgent) |
| `critical()` | Critical | PagerDuty (Urgent) |
| `error()` | Error | Slack @here (High) |
| `warning()` | Warning | None |
| `notice()` | Notice | None |
| `info()` | Info | None |
| `debug()` | Debug | None |

> **Rule of thumb**: Use `error()` for failures that need human attention. Use `critical()`/`alert()`/`emergency()` only when the CMS is down or data loss is imminent. Use `warning()` or `notice()` for things that are noteworthy but not actionable right now.

## Usage in OOP Code (Services, Controllers, etc.)

### 1. Add the service as a dependency

In your module's `*.services.yml`:

```yaml
services:
  my_module.my_service:
    class: Drupal\my_module\Service\MyService
    arguments: ['@va_gov_logging.logger']
```

### 2. Inject and use in your class

```php
<?php

namespace Drupal\my_module\Service;

use Drupal\va_gov_logging\Logger\VaGovLogger;

class MyService {

  protected VaGovLogger $logger;

  public function __construct(VaGovLogger $logger) {
    $this->logger = $logger;
  }

  public function processData(): void {
    $this->logger->info('my_module', 'Starting data processing for @count items.', [
      '@count' => $itemCount,
    ]);

    try {
      // ... do work ...
      $this->logger->notice('my_module', 'Processed @count items successfully.', [
        '@count' => $itemCount,
      ]);
    }
    catch (\Exception $e) {
      // Logs at ERROR level with exception message and trace.
      $this->logger->exception('my_module', 'Data processing failed for batch @batch.', $e, [
        '@batch' => $batchId,
      ]);
    }
  }

}
```

## Usage in Procedural Code (.module files)

Use the `VaGovLog` static helper:

```php
use Drupal\va_gov_logging\Logger\VaGovLog;

function my_module_do_task() {
  VaGovLog::taskStarted('my_module', 'Daily CSV import');
  try {
    // ... do work ...
    VaGovLog::taskCompleted('my_module', 'Daily CSV import');
  }
  catch (\Exception $e) {
    VaGovLog::taskFailed('my_module', 'Daily CSV import', $e);
  }
}
```

## Common Patterns

### Logging an exception (recommended)

```php
// OOP:
$this->logger->exception('va_gov_post_api', 'Failed to push facility @id to Lighthouse.', $e, [
  '@id' => $facilityId,
]);

// Procedural:
VaGovLog::exception('va_gov_post_api', 'Failed to push facility @id to Lighthouse.', $e, [
  '@id' => $facilityId,
]);
```

This produces a log entry like:
```
Failed to push facility vha_442 to Lighthouse. Exception: Connection refused | Trace: #0 ...
```

### Logging an error without an exception

```php
VaGovLog::error('va_gov_post_api', 'HTTP @status received from Lighthouse for facility @id.', [
  '@status' => $response->getStatusCode(),
  '@id' => $facilityId,
]);
```

### Scheduled task lifecycle

```php
function va_gov_scheduled_tasks_my_task() {
  VaGovLog::taskStarted('va_gov_scheduled_tasks', 'My custom task');
  try {
    // ... do the work ...
    VaGovLog::taskCompleted('va_gov_scheduled_tasks', 'My custom task');
  }
  catch (\Exception $e) {
    VaGovLog::taskFailed('va_gov_scheduled_tasks', 'My custom task', $e);
  }
}
```

### Migration logging

```php
function my_module_run_migration(string $migrationId) {
  VaGovLog::taskStarted('my_module', "Migration $migrationId");
  try {
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migrationId);
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();
    VaGovLog::taskCompleted('my_module', "Migration $migrationId");
  }
  catch (\Exception $e) {
    VaGovLog::taskFailed('my_module', "Migration $migrationId", $e);
  }
}
```

### User-related logging (privacy-safe)

```php
// WRONG - logs PII (username):
VaGovLog::notice('my_module', 'User @username logged in.', [
  '@username' => $account->getAccountName(),
]);

// CORRECT - logs user ID only:
VaGovLog::notice('my_module', 'User @uid logged in.', [
  '@uid' => $account->id(),
]);
```

> **Note**: The logger automatically redacts known PII keys (`@username`, `@name`, `@account_name`, etc.) as a safety net, but you should still use user IDs in the first place.

### Overriding exception severity

```php
// Default: ERROR (Slack @here)
$this->logger->exception('my_module', 'API call failed.', $e);

// Critical system failure: CRITICAL (PagerDuty)
use Drupal\Core\Logger\RfcLogLevel;
$this->logger->exception('my_module', 'Database connection lost.', $e, [], RfcLogLevel::CRITICAL);

// Expected/recoverable: WARNING (no alert)
$this->logger->exception('my_module', 'Cache miss, rebuilding.', $e, [], RfcLogLevel::WARNING);
```

## Message Formatting

Always use Drupal's `@placeholder` syntax for variable interpolation:

```php
// CORRECT - safe interpolation via placeholders:
VaGovLog::error('my_module', 'Failed to process @type with ID @id.', [
  '@type' => $entity->getEntityTypeId(),
  '@id' => $entity->id(),
]);

// WRONG - direct string interpolation (unsafe, not translatable):
VaGovLog::error('my_module', "Failed to process {$entity->getEntityTypeId()} with ID {$entity->id()}.");
```

## Integration Notes

- **Datadog APM**: Messages are automatically enriched with trace/span IDs by the existing `DatadogApmProcessor`. No special formatting needed.
- **Sentry**: Errors are automatically reported to Sentry via the Raven module. Using the correct severity level ensures proper routing.
- **Existing loggers**: This module wraps `\Drupal::logger()` under the hood. All existing log processors and handlers continue to work.
