<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteUrlsAsPathsInLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteUrlsAsPathsInLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventAbsoluteUrlsAsPathsInLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteUrlsAsPathsInLinksValidator
 */
class PreventAbsoluteUrlsAsPathsInLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteUrlsAsPathsInLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventAbsoluteUrlsAsPathsInLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventAbsoluteUrlsAsPathsInLinks();
    $this->validator = new PreventAbsoluteUrlsAsPathsInLinksValidator();
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
   * Tests text without absolute URLs as paths passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests text with absolute URL as path triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithAbsoluteUrlAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit /https://www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests text with http URL as path triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithHttpAbsoluteUrlAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit /http://www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests HTML without absolute URLs as paths passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="https://www.va.gov/page">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with absolute URL as path triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithAbsoluteUrlAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/https://www.va.gov/page">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with a path alias starting with /http does NOT trigger.
   *
   * The false-alarm guard correctly skips paths like /https-better-than-http
   * because they contain //https: in the resolved URL, indicating a legitimate
   * path alias rather than an absolute URL used as a path.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithPathAliasStartingWithHttpNoViolation() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/https-better-than-http">article</a></p>', $this->constraint, 0);
  }

}
