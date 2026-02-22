<?php

namespace tests\phpunit\va_gov_backend\unit\Plugin\Validation\Constraint;

use Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidPhoneNumber;
use Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidPhoneNumberValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Tests\Support\Classes\VaGovUnitTestBase;

/**
 * Unit tests for the ValidPhoneNumberValidator.
 *
 * @group unit
 * @group all
 *
 * @coversDefaultClass \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidPhoneNumberValidator
 */
class ValidPhoneNumberValidatorTest extends VaGovUnitTestBase {

  /**
   * The constraint.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidPhoneNumber
   */
  private $constraint;

  /**
   * The validator.
   *
   * @var \Drupal\va_gov_backend\Plugin\Validation\Constraint\ValidPhoneNumberValidator
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
    $this->constraint = new ValidPhoneNumber();
    $this->validator = new ValidPhoneNumberValidator();
    $this->context = $this->createMock(ExecutionContextInterface::class);
    $this->validator->initialize($this->context);
  }

  /**
   * Creates a mock field item list with a single item value.
   *
   * @param string $value
   *   The phone number value.
   *
   * @return \ArrayIterator
   *   An iterable of mock items.
   */
  private function createMockItems(string $value): \ArrayIterator {
    $item = new \stdClass();
    $item->value = $value;
    return new \ArrayIterator([$item]);
  }

  /**
   * Tests a valid phone number (xxx-xxx-xxxx format).
   *
   * @covers ::validate
   */
  public function testValidPhoneNumber() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockItems('800-555-1234'), $this->constraint);
  }

  /**
   * Tests an invalid phone number (more than 9 chars, wrong format).
   *
   * @covers ::validate
   */
  public function testInvalidPhoneNumber() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->notValidTel, $this->anything());
    $this->validator->validate($this->createMockItems('80055512345'), $this->constraint);
  }

  /**
   * Tests a valid 5-digit shortcode.
   *
   * @covers ::validate
   */
  public function testValidFiveDigitShortcode() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockItems('12345'), $this->constraint);
  }

  /**
   * Tests an invalid 5-character shortcode with non-digits.
   *
   * @covers ::validate
   */
  public function testInvalidFiveCharShortcode() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->notValidSms, $this->anything());
    $this->validator->validate($this->createMockItems('1234a'), $this->constraint);
  }

  /**
   * Tests a valid 6-digit shortcode.
   *
   * @covers ::validate
   */
  public function testValidSixDigitShortcode() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockItems('123456'), $this->constraint);
  }

  /**
   * Tests an invalid 6-character shortcode with non-digits.
   *
   * @covers ::validate
   */
  public function testInvalidSixCharShortcode() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->notValidSms, $this->anything());
    $this->validator->validate($this->createMockItems('12345a'), $this->constraint);
  }

  /**
   * Tests a valid 3-digit TTY number.
   *
   * @covers ::validate
   */
  public function testValidThreeDigitTty() {
    $this->context->expects($this->never())
      ->method('addViolation');
    $this->validator->validate($this->createMockItems('711'), $this->constraint);
  }

  /**
   * Tests an invalid 3-character TTY with non-digits.
   *
   * @covers ::validate
   */
  public function testInvalidThreeCharTty() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->notValidTty, $this->anything());
    $this->validator->validate($this->createMockItems('71a'), $this->constraint);
  }

  /**
   * Tests an invalid number length (not 3, 5, 6, or 12 characters).
   *
   * @covers ::validate
   */
  public function testInvalidLength() {
    $this->context->expects($this->once())
      ->method('addViolation')
      ->with($this->constraint->notValidLength, $this->anything());
    $this->validator->validate($this->createMockItems('1234'), $this->constraint);
  }

}
