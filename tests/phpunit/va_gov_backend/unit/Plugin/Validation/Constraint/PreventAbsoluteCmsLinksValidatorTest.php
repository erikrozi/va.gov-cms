<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteCmsLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteCmsLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventAbsoluteCmsLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteCmsLinksValidator
 */
class PreventAbsoluteCmsLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteCmsLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteCmsLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventAbsoluteCmsLinks();
    $this->validator = new PreventAbsoluteCmsLinksValidator();
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
   * Tests that plain text without CMS links passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNoCmsLinks() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('This is just regular text with no links.', $this->constraint, 0);
  }

  /**
   * Tests that plain text with a CMS link triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithCmsLink() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://cms.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests that plain text with an http CMS link triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithHttpCmsLink() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit http://cms.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests that plain text with a protocol-relative CMS link triggers violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithProtocolRelativeCmsLink() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit //cms.va.gov/some-page for more info.', $this->constraint, 0);
  }

  /**
   * Tests that HTML without CMS links passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNoCmsLinks() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p>This is <a href="https://www.va.gov/page">a normal link</a>.</p>', $this->constraint, 0);
  }

  /**
   * Tests that HTML with a CMS link triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithCmsLink() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p>Visit <a href="https://cms.va.gov/some-page">this page</a>.</p>', $this->constraint, 0);
  }

  /**
   * Tests that HTML with no anchor tags but CMS text passes.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithCmsTextButNoLink() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p>Visit <a href="https://www.va.gov">va.gov</a>.</p>', $this->constraint, 0);
  }

}
