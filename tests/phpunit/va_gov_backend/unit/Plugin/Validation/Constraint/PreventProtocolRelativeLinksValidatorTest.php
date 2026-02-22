<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventProtocolRelativeLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventProtocolRelativeLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventProtocolRelativeLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventProtocolRelativeLinksValidator
 */
class PreventProtocolRelativeLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventProtocolRelativeLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventProtocolRelativeLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventProtocolRelativeLinks();
    $this->validator = new PreventProtocolRelativeLinksValidator();
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
   * Tests text with a normal URL passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests text with protocol-relative URL triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithProtocolRelativeUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit //www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests HTML with normal URL passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="https://www.va.gov/page">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with protocol-relative URL triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithProtocolRelativeUrl() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="//www.va.gov/page">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with triple slash (local file) is skipped.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithTripleSlashPasses() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="///some/local/file">file</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with normal relative path passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNormalRelativePath() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/some-page">Page</a></p>', $this->constraint, 0);
  }

}
