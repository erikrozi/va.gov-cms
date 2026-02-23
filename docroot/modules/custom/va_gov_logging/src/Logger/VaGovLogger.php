<?php

namespace Drupal\va_gov_logging\Logger;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;

/**
 * Standardized logging service for VA.gov CMS.
 *
 * This service provides consistent logging patterns with proper severity
 * levels, message formatting, and privacy safeguards. It wraps Drupal's
 * logger channel factory and enforces VA.gov CMS conventions.
 *
 * Inject this service via dependency injection in OOP code:
 * @code
 * services:
 *   my_module.my_service:
 *     class: Drupal\my_module\Service\MyService
 *     arguments: ['@va_gov_logging.logger']
 * @endcode
 *
 * For procedural code (.module files), use the static helper VaGovLog instead.
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
 * This service automatically sanitizes common PII keys in context arrays.
 *
 * @see \Drupal\va_gov_logging\Logger\VaGovLog
 */
class VaGovLogger {

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Context keys that may contain PII and should be sanitized.
   *
   * @var string[]
   */
  protected static $piiKeys = [
    '@username',
    '@user_name',
    '@name',
    '@account_name',
    '%username',
    '%user_name',
    '%name',
    '%account_name',
  ];

  /**
   * Constructs a VaGovLogger.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->loggerFactory = $logger_factory;
  }

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
  public function emergency(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::EMERGENCY, $message, $context);
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
  public function alert(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::ALERT, $message, $context);
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
  public function critical(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::CRITICAL, $message, $context);
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
  public function error(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::ERROR, $message, $context);
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
  public function warning(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::WARNING, $message, $context);
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
  public function notice(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::NOTICE, $message, $context);
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
  public function info(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::INFO, $message, $context);
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
  public function debug(string $channel, string $message, array $context = []): void {
    $this->log($channel, RfcLogLevel::DEBUG, $message, $context);
  }

  /**
   * Log an exception with its message and stack trace.
   *
   * This is the preferred way to log caught exceptions. It automatically
   * includes the exception message and a truncated stack trace.
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
  public function exception(string $channel, string $message, \Throwable $exception, array $context = [], int $level = RfcLogLevel::ERROR): void {
    $context['@exception_message'] = $exception->getMessage();
    $context['@exception_trace'] = $this->truncateTrace($exception->getTraceAsString());
    $formatted_message = $message . ' Exception: @exception_message | Trace: @exception_trace';
    $this->log($channel, $level, $formatted_message, $context);
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
  public function taskStarted(string $channel, string $taskName): void {
    $this->info($channel, 'Task "@task" started.', ['@task' => $taskName]);
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
  public function taskCompleted(string $channel, string $taskName): void {
    $this->info($channel, 'Task "@task" completed.', ['@task' => $taskName]);
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
  public function taskFailed(string $channel, string $taskName, \Throwable $exception): void {
    $this->exception($channel, 'Task "@task" failed.', $exception, [
      '@task' => $taskName,
    ]);
  }

  /**
   * Core logging method that all other methods delegate to.
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
  protected function log(string $channel, int $level, string $message, array $context): void {
    $context = static::sanitizeContext($context);
    $this->loggerFactory->get($channel)->log($level, $message, $context);
  }

  /**
   * Sanitize context array to prevent PII leakage.
   *
   * Replaces known PII-bearing keys (usernames, account names) with a
   * redaction notice. Use user IDs instead of usernames in your context.
   *
   * @param array $context
   *   The context array to sanitize.
   *
   * @return array
   *   The sanitized context array.
   *
   * @see https://github.com/department-of-veterans-affairs/va.gov-cms/blob/main/patches/VACMS-19177-removing-username-from-logging-for-externalauth.patch
   */
  public static function sanitizeContext(array $context): array {
    foreach (static::$piiKeys as $key) {
      if (isset($context[$key])) {
        $context[$key] = '[REDACTED - use user ID instead]';
      }
    }
    return $context;
  }

  /**
   * Truncate a stack trace to a reasonable length for logging.
   *
   * @param string $trace
   *   The full stack trace string.
   * @param int $maxLength
   *   Maximum character length. Defaults to 2048.
   *
   * @return string
   *   The truncated trace.
   */
  protected function truncateTrace(string $trace, int $maxLength = 2048): string {
    if (strlen($trace) <= $maxLength) {
      return $trace;
    }
    return substr($trace, 0, $maxLength) . '... [truncated]';
  }

}
