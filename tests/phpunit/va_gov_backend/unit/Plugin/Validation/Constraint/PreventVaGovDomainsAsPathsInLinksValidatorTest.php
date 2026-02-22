<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventVaGovDomainsAsPathsInLinks;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventVaGovDomainsAsPathsInLinksValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the PreventVaGovDomainsAsPathsInLinksValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventVaGovDomainsAsPathsInLinksValidator
 */
class PreventVaGovDomainsAsPathsInLinksValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventVaGovDomainsAsPathsInLinks
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\PreventVaGovDomainsAsPathsInLinksValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new PreventVaGovDomainsAsPathsInLinks();
    $this->validator = new PreventVaGovDomainsAsPathsInLinksValidator();
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
   * Tests text with normal URL passes validation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithNormalUrl() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit https://www.va.gov/page for info.', $this->constraint, 0);
  }

  /**
   * Tests text with VA.gov domain as path triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithVaGovDomainAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit "/va.gov/something" for info.', $this->constraint, 0);
  }

  /**
   * Tests text with www.va.gov as path triggers a violation.
   *
   * @covers ::validateText
   */
  public function testValidateTextWithWwwVaGovDomainAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateText('Visit "/www.va.gov/something-else" for info.', $this->constraint, 0);
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
   * Tests HTML with VA.gov domain as path triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithVaGovDomainAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/va.gov/something">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with www.va.gov as path triggers a violation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithWwwVaGovAsPath() {
    $context = $this->createMockContextExpectingViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/www.va.gov/page">VA.gov</a></p>', $this->constraint, 0);
  }

  /**
   * Tests HTML with va.gov in a deeper path segment passes validation.
   *
   * @covers ::validateHtml
   */
  public function testValidateHtmlWithVaGovInDeeperPathSegment() {
    $context = $this->createMockContextExpectingNoViolation();
    $this->validator->setContext($context);
    $this->validator->validateHtml('<p><a href="/some-path/www.va.gov/">link</a></p>', $this->constraint, 0);
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
