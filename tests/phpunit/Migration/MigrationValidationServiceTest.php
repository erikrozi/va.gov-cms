<?php

namespace tests\phpunit\Migration;

use Drupal\va_gov_migrate\Service\MigrationValidationService;
use Tests\Support\Classes\VaGovExistingSiteBase;

/**
 * Tests for MigrationValidationService.
 *
 * @group functional
 * @group all
 * @group migration_validation
 */
class MigrationValidationServiceTest extends VaGovExistingSiteBase {

  /**
   * The migration validation service.
   *
   * @var \Drupal\va_gov_migrate\Service\MigrationValidationService
   */
  protected $validationService;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->validationService = $this->container->get('va_gov_migrate.migration_validation');
  }

  /**
   * Tests that the service is properly instantiated.
   */
  public function testServiceExists(): void {
    $this->assertInstanceOf(MigrationValidationService::class, $this->validationService);
  }

  /**
   * Tests validation of a valid facility API JSON response.
   */
  public function testValidFacilityApiResponse(): void {
    $json = file_get_contents(__DIR__ . '/fixtures/health_care_local_facility.json');
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertTrue($result['valid'], 'Valid facility JSON should pass validation. Errors: ' . implode(', ', $result['errors']));
    $this->assertSame(1, $result['facility_count']);
    $this->assertEmpty($result['errors']);
  }

  /**
   * Tests validation of the VBA facility fixture.
   */
  public function testValidVbaFacilityApiResponse(): void {
    $json = file_get_contents(__DIR__ . '/fixtures/vba_facility.json');
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertTrue($result['valid'], 'VBA facility JSON should pass validation. Errors: ' . implode(', ', $result['errors']));
    $this->assertSame(2, $result['facility_count']);
    $this->assertEmpty($result['errors']);
  }

  /**
   * Tests validation of the NCA facility fixture.
   */
  public function testValidNcaFacilityApiResponse(): void {
    $json = file_get_contents(__DIR__ . '/fixtures/nca_facility.json');
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertTrue($result['valid'], 'NCA facility JSON should pass validation. Errors: ' . implode(', ', $result['errors']));
    $this->assertSame(1, $result['facility_count']);
    $this->assertEmpty($result['errors']);
  }

  /**
   * Tests validation of the Vet Centers facility fixture.
   */
  public function testValidVetCentersFacilityApiResponse(): void {
    $json = file_get_contents(__DIR__ . '/fixtures/vet_centers_facility.json');
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertTrue($result['valid'], 'Vet Centers facility JSON should pass validation. Errors: ' . implode(', ', $result['errors']));
    $this->assertSame(2, $result['facility_count']);
    $this->assertEmpty($result['errors']);
  }

  /**
   * Tests validation of the multi-type facility fixture.
   */
  public function testValidMultiTypeFacilityApiResponse(): void {
    $json = file_get_contents(__DIR__ . '/fixtures/facility_api_all_types.json');
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertTrue($result['valid'], 'Multi-type facility JSON should pass validation. Errors: ' . implode(', ', $result['errors']));
    $this->assertSame(6, $result['facility_count']);
    $this->assertEmpty($result['errors']);
  }

  /**
   * Tests that invalid JSON is properly caught.
   */
  public function testInvalidJson(): void {
    $result = $this->validationService->validateFacilityApiResponse('not valid json{{{');
    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('Invalid JSON', $result['errors'][0]);
  }

  /**
   * Tests that missing data key is caught.
   */
  public function testMissingDataKey(): void {
    $result = $this->validationService->validateFacilityApiResponse('{"facilities": []}');
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('Missing or invalid "data" key', $result['errors'][0]);
  }

  /**
   * Tests that empty data array is caught.
   */
  public function testEmptyDataArray(): void {
    $result = $this->validationService->validateFacilityApiResponse('{"data": []}');
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('empty', $result['errors'][0]);
  }

  /**
   * Tests that missing required fields generate errors.
   */
  public function testMissingRequiredFields(): void {
    $json = json_encode([
      'data' => [
        [
          'id' => '',
          'attributes' => [
            'name' => '',
            'facilityType' => '',
          ],
        ],
      ],
    ]);
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertFalse($result['valid']);
    $this->assertGreaterThanOrEqual(3, count($result['errors']));
  }

  /**
   * Tests that invalid lat/long values generate errors.
   */
  public function testInvalidGeolocation(): void {
    $json = json_encode([
      'data' => [
        [
          'id' => 'test_001',
          'attributes' => [
            'name' => 'Test Facility',
            'facilityType' => 'va_health_facility',
            'lat' => 999,
            'long' => -999,
            'timeZone' => 'America/New_York',
            'address' => [
              'physical' => [
                'address1' => '123 Test St',
                'city' => 'Test',
                'state' => 'VA',
                'zip' => '12345',
              ],
            ],
            'phone' => ['main' => '1234567890'],
            'hours' => [
              'monday' => '8-5',
              'tuesday' => '8-5',
              'wednesday' => '8-5',
              'thursday' => '8-5',
              'friday' => '8-5',
              'saturday' => 'Closed',
              'sunday' => 'Closed',
            ],
          ],
        ],
      ],
    ]);
    $result = $this->validationService->validateFacilityApiResponse($json);
    $this->assertFalse($result['valid']);
    $error_text = implode(' ', $result['errors']);
    $this->assertStringContainsString('latitude', $error_text);
    $this->assertStringContainsString('longitude', $error_text);
  }

  /**
   * Tests validation of the standard forms CSV fixture.
   */
  public function testValidFormsCsv(): void {
    $csv_path = __DIR__ . '/fixtures/forms.csv';
    $result = $this->validationService->validateFormsCsv($csv_path);
    // Single row CSV will have warnings about low count but should still
    // structurally parse. The row itself should not produce errors.
    $this->assertSame(1, $result['row_count']);
  }

  /**
   * Tests validation of the multi-row forms CSV fixture.
   */
  public function testValidMultiFormsCsv(): void {
    $csv_path = __DIR__ . '/fixtures/forms_multi.csv';
    $result = $this->validationService->validateFormsCsv($csv_path);
    $this->assertSame(7, $result['row_count']);
    // The multi CSV has valid data, so should not produce hard errors
    // from structural checks. Header validation will pass since all fields
    // from migration config are present.
    $this->assertTrue($result['valid'], 'Multi-row forms CSV should pass validation. Errors: ' . implode(', ', $result['errors']));
  }

  /**
   * Tests that a nonexistent CSV file is properly caught.
   */
  public function testNonexistentCsvFile(): void {
    $result = $this->validationService->validateFormsCsv('/tmp/nonexistent_file.csv');
    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('not found', $result['errors'][0]);
  }

  /**
   * Tests that an empty CSV file is properly caught.
   */
  public function testEmptyCsvFile(): void {
    $empty_file = tempnam(sys_get_temp_dir(), 'test_csv_');
    file_put_contents($empty_file, '');
    $result = $this->validationService->validateFormsCsv($empty_file);
    $this->assertFalse($result['valid']);
    unlink($empty_file);
  }

  /**
   * Tests that a CSV with only headers (no data rows) is caught.
   */
  public function testHeaderOnlyCsv(): void {
    $header_only_file = tempnam(sys_get_temp_dir(), 'test_csv_');
    file_put_contents($header_only_file, '"FormNum","FormTitle","FileName","Orientation","PageSize","Pages","IntranetOnly","SignatureRequired","WebPrint","WebFilledPrint","ElectronicSubmit","OwnerId","Comments","InstallationDate","Deleted","DeletedDate","Usage","ReasonCode","PackagingCode","SupplierCode","SubmitParameters","SubmitType","FormType","OnlineFormURL","filename_accelio","link_name","revision_date","issue_date","displayName","rowid","prefix","formkeywords"' . "\n");
    $result = $this->validationService->validateFormsCsv($header_only_file);
    $this->assertFalse($result['valid']);
    $this->assertSame(0, $result['row_count']);
    $error_text = implode(' ', $result['errors']);
    $this->assertStringContainsString('no data rows', $error_text);
    unlink($header_only_file);
  }

  /**
   * Tests that duplicate rowids in CSV produce errors.
   */
  public function testDuplicateRowIds(): void {
    $csv_content = '"FormNum","FormTitle","FileName","Orientation","PageSize","Pages","IntranetOnly","SignatureRequired","WebPrint","WebFilledPrint","ElectronicSubmit","OwnerId","Comments","InstallationDate","Deleted","DeletedDate","Usage","ReasonCode","PackagingCode","SupplierCode","SubmitParameters","SubmitType","FormType","OnlineFormURL","filename_accelio","link_name","revision_date","issue_date","displayName","rowid","prefix","formkeywords"' . "\n";
    $csv_content .= '"100","Form A","A.pdf","0","8.5 x 11","1","0","0","1","1","0","0","","2021-01-01 00:00:00","","","","","","","","","VHA","","FALSE","","01/2021","2021-01-01 00:00:00","10-A","12345","10","test"' . "\n";
    $csv_content .= '"200","Form B","B.pdf","0","8.5 x 11","1","0","0","1","1","0","0","","2021-01-01 00:00:00","","","","","","","","","VHA","","FALSE","","01/2021","2021-01-01 00:00:00","10-B","12345","10","test"' . "\n";

    $temp_file = tempnam(sys_get_temp_dir(), 'test_csv_');
    file_put_contents($temp_file, $csv_content);
    $result = $this->validationService->validateFormsCsv($temp_file);
    $this->assertFalse($result['valid']);
    $error_text = implode(' ', $result['errors']);
    $this->assertStringContainsString('Duplicate rowid', $error_text);
    unlink($temp_file);
  }

  /**
   * Tests that invalid FormType values produce errors.
   */
  public function testInvalidFormType(): void {
    $csv_content = '"FormNum","FormTitle","FileName","Orientation","PageSize","Pages","IntranetOnly","SignatureRequired","WebPrint","WebFilledPrint","ElectronicSubmit","OwnerId","Comments","InstallationDate","Deleted","DeletedDate","Usage","ReasonCode","PackagingCode","SupplierCode","SubmitParameters","SubmitType","FormType","OnlineFormURL","filename_accelio","link_name","revision_date","issue_date","displayName","rowid","prefix","formkeywords"' . "\n";
    $csv_content .= '"100","Form A","A.pdf","0","8.5 x 11","1","0","0","1","1","0","0","","2021-01-01 00:00:00","","","","","","","","","INVALID","","FALSE","","01/2021","2021-01-01 00:00:00","10-A","54321","10","test"' . "\n";

    $temp_file = tempnam(sys_get_temp_dir(), 'test_csv_');
    file_put_contents($temp_file, $csv_content);
    $result = $this->validationService->validateFormsCsv($temp_file);
    $this->assertFalse($result['valid']);
    $error_text = implode(' ', $result['errors']);
    $this->assertStringContainsString('Invalid FormType', $error_text);
    unlink($temp_file);
  }

  /**
   * Tests migration results validation for a non-existent migration.
   */
  public function testValidateMigrationResultsNoTable(): void {
    $result = $this->validationService->validateMigrationResults('nonexistent_migration_xyz');
    $this->assertFalse($result['valid']);
    // Should have an error about 0 imported items.
    $error_text = implode(' ', $result['errors']);
    $this->assertStringContainsString('0 imported items', $error_text);
  }

}
