<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidNumeric;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidNumericValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the ValidNumericValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidNumericValidator
 */
class ValidNumericValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidNumeric
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidNumericValidator
   */
  private $validator;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new ValidNumeric();
    $this->validator = new ValidNumericValidator();
  }

  /**
   * Creates a mock iterable of items with the given values.
   *
   * @param array $values
   *   An array of string values.
   *
   * @return \ArrayIterator
   *   An iterable of mock items.
   */
  private function createMockItems(array $values): \ArrayIterator {
    $items = [];
    foreach ($values as $value) {
      $item = new \stdClass();
      $item->value = $value;
      $items[] = $item;
    }
    return new \ArrayIterator($items);
  }

  /**
   * Tests that a numeric value passes validation.
   *
   * @covers ::validate
   */
  public function testValidNumericValue() {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('buildViolation');
    $this->validator->setContext($context);
    $this->validator->validate($this->createMockItems(['12345']), $this->constraint);
  }

  /**
   * Tests that a non-numeric value triggers a violation.
   *
   * @covers ::validate
   */
  public function testInvalidNonNumericValue() {
    $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->method('atPath')->willReturnSelf();
    $violationBuilder->method('addViolation')->willReturn(NULL);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('buildViolation')
      ->with($this->constraint->notANumber)
      ->willReturn($violationBuilder);
    $this->validator->setContext($context);
    $this->validator->validate($this->createMockItems(['abc']), $this->constraint);
  }

  /**
   * Tests that a value with mixed digits and letters triggers a violation.
   *
   * @covers ::validate
   */
  public function testInvalidMixedValue() {
    $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
    $violationBuilder->method('atPath')->willReturnSelf();
    $violationBuilder->method('addViolation')->willReturn(NULL);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->once())
      ->method('buildViolation')
      ->willReturn($violationBuilder);
    $this->validator->setContext($context);
    $this->validator->validate($this->createMockItems(['123abc']), $this->constraint);
  }

  /**
   * Tests that zero passes validation.
   *
   * @covers ::validate
   */
  public function testValidZero() {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('buildViolation');
    $this->validator->setContext($context);
    $this->validator->validate($this->createMockItems(['0']), $this->constraint);
  }

  /**
   * Tests that multiple valid items all pass.
   *
   * @covers ::validate
   */
  public function testMultipleValidItems() {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($this->never())
      ->method('buildViolation');
    $this->validator->setContext($context);
    $this->validator->validate($this->createMockItems(['123', '456', '789']), $this->constraint);
  }

}
