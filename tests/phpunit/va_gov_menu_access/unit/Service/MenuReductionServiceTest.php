<?php

namespace tests\phpunit\va_gov_menu_access\unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\va_gov_lovell\LovellOps;
use Drupal\va_gov_menu_access\Service\MenuReductionService;
use Drupal\va_gov_user\Service\UserPermsService;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the MenuReductionService.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_menu_access\Service\MenuReductionService
 */
class MenuReductionServiceTest extends VaGovUnitTestBase {

  /**
   * The MenuReductionService instance.
   *
   * @var \Drupal\va_gov_menu_access\Service\MenuReductionService
   */
  private $service;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    $immutableConfig = $this->createMock(ImmutableConfig::class);
    // Provide config values that buildMenuAccessRules() will parse.
    $immutableConfig->method('get')->willReturnMap([
      [
        'va_gov_menu_access.paths',
        "/some-vamc-path%\n/another-path%\n/disabled-parent~%\n/parent-with-children!%",
      ],
      [
        'va_gov_menu_access.locked_paths',
        "/locked-path%\n/locked-wildcard/*%\n/single-locked-path",
      ],
    ]);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($immutableConfig);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $aliasManager = $this->createMock(AliasManagerInterface::class);
    $userPermsService = $this->createMock(UserPermsService::class);

    $this->service = new MenuReductionService(
      $configFactory,
      $entityTypeManager,
      $aliasManager,
      $userPermsService
    );
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
   * Helper to set a protected property.
   *
   * @param string $propertyName
   *   The property name.
   * @param mixed $value
   *   The value to set.
   */
  private function setProtectedProperty(string $propertyName, $value): void {
    $reflection = new \ReflectionProperty($this->service, $propertyName);
    $reflection->setAccessible(TRUE);
    $reflection->setValue($this->service, $value);
  }

  /**
   * Tests removeWildCard removes specified wildcard character.
   *
   * @covers ::removeWildCard
   */
  public function testRemoveWildCardPercent() {
    $result = $this->invokeProtectedMethod('removeWildCard', ['%', '/some-path%']);
    $this->assertEquals('/some-path', $result);
  }

  /**
   * Tests removeWildCard removes tilde wildcard.
   *
   * @covers ::removeWildCard
   */
  public function testRemoveWildCardTilde() {
    $result = $this->invokeProtectedMethod('removeWildCard', ['~', '/disabled-parent~']);
    $this->assertEquals('/disabled-parent', $result);
  }

  /**
   * Tests removeWildCard removes exclamation wildcard.
   *
   * @covers ::removeWildCard
   */
  public function testRemoveWildCardExclamation() {
    $result = $this->invokeProtectedMethod('removeWildCard', ['!', '/parent-with-children!']);
    $this->assertEquals('/parent-with-children', $result);
  }

  /**
   * Tests removeWildCard trims whitespace.
   *
   * @covers ::removeWildCard
   */
  public function testRemoveWildCardTrimsWhitespace() {
    $result = $this->invokeProtectedMethod('removeWildCard', ['%', '  /some-path%  ']);
    $this->assertEquals('/some-path', $result);
  }

  /**
   * Tests extractMenuIds extracts UUIDs from menu parent options.
   *
   * @covers ::extractMenuIds
   */
  public function testExtractMenuIdsTypical() {
    $options = [
      'pittsburgh-health-care:menu_link_content:abc-123-def' => 'Pittsburgh VA',
      'pittsburgh-health-care:menu_link_content:ghi-456-jkl' => '-- Locations',
    ];

    $result = $this->invokeProtectedMethod('extractMenuIds', [$options]);
    $this->assertArrayHasKey('abc-123-def', $result);
    $this->assertArrayHasKey('ghi-456-jkl', $result);
    $this->assertEquals('Pittsburgh VA', $result['abc-123-def']['option']);
    $this->assertEquals('-- Locations', $result['ghi-456-jkl']['option']);
    $this->assertEquals(0, $result['abc-123-def']['key_count']);
    $this->assertEquals(1, $result['ghi-456-jkl']['key_count']);
  }

  /**
   * Tests extractMenuIds with empty options.
   *
   * @covers ::extractMenuIds
   */
  public function testExtractMenuIdsEmpty() {
    $result = $this->invokeProtectedMethod('extractMenuIds', [[]]);
    $this->assertEmpty($result);
  }

  /**
   * Tests extractMenuIds skips entries without menu_link_content.
   *
   * @covers ::extractMenuIds
   */
  public function testExtractMenuIdsSkipsNonMenuContent() {
    $options = [
      'pittsburgh-health-care:' => '<Pittsburgh health care>',
      'pittsburgh-health-care:menu_link_content:abc-123' => 'Valid Item',
    ];

    $result = $this->invokeProtectedMethod('extractMenuIds', [$options]);
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('abc-123', $result);
  }

  /**
   * Tests isLockedPath returns TRUE for wildcard path match.
   *
   * @covers ::isLockedPath
   */
  public function testIsLockedPathWildcardMatch() {
    $result = $this->invokeProtectedMethod('isLockedPath', ['/locked-wildcard/some-page']);
    $this->assertTrue($result);
  }

  /**
   * Tests isLockedPath returns TRUE for exact locked path match.
   *
   * @covers ::isLockedPath
   */
  public function testIsLockedPathExactMatch() {
    $result = $this->invokeProtectedMethod('isLockedPath', ['/locked-path']);
    $this->assertTrue($result);
  }

  /**
   * Tests isLockedPath returns TRUE for single locked path.
   *
   * @covers ::isLockedPath
   */
  public function testIsLockedPathSingleLockedPath() {
    $result = $this->invokeProtectedMethod('isLockedPath', ['/single-locked-path']);
    $this->assertTrue($result);
  }

  /**
   * Tests isLockedPath returns FALSE for non-matching path.
   *
   * @covers ::isLockedPath
   */
  public function testIsLockedPathNoMatch() {
    $result = $this->invokeProtectedMethod('isLockedPath', ['/some-other-path']);
    $this->assertFalse($result);
  }

  /**
   * Tests isLockedPath returns FALSE for empty alias.
   *
   * @covers ::isLockedPath
   */
  public function testIsLockedPathEmpty() {
    $result = $this->invokeProtectedMethod('isLockedPath', ['']);
    $this->assertFalse($result);
  }

  /**
   * Tests checkForSimpleMatch returns ENABLED for matching alias.
   *
   * @covers ::checkForSimpleMatch
   */
  public function testCheckForSimpleMatchReturnsEnabled() {
    $result = $this->invokeProtectedMethod('checkForSimpleMatch', ['/some-vamc-path']);
    $this->assertEquals(MenuReductionService::ENABLED, $result);
  }

  /**
   * Tests checkForSimpleMatch returns NULL for non-matching alias.
   *
   * @covers ::checkForSimpleMatch
   */
  public function testCheckForSimpleMatchReturnsNull() {
    $result = $this->invokeProtectedMethod('checkForSimpleMatch', ['/completely-different-path']);
    $this->assertNull($result);
  }

  /**
   * Tests checkForTypeDisabledParent returns DISABLED for matching alias.
   *
   * @covers ::checkForTypeDisabledParent
   */
  public function testCheckForTypeDisabledParentReturnsDisabled() {
    $result = $this->invokeProtectedMethod('checkForTypeDisabledParent', ['/disabled-parent']);
    $this->assertEquals(MenuReductionService::DISABLED, $result);
  }

  /**
   * Tests checkForTypeDisabledParent returns NULL for non-matching alias.
   *
   * @covers ::checkForTypeDisabledParent
   */
  public function testCheckForTypeDisabledParentReturnsNull() {
    $result = $this->invokeProtectedMethod('checkForTypeDisabledParent', ['/some-other-path']);
    $this->assertNull($result);
  }

  /**
   * Tests checkForLovellSubSystem returns LOVELLSYS for Tricare path.
   *
   * @covers ::checkForLovellSubSystem
   */
  public function testCheckForLovellSubSystemTricare() {
    $result = $this->invokeProtectedMethod('checkForLovellSubSystem', ['/' . LovellOps::TRICARE_PATH]);
    $this->assertEquals(MenuReductionService::LOVELLSYS, $result);
  }

  /**
   * Tests checkForLovellSubSystem returns LOVELLSYS for VA path.
   *
   * @covers ::checkForLovellSubSystem
   */
  public function testCheckForLovellSubSystemVa() {
    $result = $this->invokeProtectedMethod('checkForLovellSubSystem', ['/' . LovellOps::VA_PATH]);
    $this->assertEquals(MenuReductionService::LOVELLSYS, $result);
  }

  /**
   * Tests checkForLovellSubSystem returns NULL for non-Lovell path.
   *
   * @covers ::checkForLovellSubSystem
   */
  public function testCheckForLovellSubSystemReturnsNull() {
    $result = $this->invokeProtectedMethod('checkForLovellSubSystem', ['/some-other-path']);
    $this->assertNull($result);
  }

  /**
   * Tests checkForDisabledParentWithChildren returns DISABLED for parent.
   *
   * @covers ::checkForDisabledParentWithChildren
   */
  public function testCheckForDisabledParentWithChildrenParent() {
    $result = $this->invokeProtectedMethod('checkForDisabledParentWithChildren', ['/parent-with-children']);
    $this->assertEquals(MenuReductionService::DISABLED, $result);
  }

  /**
   * Tests checkForDisabledParentWithChildren returns ENABLED for child.
   *
   * @covers ::checkForDisabledParentWithChildren
   */
  public function testCheckForDisabledParentWithChildrenChild() {
    $result = $this->invokeProtectedMethod('checkForDisabledParentWithChildren', ['/parent-with-children/child-page']);
    $this->assertEquals(MenuReductionService::ENABLED, $result);
  }

  /**
   * Tests checkForDisabledParentWithChildren returns NULL for non-match.
   *
   * @covers ::checkForDisabledParentWithChildren
   */
  public function testCheckForDisabledParentWithChildrenNoMatch() {
    $result = $this->invokeProtectedMethod('checkForDisabledParentWithChildren', ['/some-other-path']);
    $this->assertNull($result);
  }

  /**
   * Tests isCurrentMenuParentDisabled returns TRUE when Disabled is in label.
   *
   * @covers ::isCurrentMenuParentDisabled
   */
  public function testIsCurrentMenuParentDisabledTrue() {
    $form = [
      'menu' => [
        'link' => [
          'menu_parent' => [
            '#options' => [
              'menu:parent-123' => 'Some Parent | Disabled',
            ],
          ],
        ],
      ],
    ];
    $result = MenuReductionService::isCurrentMenuParentDisabled($form, 'menu:parent-123');
    $this->assertTrue($result);
  }

  /**
   * Tests isCurrentMenuParentDisabled returns FALSE for enabled items.
   *
   * @covers ::isCurrentMenuParentDisabled
   */
  public function testIsCurrentMenuParentDisabledFalse() {
    $form = [
      'menu' => [
        'link' => [
          'menu_parent' => [
            '#options' => [
              'menu:parent-123' => 'Some Parent',
            ],
          ],
        ],
      ],
    ];
    $result = MenuReductionService::isCurrentMenuParentDisabled($form, 'menu:parent-123');
    $this->assertFalse($result);
  }

  /**
   * Tests isCurrentMenuParentDisabled returns FALSE for missing key.
   *
   * @covers ::isCurrentMenuParentDisabled
   */
  public function testIsCurrentMenuParentDisabledMissingKey() {
    $form = [
      'menu' => [
        'link' => [
          'menu_parent' => [
            '#options' => [],
          ],
        ],
      ],
    ];
    $result = MenuReductionService::isCurrentMenuParentDisabled($form, 'menu:nonexistent');
    $this->assertFalse($result);
  }

  /**
   * Tests getMenuItemType returns Lovell type first (priority).
   *
   * @covers ::getMenuItemType
   */
  public function testGetMenuItemTypeLovellPriority() {
    $result = $this->invokeProtectedMethod('getMenuItemType', ['/' . LovellOps::TRICARE_PATH]);
    $this->assertEquals(MenuReductionService::LOVELLSYS, $result);
  }

  /**
   * Tests getMenuItemType returns ENABLED for simple match.
   *
   * @covers ::getMenuItemType
   */
  public function testGetMenuItemTypeSimpleMatch() {
    $result = $this->invokeProtectedMethod('getMenuItemType', ['/some-vamc-path']);
    $this->assertEquals(MenuReductionService::ENABLED, $result);
  }

  /**
   * Tests getMenuItemType returns NULL for empty alias.
   *
   * @covers ::getMenuItemType
   */
  public function testGetMenuItemTypeEmptyAlias() {
    $result = $this->invokeProtectedMethod('getMenuItemType', ['']);
    $this->assertNull($result);
  }

  /**
   * Tests getMenuItemType returns NULL for non-matching alias.
   *
   * @covers ::getMenuItemType
   */
  public function testGetMenuItemTypeNoMatch() {
    $result = $this->invokeProtectedMethod('getMenuItemType', ['/completely-unrelated-path']);
    $this->assertNull($result);
  }

  /**
   * Tests buildMenuAccessRules populates menuRules correctly.
   *
   * @covers ::buildMenuAccessRules
   */
  public function testBuildMenuAccessRulesPopulatesRules() {
    $reflection = new \ReflectionProperty($this->service, 'menuRules');
    $reflection->setAccessible(TRUE);
    $rules = $reflection->getValue($this->service);

    // Check universal_parent_menu_items parsed correctly.
    $this->assertNotEmpty($rules['universal_parent_menu_items']);
    $this->assertContains('/some-vamc-path', $rules['universal_parent_menu_items']);
    $this->assertContains('/another-path', $rules['universal_parent_menu_items']);

    // Check universal_locked_paths parsed correctly.
    $this->assertNotEmpty($rules['universal_locked_paths']);
    $this->assertContains('/locked-path', $rules['universal_locked_paths']);

    // Check single_locked_paths parsed correctly.
    $this->assertNotEmpty($rules['single_locked_paths']);
    $this->assertContains('/single-locked-path', $rules['single_locked_paths']);
  }

  /**
   * Tests isCurrentMenuParentDisabledAndLocked returns TRUE.
   *
   * @covers ::isCurrentMenuParentDisabledAndLocked
   */
  public function testIsCurrentMenuParentDisabledAndLockedTrue() {
    $this->setProtectedProperty('currentMenuParent', 'menu:parent-123');
    $form = [
      'menu' => [
        'link' => [
          'menu_parent' => [
            '#options' => [
              'menu:parent-123' => 'Some Parent | Disabled no-link',
            ],
          ],
        ],
      ],
    ];
    $result = $this->invokeProtectedMethod('isCurrentMenuParentDisabledAndLocked', [$form]);
    $this->assertTrue($result);
  }

  /**
   * Tests isCurrentMenuParentDisabledAndLocked returns FALSE.
   *
   * @covers ::isCurrentMenuParentDisabledAndLocked
   */
  public function testIsCurrentMenuParentDisabledAndLockedFalse() {
    $this->setProtectedProperty('currentMenuParent', 'menu:parent-123');
    $form = [
      'menu' => [
        'link' => [
          'menu_parent' => [
            '#options' => [
              'menu:parent-123' => 'Some Parent',
            ],
          ],
        ],
      ],
    ];
    $result = $this->invokeProtectedMethod('isCurrentMenuParentDisabledAndLocked', [$form]);
    $this->assertFalse($result);
  }

}
