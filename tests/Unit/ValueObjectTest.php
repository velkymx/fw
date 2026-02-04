<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Domain\Email;
use Fw\Domain\Id;
use Fw\Domain\Money;
use Fw\Domain\UserId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ValueObjectTest extends TestCase
{
    // ==========================================
    // EMAIL TESTS
    // ==========================================

    public function testEmailCreatesFromValidString(): void
    {
        $email = Email::from('john@example.com');

        $this->assertEquals('john@example.com', $email->value);
        $this->assertEquals('john@example.com', (string) $email);
    }

    public function testEmailNormalizesToLowercase(): void
    {
        $email = Email::from('John@Example.COM');

        $this->assertEquals('john@example.com', $email->value);
    }

    public function testEmailTrimsWhitespace(): void
    {
        $email = Email::from('  john@example.com  ');

        $this->assertEquals('john@example.com', $email->value);
    }

    public function testEmailThrowsForInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        Email::from('not-an-email');
    }

    public function testEmailLocalPart(): void
    {
        $email = Email::from('john.doe@example.com');

        $this->assertEquals('john.doe', $email->localPart());
    }

    public function testEmailDomain(): void
    {
        $email = Email::from('john@example.com');

        $this->assertEquals('example.com', $email->domain());
    }

    public function testEmailIsDomain(): void
    {
        $email = Email::from('john@gmail.com');

        $this->assertTrue($email->isDomain('gmail.com'));
        $this->assertTrue($email->isDomain('Gmail.COM'));
        $this->assertFalse($email->isDomain('yahoo.com'));
    }

    public function testEmailIsFreeProvider(): void
    {
        $this->assertTrue(Email::from('john@gmail.com')->isFreeProvider());
        $this->assertTrue(Email::from('john@yahoo.com')->isFreeProvider());
        $this->assertFalse(Email::from('john@company.com')->isFreeProvider());
    }

    public function testEmailFromTrustedSkipsValidation(): void
    {
        $email = Email::fromTrusted('already-validated@example.com');

        $this->assertEquals('already-validated@example.com', $email->value);
    }

    public function testEmailEquality(): void
    {
        $email1 = Email::from('john@example.com');
        $email2 = Email::from('john@example.com');
        $email3 = Email::from('jane@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }

    // ==========================================
    // USER ID TESTS
    // ==========================================

    public function testUserIdGenerate(): void
    {
        $id1 = UserId::generate();
        $id2 = UserId::generate();

        $this->assertNotEquals($id1->value, $id2->value);
        $this->assertEquals(36, strlen($id1->value));
    }

    public function testUserIdFromValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id = UserId::from($uuid);

        $this->assertEquals(strtolower($uuid), $id->value);
    }

    public function testUserIdFromInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UserId::from('not-a-uuid');
    }

    public function testUserIdFromTrusted(): void
    {
        $id = UserId::fromTrusted('any-string');

        $this->assertEquals('any-string', $id->value);
    }

    public function testUserIdEquality(): void
    {
        $id1 = UserId::fromTrusted('same-id');
        $id2 = UserId::fromTrusted('same-id');
        $id3 = UserId::fromTrusted('different-id');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    // ==========================================
    // ID BASE CLASS TESTS
    // ==========================================

    public function testIdIsValidUuid(): void
    {
        $this->assertTrue(UserId::isValid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse(UserId::isValid('not-valid'));
    }

    public function testIdGenerateUlid(): void
    {
        $id = UserId::generateUlid();

        $this->assertEquals(26, strlen($id->value));
    }

    // ==========================================
    // MONEY TESTS
    // ==========================================

    public function testMoneyCreatesFromCents(): void
    {
        $money = Money::cents(1500, 'USD');

        $this->assertEquals(1500, $money->cents);
        $this->assertEquals('USD', $money->currency);
        $this->assertEquals(15.0, $money->toDollars());
    }

    public function testMoneyCreatesFromDollars(): void
    {
        $money = Money::dollars(19.99, 'USD');

        $this->assertEquals(1999, $money->cents);
        $this->assertEquals(19.99, $money->toDollars());
    }

    public function testMoneyZero(): void
    {
        $money = Money::zero('EUR');

        $this->assertEquals(0, $money->cents);
        $this->assertEquals('EUR', $money->currency);
        $this->assertTrue($money->isZero());
    }

    public function testMoneyFormatted(): void
    {
        $this->assertEquals('$19.99', Money::dollars(19.99)->formatted());
        $this->assertEquals('€10.00', Money::cents(1000, 'EUR')->formatted());
        $this->assertEquals('£5.50', Money::cents(550, 'GBP')->formatted());
    }

    public function testMoneyAdd(): void
    {
        $money1 = Money::dollars(10);
        $money2 = Money::dollars(5);

        $result = $money1->add($money2);

        $this->assertEquals(1500, $result->cents);
    }

    public function testMoneyAddDifferentCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot operate on different currencies');

        Money::dollars(10, 'USD')->add(Money::dollars(5, 'EUR'));
    }

    public function testMoneySubtract(): void
    {
        $money1 = Money::dollars(10);
        $money2 = Money::dollars(3);

        $result = $money1->subtract($money2);

        $this->assertEquals(700, $result->cents);
    }

    public function testMoneyMultiply(): void
    {
        $money = Money::dollars(10);

        $result = $money->multiply(2.5);

        $this->assertEquals(2500, $result->cents);
    }

    public function testMoneyDivide(): void
    {
        $money = Money::dollars(10);

        $result = $money->divide(4);

        $this->assertEquals(250, $result->cents);
    }

    public function testMoneyDivideByZeroThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot divide by zero');

        Money::dollars(10)->divide(0);
    }

    public function testMoneyAbsolute(): void
    {
        $money = Money::cents(-500);

        $this->assertEquals(500, $money->absolute()->cents);
    }

    public function testMoneyNegate(): void
    {
        $money = Money::cents(500);

        $this->assertEquals(-500, $money->negate()->cents);
    }

    public function testMoneyIsPositive(): void
    {
        $this->assertTrue(Money::cents(100)->isPositive());
        $this->assertFalse(Money::cents(0)->isPositive());
        $this->assertFalse(Money::cents(-100)->isPositive());
    }

    public function testMoneyIsNegative(): void
    {
        $this->assertTrue(Money::cents(-100)->isNegative());
        $this->assertFalse(Money::cents(0)->isNegative());
        $this->assertFalse(Money::cents(100)->isNegative());
    }

    public function testMoneyComparisons(): void
    {
        $ten = Money::dollars(10);
        $five = Money::dollars(5);

        $this->assertTrue($ten->greaterThan($five));
        $this->assertTrue($ten->greaterThanOrEqual($five));
        $this->assertTrue($five->lessThan($ten));
        $this->assertTrue($five->lessThanOrEqual($ten));
    }

    public function testMoneyAllocate(): void
    {
        $money = Money::dollars(10);

        $allocated = $money->allocate([1, 1, 1]);

        $this->assertCount(3, $allocated);
        $this->assertEquals(334, $allocated[0]->cents);
        $this->assertEquals(333, $allocated[1]->cents);
        $this->assertEquals(333, $allocated[2]->cents);

        // Total should equal original
        $total = array_reduce($allocated, fn($sum, $m) => $sum + $m->cents, 0);
        $this->assertEquals(1000, $total);
    }

    public function testMoneyAllocateUneven(): void
    {
        $money = Money::cents(100);

        $allocated = $money->allocate([70, 30]);

        $this->assertEquals(70, $allocated[0]->cents);
        $this->assertEquals(30, $allocated[1]->cents);
    }

    public function testMoneyConvertTo(): void
    {
        $usd = Money::dollars(100, 'USD');

        $eur = $usd->convertTo('EUR', 0.85);

        $this->assertEquals('EUR', $eur->currency);
        $this->assertEquals(8500, $eur->cents);
    }

    public function testMoneyEquality(): void
    {
        $money1 = Money::cents(1000, 'USD');
        $money2 = Money::cents(1000, 'USD');
        $money3 = Money::cents(1000, 'EUR');
        $money4 = Money::cents(500, 'USD');

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3)); // Different currency
        $this->assertFalse($money1->equals($money4)); // Different amount
    }

    public function testMoneyJsonSerialize(): void
    {
        $money = Money::dollars(19.99, 'USD');

        $json = json_encode($money);
        $decoded = json_decode($json, true);

        $this->assertEquals(1999, $decoded['cents']);
        $this->assertEquals('USD', $decoded['currency']);
        $this->assertEquals('$19.99', $decoded['formatted']);
    }

    public function testMoneyToString(): void
    {
        $money = Money::dollars(19.99, 'USD');

        $this->assertEquals('$19.99', (string) $money);
    }

    public function testMoneyEmptyCurrencyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency cannot be empty');

        new Money(100, '');
    }
}
