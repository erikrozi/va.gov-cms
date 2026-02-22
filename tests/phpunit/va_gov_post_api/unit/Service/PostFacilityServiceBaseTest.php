<?php

namespace tests\phpunit\va_gov_post_api\unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\file\FileRepositoryInterface;
use Drupal\post_api\Service\AddToQueue;
use Drupal\va_gov_post_api\Service\PostFacilityServiceBase;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PostFacilityServiceBase service.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_post_api\Service\PostFacilityServiceBase
 */
class PostFacilityServiceBaseTest extends VaGovUnitTestBase {

  /**
   * A concrete instance of the abstract PostFacilityServiceBase class.
   *
   * @var \Drupal\va_gov_post_api\Service\PostFacilityServiceBase
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
    $renderer = $this->createMock(Renderer::class);
    $fileSystem = $this->createMock(FileSystemInterface::class);
    $fileRepository = $this->createMock(FileRepositoryInterface::class);

    // Create a concrete anonymous subclass of PostFacilityServiceBase.
    // PostFacilityServiceBase is abstract but has no abstract methods.
    $this->service = new class(
      $configFactory,
      $entityTypeManager,
      $loggerChannelFactory,
      $messenger,
      $postQueue,
      $renderer,
      $fileSystem,
      $fileRepository
    ) extends PostFacilityServiceBase {};
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
   * Tests makeLinksVaGov replaces relative links with va.gov URLs.
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovRelativeLinks() {
    $html = '<a href="/health-care">Health Care</a>';
    $result = $this->invokeProtectedMethod('makeLinksVaGov', [$html]);
    $this->assertEquals('<a href="https://www.va.gov/health-care">Health Care</a>', $result);
  }

  /**
   * Tests makeLinksVaGov replaces file paths and then adds domain.
   *
   * The str_replace first converts /sites/default/files/ to /files/,
   * then converts href="/ to href="https://www.va.gov/.
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovFilePaths() {
    $html = '<a href="/sites/default/files/2024-01/document.pdf">Download</a>';
    $result = $this->invokeProtectedMethod('makeLinksVaGov', [$html]);
    $this->assertEquals('<a href="https://www.va.gov/files/2024-01/document.pdf">Download</a>', $result);
  }

  /**
   * Tests makeLinksVaGov does not alter absolute URLs.
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovAbsoluteUrlsUnchanged() {
    $html = '<a href="https://www.example.com/page">External</a>';
    $result = $this->invokeProtectedMethod('makeLinksVaGov', [$html]);
    $this->assertEquals($html, $result);
  }

  /**
   * Tests makeLinksVaGov with multiple links.
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovMultipleLinks() {
    $html = '<a href="/page-one">One</a> and <a href="/page-two">Two</a>';
    $result = $this->invokeProtectedMethod('makeLinksVaGov', [$html]);
    $this->assertStringContainsString('href="https://www.va.gov/page-one"', $result);
    $this->assertStringContainsString('href="https://www.va.gov/page-two"', $result);
  }

  /**
   * Tests makeLinksVaGov with empty HTML.
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovEmpty() {
    $result = $this->invokeProtectedMethod('makeLinksVaGov', ['']);
    $this->assertEquals('', $result);
  }

  /**
   * Tests makeLinksVaGov with plain text (no links).
   *
   * @covers ::makeLinksVaGov
   */
  public function testMakeLinksVaGovPlainText() {
    $html = 'This is just text without any links.';
    $result = $this->invokeProtectedMethod('makeLinksVaGov', [$html]);
    $this->assertEquals($html, $result);
  }

  /**
   * Tests getFacilityAddress populates an address object.
   *
   * GetFacilityAddress takes $address by reference, so we use
   * ReflectionMethod::getClosure() to call it directly.
   *
   * @covers ::getFacilityAddress
   */
  public function testGetFacilityAddress() {
    $address = new \stdClass();
    $use_address = [
      'address_line1' => '123 Main St',
      'address_line2' => 'Suite 100',
      'locality' => 'Springfield',
      'administrative_area' => 'IL',
      'postal_code' => '62701',
      'country_code' => 'US',
    ];

    $reflection = new \ReflectionMethod($this->service, 'getFacilityAddress');
    $reflection->setAccessible(TRUE);
    $closure = $reflection->getClosure($this->service);
    $result = $closure($address, $use_address);
    $this->assertEquals('123 Main St', $result->address_line1);
    $this->assertEquals('Suite 100', $result->address_line2);
    $this->assertEquals('Springfield', $result->city);
    $this->assertEquals('IL', $result->state);
    $this->assertEquals('62701', $result->zip_code);
    $this->assertEquals('US', $result->country_code);
  }

  /**
   * Tests isPushable returns false when serviceTerm is empty.
   *
   * @covers ::isPushable
   */
  public function testIsPushableNoServiceTerm() {
    $result = $this->invokeProtectedMethod('isPushable', []);
    $this->assertFalse($result);
  }

}
