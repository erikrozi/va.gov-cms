<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventPreviewUrlLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventPreviewUrlLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventPreviewUrlLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventPreviewUrlLinksValidator
 */
class PreventPreviewUrlLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventPreviewUrlLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventPreviewUrlLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventPreviewUrlLinks();
    $this->validator = new PreventPreviewUrlLinksValidator();
  }

  /**
   * Creates a mock context that expects no violation.
   *
   * @return \Symfony\Component\Validator\Context\ExecutionContextInterface
   *   The mock context.
   */
  private function createMockContextExpectingNoViolation(): ExecutionContextInterface {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('buildViolation');
    return $context;
  }

  /**
   * Creates a mock context that expects a violation.
   *
   * @return \Symfony\Component\Validator\Context\ExecutionContextInterface
   *   The mock context.
   */
  private function createMockContextExpectingViolation(): ExecutionContextInterface {
    $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->method('atPath')->willReturnSelf();
    $violationBuilder->method('addViolation')->willReturn(NULL);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('buildViolation')
      ->willReturn($violationBuilder);
    return $context;
  }

  /**
   * Tests text without preview URLs passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNoPreviewUrls() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://www.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests text with staging preview URL triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithStagingPreviewUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://preview-staging.vfs.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests text with prod preview URL triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithProdPreviewUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://preview-prod.vfs.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests text with http staging preview URL triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithHttpStagingPreviewUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit http://preview-staging.vfs.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests HTML without preview URLs passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNoPreviewUrls() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p>Visit <a href="https://www.va.gov/page">VA.gov</a>.</p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with staging preview URL triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithStagingPreviewUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="https://preview-staging.vfs.va.gov/page">link</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with prod preview URL triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithProdPreviewUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="https://preview-prod.vfs.va.gov/page">link</a></p>', $this->constraint, 0);
  }

}
