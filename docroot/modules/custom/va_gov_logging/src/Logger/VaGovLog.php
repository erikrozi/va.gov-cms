<?php

namespace Drupal\va_gov_logging\Logger;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Static logging helper for procedural code (.module files).
 *
 * This class provides static methods that wrap Drupal's logger with VA.gov
 * CMS conventions. Use this in .module files and other procedural code where
 * dependency injection is not available.
 *
 * For OOP code (services, controllers, etc.), prefer injecting the
 * VaGovLogger service via dependency injection instead.
 *
 * Usage:
 * @code
 * use Drupal\va_gov_logging\Logger\VaGovLog;
 *
 * // Simple messages at various severity levels:
 * VaGovLog::info('my_module', 'Task completed successfully.');
 * VaGovLog::error('my_module', 'Failed to download file: @url', ['@url' => $url]);
 *
 * // Logging exceptions:
 * try {
 *   $service->doSomething();
 * }
 * catch (\Exception $e) {
 *   VaGovLog::exception('my_module', 'Operation failed', $e);
 * }
 *
 * // Standardized task lifecycle:
 * VaGovLog::taskStarted('my_module', 'Daily CSV import');
 * try {
 *   run_import();
 *   VaGovLog::taskCompleted('my_module', 'Daily CSV import');
 * }
 * catch (\Exception $e) {
 *   VaGovLog::taskFailed('my_module', 'Daily CSV import', $e);
 * }
 * @endcode
 *
 * ## Alert Routing Reference
 *
 * Drupal log levels map to alert channels as follows:
 * - Emergency, Alert, Critical => Urgent (PagerDuty)
 * - Error => High (Slack @here)
 * - Warning, Notice => None
 * - Informational, Debug => None
 *
 * ## Privacy
 *
 * Never log usernames or other PII. Use user IDs instead.
 * Context arrays are automatically sanitized for common PII keys.
 *
 * @see \Drupal\va_gov_logging\Logger\VaGovLogger
 */
class VaGovLog {

  /**
   * Log an emergency message. Triggers PagerDuty (Urgent).
   *
   * Use for: System is unusable. CMS is completely down or data loss is
   * imminent. This will page on-call engineers immediately.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function emergency(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::EMERGENCY, $message, $context);
  }

  /**
   * Log an alert message. Triggers PagerDuty (Urgent).
   *
   * Use for: Immediate action required. A critical subsystem has failed
   * and needs human intervention right away.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function alert(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::ALERT, $message, $context);
  }

  /**
   * Log a critical message. Triggers PagerDuty (Urgent).
   *
   * Use for: Critical conditions such as an unexpected component failure
   * that affects core CMS functionality.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function critical(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::CRITICAL, $message, $context);
  }

  /**
   * Log an error message. Triggers Slack @here (High).
   *
   * Use for: Runtime errors that need attention but don't require immediate
   * paging. Failed API calls, broken integrations, or operation failures
   * that affect end users.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function error(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::ERROR, $message, $context);
  }

  /**
   * Log a warning message. No alert triggered.
   *
   * Use for: Potential problems that should be investigated but are not
   * immediately impactful. Deprecated API usage, approaching rate limits,
   * or unusual but non-critical behavior.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function warning(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::WARNING, $message, $context);
  }

  /**
   * Log a notice message. No alert triggered.
   *
   * Use for: Normal but significant events such as user logins,
   * configuration changes, or successful task completions that should be
   * part of the audit trail.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function notice(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::NOTICE, $message, $context);
  }

  /**
   * Log an informational message. No alert triggered.
   *
   * Use for: General operational messages that highlight progress of the
   * application. Task start/complete messages, successful operations, or
   * routine status updates.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function info(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::INFO, $message, $context);
  }

  /**
   * Log a debug message. No alert triggered.
   *
   * Use for: Detailed diagnostic information useful during development
   * or troubleshooting. Variable values, execution paths, or detailed
   * state information. Avoid in production-critical paths.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   The log message with @placeholder syntax for safe interpolation.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  public static function debug(string $channel, string $message, array $context = []): void {
    static::log($channel, RfcLogLevel::DEBUG, $message, $context);
  }

  /**
   * Log an exception with its message and stack trace.
   *
   * This is the preferred way to log caught exceptions in procedural code.
   * It automatically includes the exception message and a truncated trace.
   *
   * Default level is ERROR (Slack @here). Override $level for exceptions
   * that warrant a different severity:
   * - RfcLogLevel::CRITICAL for exceptions in critical subsystems (PagerDuty)
   * - RfcLogLevel::WARNING for expected/recoverable exceptions (no alert)
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_post_api').
   * @param string $message
   *   A descriptive message about what operation failed.
   * @param \Throwable $exception
   *   The caught exception or error.
   * @param array $context
   *   Additional context to include in the log entry.
   * @param int $level
   *   The log level. Defaults to RfcLogLevel::ERROR.
   */
  public static function exception(string $channel, string $message, \Throwable $exception, array $context = [], int $level = RfcLogLevel::ERROR): void {
    $context['@exception_message'] = $exception->getMessage();
    $trace = $exception->getTraceAsString();
    $context['@exception_trace'] = strlen($trace) > 2048
      ? substr($trace, 0, 2048) . '... [truncated]'
      : $trace;
    $formatted_message = $message . ' Exception: @exception_message | Trace: @exception_trace';
    static::log($channel, $level, $formatted_message, $context);
  }

  /**
   * Log a standardized "task started" message.
   *
   * Use this at the beginning of scheduled tasks, migrations, or other
   * long-running operations to create consistent start/end log pairs.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_scheduled_tasks').
   * @param string $taskName
   *   A human-readable name for the task (e.g., 'Download VA Forms CSV').
   */
  public static function taskStarted(string $channel, string $taskName): void {
    static::info($channel, 'Task "@task" started.', ['@task' => $taskName]);
  }

  /**
   * Log a standardized "task completed" message.
   *
   * Use this at the end of scheduled tasks, migrations, or other
   * long-running operations to create consistent start/end log pairs.
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_scheduled_tasks').
   * @param string $taskName
   *   A human-readable name for the task (e.g., 'Download VA Forms CSV').
   */
  public static function taskCompleted(string $channel, string $taskName): void {
    static::info($channel, 'Task "@task" completed.', ['@task' => $taskName]);
  }

  /**
   * Log a standardized "task failed" message with exception details.
   *
   * Use this in catch blocks for scheduled tasks, migrations, or other
   * long-running operations. Logs at ERROR level (Slack @here).
   *
   * @param string $channel
   *   The logger channel name (e.g., 'va_gov_scheduled_tasks').
   * @param string $taskName
   *   A human-readable name for the task (e.g., 'Download VA Forms CSV').
   * @param \Throwable $exception
   *   The caught exception.
   */
  public static function taskFailed(string $channel, string $taskName, \Throwable $exception): void {
    static::exception($channel, 'Task "@task" failed.', $exception, [
      '@task' => $taskName,
    ]);
  }

  /**
   * Core logging method that all static methods delegate to.
   *
   * Sanitizes context for PII, then delegates to Drupal's logger.
   *
   * @param string $channel
   *   The logger channel name.
   * @param int $level
   *   The RFC 5424 log level.
   * @param string $message
   *   The log message with @placeholder syntax.
   * @param array $context
   *   An associative array of placeholder replacements.
   */
  protected static function log(string $channel, int $level, string $message, array $context): void {
    $context = VaGovLogger::sanitizeContext($context);
    \Drupal::logger($channel)->log($level, $message, $context);
  }

}
