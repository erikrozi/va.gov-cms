<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventDomainsAsPathsInLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventDomainsAsPathsInLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventDomainsAsPathsInLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventDomainsAsPathsInLinksValidator
 */
class PreventDomainsAsPathsInLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventDomainsAsPathsInLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventDomainsAsPathsInLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventDomainsAsPathsInLinks();
    $this->validator = new PreventDomainsAsPathsInLinksValidator();
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
   * Tests text without domain-as-path passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://www.navy.mil/page for info.', $this->constraint, 0);
  }

  /**
   * Tests text with domain as path triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithDomainAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit "/www.navy.mil/something" for info.', $this->constraint, 0);
  }

  /**
   * Tests text with www domain preceded by whitespace triggers violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithDomainAsPathWhitespace() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit /www.whitehouse.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests HTML without domain-as-path passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="https://www.navy.mil/page">Navy</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with domain as path triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithDomainAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/www.navy.mil/something">Navy</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with normal relative path passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNormalRelativePath() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/some-path">Page</a></p>', $this->constraint, 0);
  }

}
