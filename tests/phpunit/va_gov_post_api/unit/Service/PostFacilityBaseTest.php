<?php

namespace tests\phpunit\va_gov_post_api\unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\post_api\Service\AddToQueue;
use Drupal\va_gov_post_api\Service\PostFacilityBase;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PostFacilityBase service.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_post_api\Service\PostFacilityBase
 */
class PostFacilityBaseTest extends VaGovUnitTestBase {

  /**
   * A concrete instance of the abstract PostFacilityBase class.
   *
   * @var \Drupal\va_gov_post_api\Service\PostFacilityBase
   */
  private $service;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $immutableConfig = $this->createMock(ImmutableConfig::class);
    $immutableConfig->method('get')->willReturn(NULL);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($immutableConfig);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $loggerChannelFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $messenger = $this->createMock(MessengerInterface::class);
    $postQueue = $this->createMock(AddToQueue::class);

    // Create a concrete anonymous subclass of PostFacilityBase.
    $this->service = new class(
      $configFactory,
      $entityTypeManager,
      $loggerChannelFactory,
      $messenger,
      $postQueue
    ) extends PostFacilityBase {};
  }

  /**
   * Helper to invoke a protected method.
   *
   * @param string $methodName
   *   The method name.
   * @param array $args
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  private function invokeProtectedMethod(string $methodName, array $args = []) {
    $reflection = new \ReflectionMethod($this->service, $methodName);
    $reflection->setAccessible(TRUE);
    return $reflection->invokeArgs($this->service, $args);
  }

  /**
   * Tests normalizeTime with AM/PM replacements.
   *
   * @covers ::normalizeTime
   */
  public function testNormalizeTimeAmPm() {
    $this->assertEquals('08:00 a.m.', $this->invokeProtectedMethod('normalizeTime', ['08:00 am']));
    $this->assertEquals('08:00 a.m.', $this->invokeProtectedMethod('normalizeTime', ['08:00 AM']));
    $this->assertEquals('05:00 p.m.', $this->invokeProtectedMethod('normalizeTime', ['05:00 pm']));
    $this->assertEquals('05:00 p.m.', $this->invokeProtectedMethod('normalizeTime', ['05:00 PM']));
    $this->assertEquals('05:00 a.m.', $this->invokeProtectedMethod('normalizeTime', ['05:00 A.M.']));
    $this->assertEquals('05:00 p.m.', $this->invokeProtectedMethod('normalizeTime', ['05:00 P.M.']));
  }

  /**
   * Tests normalizeTime trims whitespace.
   *
   * @covers ::normalizeTime
   */
  public function testNormalizeTimeTrimsWhitespace() {
    $this->assertEquals('08:00 a.m.', $this->invokeProtectedMethod('normalizeTime', ['  08:00 am  ']));
  }

  /**
   * Tests normalizeTime with empty string.
   *
   * @covers ::normalizeTime
   */
  public function testNormalizeTimeEmpty() {
    $this->assertEquals('', $this->invokeProtectedMethod('normalizeTime', ['']));
  }

  /**
   * Tests midnightNoonify with midnight.
   *
   * @covers ::midnightNoonify
   */
  public function testMidnightNoonifyMidnight() {
    $this->assertEquals('midnight', $this->invokeProtectedMethod('midnightNoonify', ['12:00 a.m.']));
    $this->assertEquals('midnight', $this->invokeProtectedMethod('midnightNoonify', ['12:00 am']));
  }

  /**
   * Tests midnightNoonify with noon.
   *
   * @covers ::midnightNoonify
   */
  public function testMidnightNoonifyNoon() {
    $this->assertEquals('noon', $this->invokeProtectedMethod('midnightNoonify', ['12:00 p.m.']));
    $this->assertEquals('noon', $this->invokeProtectedMethod('midnightNoonify', ['12:00 pm']));
  }

  /**
   * Tests midnightNoonify with regular time.
   *
   * @covers ::midnightNoonify
   */
  public function testMidnightNoonifyRegularTime() {
    $this->assertEquals('03:00 pm', $this->invokeProtectedMethod('midnightNoonify', ['03:00 pm']));
    $this->assertEquals('08:30 am', $this->invokeProtectedMethod('midnightNoonify', ['08:30 am']));
  }

  /**
   * Tests midnightNoonify trims whitespace.
   *
   * @covers ::midnightNoonify
   */
  public function testMidnightNoonifyTrimsWhitespace() {
    $this->assertEquals('midnight', $this->invokeProtectedMethod('midnightNoonify', [' 12:00 am ']));
  }

  /**
   * Tests getFrontEndFragment with typical strings.
   *
   * @covers ::getFrontEndFragment
   */
  public function testGetFrontEndFragmentTypical() {
    $this->assertEquals('#primary-care', $this->service->getFrontEndFragment('Primary Care'));
    $this->assertEquals('#mental-health', $this->service->getFrontEndFragment('Mental Health'));
  }

  /**
   * Tests getFrontEndFragment strips special characters.
   *
   * @covers ::getFrontEndFragment
   */
  public function testGetFrontEndFragmentStripsSpecialChars() {
    $this->assertEquals('#womens-health', $this->service->getFrontEndFragment("Women's Health"));
    $this->assertEquals('#covid-19-vaccines', $this->service->getFrontEndFragment('COVID-19 Vaccines'));
  }

  /**
   * Tests getFrontEndFragment truncates to 30 characters.
   *
   * @covers ::getFrontEndFragment
   */
  public function testGetFrontEndFragmentTruncates() {
    $result = $this->service->getFrontEndFragment('This Is A Really Long Service Name That Should Be Truncated');
    // 30 chars + "#" prefix.
    $this->assertLessThanOrEqual(31, strlen($result));
  }

  /**
   * Tests getFrontEndFragment with empty string.
   *
   * @covers ::getFrontEndFragment
   */
  public function testGetFrontEndFragmentEmpty() {
    $this->assertEquals('', $this->service->getFrontEndFragment(''));
    $this->assertEquals('', $this->service->getFrontEndFragment('  '));
  }

  /**
   * Tests getFrontEndFragment removes trailing hyphens.
   *
   * @covers ::getFrontEndFragment
   */
  public function testGetFrontEndFragmentNoTrailingHyphens() {
    $result = $this->service->getFrontEndFragment('Test--');
    $this->assertStringEndsNotWith('-', $result);
  }

  /**
   * Tests stringNullify returns NULL for empty strings.
   *
   * @covers ::stringNullify
   */
  public function testStringNullifyEmpty() {
    $this->assertNull($this->invokeProtectedMethod('stringNullify', ['']));
    $this->assertNull($this->invokeProtectedMethod('stringNullify', [NULL]));
  }

  /**
   * Tests stringNullify returns the string for non-empty values.
   *
   * @covers ::stringNullify
   */
  public function testStringNullifyNonEmpty() {
    $this->assertEquals('hello', $this->invokeProtectedMethod('stringNullify', ['hello']));
    $this->assertEquals('some text', $this->invokeProtectedMethod('stringNullify', ['some text']));
  }

  /**
   * Tests stringNullify returns NULL for '0' since PHP empty('0') is true.
   *
   * @covers ::stringNullify
   */
  public function testStringNullifyZeroString() {
    $this->assertNull($this->invokeProtectedMethod('stringNullify', ['0']));
  }

  /**
   * Tests normalizeHoursComment trims whitespace.
   *
   * @covers ::normalizeHoursComment
   */
  public function testNormalizeHoursComment() {
    $this->assertEquals('closed', $this->invokeProtectedMethod('normalizeHoursComment', ['  closed  ']));
    $this->assertEquals('', $this->invokeProtectedMethod('normalizeHoursComment', ['']));
  }

  /**
   * Tests getAddresses with a single address.
   *
   * @covers ::getAddresses
   */
  public function testGetAddressesSingle() {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([
      [
        'organization' => 'VA Hospital',
        'address_line1' => '123 Main St',
        'address_line2' => 'Suite 100',
        'locality' => 'Springfield',
        'administrative_area' => 'IL',
        'country_code' => 'US',
        'postal_code' => '62701',
      ],
    ]);

    $result = $this->invokeProtectedMethod('getAddresses', [$field]);
    $this->assertIsObject($result);
    $this->assertEquals('VA Hospital', $result->address_organization);
    $this->assertEquals('123 Main St', $result->address_line1);
    $this->assertEquals('Suite 100', $result->address_line2);
    $this->assertEquals('Springfield', $result->city);
    $this->assertEquals('IL', $result->state);
    $this->assertEquals('US', $result->country_code);
    $this->assertEquals('62701', $result->zip_code);
  }

  /**
   * Tests getAddresses with multiple addresses.
   *
   * @covers ::getAddresses
   */
  public function testGetAddressesMultiple() {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([
      [
        'organization' => 'VA Hospital',
        'address_line1' => '123 Main St',
        'address_line2' => '',
        'locality' => 'Springfield',
        'administrative_area' => 'IL',
        'country_code' => 'US',
        'postal_code' => '62701',
      ],
      [
        'organization' => 'VA Clinic',
        'address_line1' => '456 Oak Ave',
        'address_line2' => '',
        'locality' => 'Chicago',
        'administrative_area' => 'IL',
        'country_code' => 'US',
        'postal_code' => '60601',
      ],
    ]);

    $result = $this->invokeProtectedMethod('getAddresses', [$field]);
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertEquals('VA Hospital', $result[0]->address_organization);
    $this->assertEquals('VA Clinic', $result[1]->address_organization);
  }

  /**
   * Tests getDay returns comment only when no start/end hours.
   *
   * @covers ::getDay
   */
  public function testGetDayCommentOnly() {
    $days = [
      0 => [
        'starthours' => '',
        'endhours' => '',
        'comment' => 'Closed',
      ],
    ];
    $result = $this->invokeProtectedMethod('getDay', [0, $days]);
    $this->assertEquals('Closed', $result);
  }

  /**
   * Tests getDay returns empty string when no data.
   *
   * @covers ::getDay
   */
  public function testGetDayEmpty() {
    $days = [
      0 => [
        'starthours' => '',
        'endhours' => '',
        'comment' => '',
      ],
    ];
    $result = $this->invokeProtectedMethod('getDay', [0, $days]);
    $this->assertEquals('', $result);
  }

}
