<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\BenefitsSelectionLimit;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\BenefitsSelectionLimitValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the BenefitsSelectionLimitValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\BenefitsSelectionLimitValidator
 */
class BenefitsSelectionLimitValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\BenefitsSelectionLimit
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\BenefitsSelectionLimitValidator
   */
  private $validator;

  /**
   * The execution context mock.
   *
   * @var \Symfony\Component\Validator\Context\ExecutionContextInterface
   */
  private $context;

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->constraint = new BenefitsSelectionLimit();
    $this->validator = new BenefitsSelectionLimitValidator();
    $this->context = $this->createMock(ExecutionContextInterface::class);
    $this->validator->initialize($this->context);
  }

  /**
   * Creates a mock countable item with the given count.
   *
   * @param int $count
   *   The count to return.
   *
   * @return object
   *   A mock countable object.
   */
  private function createMockCountableItem(int $count) {
    $item = $this->createMock(\Countable::class);
    $item->method('count')
      ->willReturn($count);
    return $item;
  }

  /**
   * Tests that zero items passes validation.
   *
   * @covers ::validate
   */
  public function testZeroItemsPasses() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockCountableItem(0), $this->constraint);
  }

  /**
   * Tests that one item passes validation.
   *
   * @covers ::validate
   */
  public function testOneItemPasses() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockCountableItem(1), $this->constraint);
  }

  /**
   * Tests that two items passes validation.
   *
   * @covers ::validate
   */
  public function testTwoItemsPasses() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockCountableItem(2), $this->constraint);
  }

  /**
   * Tests that three items triggers a violation.
   *
   * @covers ::validate
   */
  public function testThreeItemsFails() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->moreThanTwo);
    $this->validator->validate($this->createMockCountableItem(3), $this->constraint);
  }

  /**
   * Tests that five items triggers a violation.
   *
   * @covers ::validate
   */
  public function testFiveItemsFails() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->moreThanTwo);
    $this->validator->validate($this->createMockCountableItem(5), $this->constraint);
  }

}
