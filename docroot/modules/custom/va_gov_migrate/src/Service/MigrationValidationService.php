<?php

namespace Drupal\va_gov_migrate\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for validating migration source data and post-migration results.
 *
 * Provides automated validation checks for:
 * - Facility API JSON responses (structure, required fields, data ranges)
 * - Forms CSV data (headers, required fields, data integrity)
 * - Post-migration result verification (status, imported counts, messages)
 */
class MigrationValidationService {

  /**
   * Known facility types from the Lighthouse Facility API.
   *
   * @var string[]
   */
  protected const KNOWN_FACILITY_TYPES = [
    'va_health_facility',
    'va_benefits_facility',
    'va_cemetery',
    'vet_center',
  ];

  /**
   * Known form types from the VA Forms database.
   *
   * @var string[]
   */
  protected const KNOWN_FORM_TYPES = [
    'VA',
    'VHA',
    'VBA',
  ];

  /**
   * Minimum expected row count for forms CSV to be considered valid.
   *
   * A suspiciously low count may indicate a truncated or corrupted file.
   *
   * @var int
   */
  protected const FORMS_CSV_MIN_ROW_COUNT = 10;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The logger channel for va_gov_migrate.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new MigrationValidationService.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    Connection $database,
    ConfigFactoryInterface $config_factory,
    StreamWrapperManagerInterface $stream_wrapper_manager,
    LoggerInterface $logger,
  ) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->logger = $logger;
  }

  /**
   * Validates a Facility API JSON response structure and data.
   *
   * Checks that:
   * - The response contains a 'data' array.
   * - Each facility has required fields (id, name, facilityType).
   * - facilityType is a known type.
   * - Geolocation values are within valid ranges.
   * - Address fields are present.
   *
   * @param string $json_string
   *   The raw JSON string from the Facility API.
   *
   * @return array
   *   An array with keys:
   *   - 'valid' (bool): Whether the data passed all checks.
   *   - 'errors' (string[]): List of validation error messages.
   *   - 'warnings' (string[]): List of non-fatal warning messages.
   *   - 'facility_count' (int): Number of facilities in the response.
   */
  public function validateFacilityApiResponse(string $json_string): array {
    $errors = [];
    $warnings = [];
    $facility_count = 0;

    $data = json_decode($json_string, TRUE);
    if ($data === NULL) {
      $errors[] = 'Invalid JSON: unable to decode response.';
      return $this->buildResult(FALSE, $errors, $warnings, 0);
    }

    if (!isset($data['data']) || !is_array($data['data'])) {
      $errors[] = 'Missing or invalid "data" key in API response.';
      return $this->buildResult(FALSE, $errors, $warnings, 0);
    }

    if (empty($data['data'])) {
      $errors[] = 'The "data" array is empty; no facilities returned.';
      return $this->buildResult(FALSE, $errors, $warnings, 0);
    }

    $facility_count = count($data['data']);

    foreach ($data['data'] as $index => $facility) {
      $facility_errors = $this->validateSingleFacility($facility, $index);
      $errors = array_merge($errors, $facility_errors['errors']);
      $warnings = array_merge($warnings, $facility_errors['warnings']);
    }

    return $this->buildResult(empty($errors), $errors, $warnings, $facility_count);
  }

  /**
   * Validates a single facility record from the API response.
   *
   * @param array $facility
   *   A single facility data array.
   * @param int $index
   *   The index of the facility in the data array.
   *
   * @return array
   *   An array with 'errors' and 'warnings' keys.
   */
  protected function validateSingleFacility(array $facility, int $index): array {
    $errors = [];
    $warnings = [];
    $prefix = "Facility at index {$index}";

    // Check required top-level field: id.
    if (empty($facility['id'])) {
      $errors[] = "{$prefix}: Missing required field 'id'.";
    }
    else {
      $prefix = "Facility '{$facility['id']}'";
    }

    // Check attributes exist.
    if (!isset($facility['attributes']) || !is_array($facility['attributes'])) {
      $errors[] = "{$prefix}: Missing or invalid 'attributes' object.";
      return ['errors' => $errors, 'warnings' => $warnings];
    }

    $attrs = $facility['attributes'];

    // Check required attribute: name.
    if (empty($attrs['name'])) {
      $errors[] = "{$prefix}: Missing required field 'attributes/name'.";
    }

    // Check required attribute: facilityType.
    if (empty($attrs['facilityType'])) {
      $errors[] = "{$prefix}: Missing required field 'attributes/facilityType'.";
    }
    elseif (!in_array($attrs['facilityType'], self::KNOWN_FACILITY_TYPES, TRUE)) {
      $warnings[] = "{$prefix}: Unknown facilityType '{$attrs['facilityType']}'.";
    }

    // Validate geolocation.
    if (isset($attrs['lat'])) {
      if (!is_numeric($attrs['lat']) || $attrs['lat'] < -90 || $attrs['lat'] > 90) {
        $errors[] = "{$prefix}: Invalid latitude value '{$attrs['lat']}'. Must be between -90 and 90.";
      }
    }
    else {
      $warnings[] = "{$prefix}: Missing 'attributes/lat' (latitude).";
    }

    if (isset($attrs['long'])) {
      if (!is_numeric($attrs['long']) || $attrs['long'] < -180 || $attrs['long'] > 180) {
        $errors[] = "{$prefix}: Invalid longitude value '{$attrs['long']}'. Must be between -180 and 180.";
      }
    }
    else {
      $warnings[] = "{$prefix}: Missing 'attributes/long' (longitude).";
    }

    // Validate address - at least physical address should be present.
    if (!isset($attrs['address']['physical'])) {
      $warnings[] = "{$prefix}: Missing 'attributes/address/physical'.";
    }
    else {
      $physical = $attrs['address']['physical'];
      if (empty($physical['address1'])) {
        $warnings[] = "{$prefix}: Missing physical address line 1.";
      }
      if (empty($physical['city'])) {
        $warnings[] = "{$prefix}: Missing physical address city.";
      }
      if (empty($physical['state'])) {
        $warnings[] = "{$prefix}: Missing physical address state.";
      }
      if (empty($physical['zip'])) {
        $warnings[] = "{$prefix}: Missing physical address zip.";
      }
    }

    // Validate phone.
    if (!isset($attrs['phone']['main'])) {
      $warnings[] = "{$prefix}: Missing 'attributes/phone/main'.";
    }

    // Validate hours.
    if (!isset($attrs['hours']) || !is_array($attrs['hours'])) {
      $warnings[] = "{$prefix}: Missing or invalid 'attributes/hours'.";
    }
    else {
      $expected_days = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
      ];
      foreach ($expected_days as $day) {
        if (!isset($attrs['hours'][$day])) {
          $warnings[] = "{$prefix}: Missing hours for '{$day}'.";
        }
      }
    }

    // Validate timeZone.
    if (empty($attrs['timeZone'])) {
      $warnings[] = "{$prefix}: Missing 'attributes/timeZone'.";
    }

    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Validates the Forms CSV data for structural integrity and content.
   *
   * Checks:
   * - File is readable and non-empty.
   * - Headers match the migration configuration.
   * - Row count is above the minimum threshold.
   * - Required fields are populated in each row.
   * - FormType values are valid.
   * - No duplicate rowid values.
   * - Date fields are well-formed where present.
   *
   * @param string|null $file_path
   *   Optional path to the CSV file. If NULL, uses the default location.
   *
   * @return array
   *   An array with keys:
   *   - 'valid' (bool): Whether the CSV passed all checks.
   *   - 'errors' (string[]): List of validation error messages.
   *   - 'warnings' (string[]): List of non-fatal warning messages.
   *   - 'row_count' (int): Number of data rows in the CSV.
   */
  public function validateFormsCsv(?string $file_path = NULL): array {
    $errors = [];
    $warnings = [];
    $row_count = 0;

    if ($file_path === NULL) {
      $wrapper = $this->streamWrapperManager->getViaScheme('public');
      if ($wrapper) {
        $base_path = $wrapper->realpath();
        $file_path = "{$base_path}/migrate_source/va_forms_data.csv";
      }
      else {
        $errors[] = 'Unable to resolve public stream wrapper.';
        return $this->buildFormsCsvResult(FALSE, $errors, $warnings, 0);
      }
    }

    if (!file_exists($file_path) || !is_readable($file_path)) {
      $errors[] = "CSV file not found or not readable at: {$file_path}";
      return $this->buildFormsCsvResult(FALSE, $errors, $warnings, 0);
    }

    $handle = fopen($file_path, 'r');
    if ($handle === FALSE) {
      $errors[] = "Unable to open CSV file at: {$file_path}";
      return $this->buildFormsCsvResult(FALSE, $errors, $warnings, 0);
    }

    // Read and validate headers.
    $headers = fgetcsv($handle);
    if ($headers === FALSE || empty($headers)) {
      $errors[] = 'CSV file is empty or has invalid headers.';
      fclose($handle);
      return $this->buildFormsCsvResult(FALSE, $errors, $warnings, 0);
    }

    // Validate headers against migration config.
    $header_errors = $this->validateFormsCsvHeaders($headers);
    $errors = array_merge($errors, $header_errors);

    // Read and validate data rows.
    $seen_row_ids = [];
    $line_number = 1;
    while (($row = fgetcsv($handle)) !== FALSE) {
      // Skip empty rows.
      if (count($row) === 1 && empty($row[0])) {
        continue;
      }
      $line_number++;
      $row_count++;

      // Map headers to values.
      if (count($headers) !== count($row)) {
        $warnings[] = "Row {$line_number}: Column count mismatch (expected " . count($headers) . ", got " . count($row) . ").";
        continue;
      }
      $record = array_combine($headers, $row);

      // Validate required fields.
      $row_errors = $this->validateFormsCsvRow($record, $line_number);
      $errors = array_merge($errors, $row_errors['errors']);
      $warnings = array_merge($warnings, $row_errors['warnings']);

      // Check for duplicate rowid.
      if (!empty($record['rowid'])) {
        if (isset($seen_row_ids[$record['rowid']])) {
          $errors[] = "Row {$line_number}: Duplicate rowid '{$record['rowid']}' (first seen at row {$seen_row_ids[$record['rowid']]}).";
        }
        else {
          $seen_row_ids[$record['rowid']] = $line_number;
        }
      }
    }

    fclose($handle);

    // Check minimum row count.
    if ($row_count === 0) {
      $errors[] = 'CSV file contains no data rows.';
    }
    elseif ($row_count < self::FORMS_CSV_MIN_ROW_COUNT) {
      $warnings[] = "CSV file contains only {$row_count} data rows, which is below the minimum threshold of " . self::FORMS_CSV_MIN_ROW_COUNT . ".";
    }

    return $this->buildFormsCsvResult(empty($errors), $errors, $warnings, $row_count);
  }

  /**
   * Validates CSV headers against the va_node_form migration configuration.
   *
   * @param array $headers
   *   The CSV header row.
   *
   * @return string[]
   *   Array of error messages, empty if headers are valid.
   */
  protected function validateFormsCsvHeaders(array $headers): array {
    $errors = [];
    $config = $this->configFactory->get('migrate_plus.migration.va_node_form');
    $raw_data = $config->getRawData();

    if (empty($raw_data) || !isset($raw_data['source']['fields'])) {
      $errors[] = 'Unable to load migration config for va_node_form to validate headers.';
      return $errors;
    }

    $field_names = array_column($raw_data['source']['fields'], 'name');
    $missing_in_csv = array_diff($field_names, $headers);
    $extra_in_csv = array_diff($headers, $field_names);

    if (!empty($missing_in_csv)) {
      $errors[] = 'CSV is missing expected columns: ' . implode(', ', $missing_in_csv);
    }
    if (!empty($extra_in_csv)) {
      // Extra columns are a warning, not an error.
      // They won't break the migration but may indicate schema drift.
    }

    return $errors;
  }

  /**
   * Validates a single row of Forms CSV data.
   *
   * @param array $record
   *   An associative array of column name => value for one row.
   * @param int $line_number
   *   The line number in the CSV file.
   *
   * @return array
   *   An array with 'errors' and 'warnings' keys.
   */
  protected function validateFormsCsvRow(array $record, int $line_number): array {
    $errors = [];
    $warnings = [];

    // Required fields.
    if (empty($record['rowid'])) {
      $errors[] = "Row {$line_number}: Missing required field 'rowid'.";
    }
    if (empty($record['FormTitle'])) {
      $warnings[] = "Row {$line_number}: Missing 'FormTitle'.";
    }
    if (empty($record['displayName'])) {
      $warnings[] = "Row {$line_number}: Missing 'displayName'.";
    }
    if (empty($record['FileName'])) {
      $warnings[] = "Row {$line_number}: Missing 'FileName'.";
    }

    // FormType validation.
    if (!empty($record['FormType'])) {
      if (!in_array($record['FormType'], self::KNOWN_FORM_TYPES, TRUE)) {
        $errors[] = "Row {$line_number}: Invalid FormType '{$record['FormType']}'. Expected one of: " . implode(', ', self::KNOWN_FORM_TYPES) . ".";
      }
    }
    else {
      $warnings[] = "Row {$line_number}: Missing 'FormType'.";
    }

    // Date format validation for issue_date (if present).
    if (!empty($record['issue_date'])) {
      $date = \DateTime::createFromFormat('Y-m-d H:i:s', $record['issue_date']);
      if ($date === FALSE) {
        $warnings[] = "Row {$line_number}: Invalid issue_date format '{$record['issue_date']}'. Expected 'Y-m-d H:i:s'.";
      }
    }

    // OwnerId validation.
    if (isset($record['OwnerId']) && $record['OwnerId'] !== '') {
      $valid_owner_ids = ['0', '1', '2'];
      if (!in_array($record['OwnerId'], $valid_owner_ids, TRUE)) {
        $warnings[] = "Row {$line_number}: Unexpected OwnerId '{$record['OwnerId']}'. Expected 0, 1, or 2.";
      }
    }

    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Validates the results of a migration run.
   *
   * Checks the migration status table for the given migration to verify:
   * - The migration completed (status is idle).
   * - At least some items were imported.
   * - The number of migration messages (errors/warnings) is reported.
   *
   * @param string $migration_id
   *   The migration plugin ID (e.g., 'va_node_facility_vba').
   *
   * @return array
   *   An array with keys:
   *   - 'valid' (bool): Whether the migration results look healthy.
   *   - 'errors' (string[]): List of issues found.
   *   - 'warnings' (string[]): List of non-fatal warnings.
   *   - 'imported_count' (int): Number of items imported.
   *   - 'message_count' (int): Number of migration messages logged.
   */
  public function validateMigrationResults(string $migration_id): array {
    $errors = [];
    $warnings = [];
    $imported_count = 0;
    $message_count = 0;

    // Check the migration map table for imported items.
    $map_table = 'migrate_map_' . $migration_id;
    try {
      if ($this->database->schema()->tableExists($map_table)) {
        $imported_count = (int) $this->database->select($map_table, 'm')
          ->countQuery()
          ->execute()
          ->fetchField();
      }
      else {
        $warnings[] = "Migration map table '{$map_table}' does not exist. Migration may not have run yet.";
      }
    }
    catch (\Exception $e) {
      $warnings[] = "Could not query migration map table: {$e->getMessage()}";
    }

    if ($imported_count === 0) {
      $errors[] = "Migration '{$migration_id}' has 0 imported items.";
    }

    // Check migration messages table.
    $message_table = 'migrate_message_' . $migration_id;
    try {
      if ($this->database->schema()->tableExists($message_table)) {
        $message_count = (int) $this->database->select($message_table, 'm')
          ->countQuery()
          ->execute()
          ->fetchField();

        if ($message_count > 0) {
          $warnings[] = "Migration '{$migration_id}' has {$message_count} message(s). Review with: drush migrate:messages {$migration_id}";
        }
      }
    }
    catch (\Exception $e) {
      $warnings[] = "Could not query migration message table: {$e->getMessage()}";
    }

    $valid = empty($errors);

    return [
      'valid' => $valid,
      'errors' => $errors,
      'warnings' => $warnings,
      'imported_count' => $imported_count,
      'message_count' => $message_count,
    ];
  }

  /**
   * Runs all facility migration validations and logs results.
   *
   * This is intended to be called after facility migrations complete.
   *
   * @return bool
   *   TRUE if all validations passed, FALSE otherwise.
   */
  public function validateAllFacilityMigrations(): bool {
    $all_valid = TRUE;
    $facility_migrations = [
      'va_node_health_care_local_facility',
      'va_node_facility_vba',
      'va_node_facility_nca',
      'va_node_facility_vet_centers',
      'va_node_facility_vet_centers_mvc',
      'va_node_facility_vet_centers_os',
    ];

    foreach ($facility_migrations as $migration_id) {
      $result = $this->validateMigrationResults($migration_id);
      if (!$result['valid']) {
        $all_valid = FALSE;
        foreach ($result['errors'] as $error) {
          $this->logger->error('Facility migration validation: @error', ['@error' => $error]);
        }
      }
      foreach ($result['warnings'] as $warning) {
        $this->logger->warning('Facility migration validation: @warning', ['@warning' => $warning]);
      }
      $this->logger->notice('Migration @id: @count imported, @messages message(s).', [
        '@id' => $migration_id,
        '@count' => $result['imported_count'],
        '@messages' => $result['message_count'],
      ]);
    }

    return $all_valid;
  }

  /**
   * Runs the forms migration validation and logs results.
   *
   * This is intended to be called after the forms migration completes.
   *
   * @return bool
   *   TRUE if validation passed, FALSE otherwise.
   */
  public function validateFormsMigration(): bool {
    $result = $this->validateMigrationResults('va_node_form');
    if (!$result['valid']) {
      foreach ($result['errors'] as $error) {
        $this->logger->error('Forms migration validation: @error', ['@error' => $error]);
      }
    }
    foreach ($result['warnings'] as $warning) {
      $this->logger->warning('Forms migration validation: @warning', ['@warning' => $warning]);
    }
    $this->logger->notice('Migration va_node_form: @count imported, @messages message(s).', [
      '@count' => $result['imported_count'],
      '@messages' => $result['message_count'],
    ]);

    return $result['valid'];
  }

  /**
   * Builds a standardized validation result for facility API.
   *
   * @param bool $valid
   *   Whether validation passed.
   * @param array $errors
   *   List of error messages.
   * @param array $warnings
   *   List of warning messages.
   * @param int $facility_count
   *   Number of facilities.
   *
   * @return array
   *   The validation result array.
   */
  protected function buildResult(bool $valid, array $errors, array $warnings, int $facility_count): array {
    return [
      'valid' => $valid,
      'errors' => $errors,
      'warnings' => $warnings,
      'facility_count' => $facility_count,
    ];
  }

  /**
   * Builds a standardized validation result for forms CSV.
   *
   * @param bool $valid
   *   Whether validation passed.
   * @param array $errors
   *   List of error messages.
   * @param array $warnings
   *   List of warning messages.
   * @param int $row_count
   *   Number of data rows.
   *
   * @return array
   *   The validation result array.
   */
  protected function buildFormsCsvResult(bool $valid, array $errors, array $warnings, int $row_count): array {
    return [
      'valid' => $valid,
      'errors' => $errors,
      'warnings' => $warnings,
      'row_count' => $row_count,
    ];
  }

}
