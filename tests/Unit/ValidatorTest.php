<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Security\Validator;
use Fw\Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRulePasses(): void
    {
        $validator = Validator::make(
            ['name' => 'John'],
            ['name' => 'required']
        );

        $this->assertTrue($validator->passes());
    }

    public function testRequiredRuleFails(): void
    {
        $validator = Validator::make(
            ['name' => ''],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testRequiredRuleFailsForNull(): void
    {
        $validator = Validator::make(
            ['name' => null],
            ['name' => 'required']
        );

        $this->assertTrue($validator->fails());
    }

    public function testRequiredRuleFailsForEmptyArray(): void
    {
        $validator = Validator::make(
            ['items' => []],
            ['items' => 'required']
        );

        $this->assertTrue($validator->fails());
    }

    public function testEmailRulePasses(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->passes());
    }

    public function testEmailRuleFails(): void
    {
        $validator = Validator::make(
            ['email' => 'not-an-email'],
            ['email' => 'email']
        );

        $this->assertTrue($validator->fails());
    }

    public function testUrlRulePasses(): void
    {
        $validator = Validator::make(
            ['website' => 'https://example.com'],
            ['website' => 'url']
        );

        $this->assertTrue($validator->passes());
    }

    public function testUrlRuleFails(): void
    {
        $validator = Validator::make(
            ['website' => 'not-a-url'],
            ['website' => 'url']
        );

        $this->assertTrue($validator->fails());
    }

    public function testMinRulePassesForString(): void
    {
        $validator = Validator::make(
            ['password' => 'secret123'],
            ['password' => 'min:8']
        );

        $this->assertTrue($validator->passes());
    }

    public function testMinRuleFailsForString(): void
    {
        $validator = Validator::make(
            ['password' => 'short'],
            ['password' => 'min:8']
        );

        $this->assertTrue($validator->fails());
    }

    public function testMinRulePassesForNumber(): void
    {
        $validator = Validator::make(
            ['age' => 21],
            ['age' => 'min:18']
        );

        $this->assertTrue($validator->passes());
    }

    public function testMinRuleFailsForNumber(): void
    {
        $validator = Validator::make(
            ['age' => 15],
            ['age' => 'min:18']
        );

        $this->assertTrue($validator->fails());
    }

    public function testMinRulePassesForArray(): void
    {
        $validator = Validator::make(
            ['items' => ['a', 'b', 'c']],
            ['items' => 'min:2']
        );

        $this->assertTrue($validator->passes());
    }

    public function testMaxRulePassesForString(): void
    {
        $validator = Validator::make(
            ['title' => 'Short title'],
            ['title' => 'max:100']
        );

        $this->assertTrue($validator->passes());
    }

    public function testMaxRuleFailsForString(): void
    {
        $validator = Validator::make(
            ['title' => str_repeat('a', 101)],
            ['title' => 'max:100']
        );

        $this->assertTrue($validator->fails());
    }

    public function testBetweenRulePasses(): void
    {
        $validator = Validator::make(
            ['username' => 'johndoe'],
            ['username' => 'between:3,20']
        );

        $this->assertTrue($validator->passes());
    }

    public function testBetweenRuleFailsTooShort(): void
    {
        $validator = Validator::make(
            ['username' => 'ab'],
            ['username' => 'between:3,20']
        );

        $this->assertTrue($validator->fails());
    }

    public function testBetweenRuleFailsTooLong(): void
    {
        $validator = Validator::make(
            ['username' => str_repeat('a', 21)],
            ['username' => 'between:3,20']
        );

        $this->assertTrue($validator->fails());
    }

    public function testNumericRulePasses(): void
    {
        $validator = Validator::make(
            ['price' => '19.99'],
            ['price' => 'numeric']
        );

        $this->assertTrue($validator->passes());
    }

    public function testNumericRuleFails(): void
    {
        $validator = Validator::make(
            ['price' => 'not-a-number'],
            ['price' => 'numeric']
        );

        $this->assertTrue($validator->fails());
    }

    public function testIntegerRulePasses(): void
    {
        $validator = Validator::make(
            ['count' => '42'],
            ['count' => 'integer']
        );

        $this->assertTrue($validator->passes());
    }

    public function testIntegerRuleFails(): void
    {
        $validator = Validator::make(
            ['count' => '42.5'],
            ['count' => 'integer']
        );

        $this->assertTrue($validator->fails());
    }

    public function testAlphaRulePasses(): void
    {
        $validator = Validator::make(
            ['name' => 'JohnDoe'],
            ['name' => 'alpha']
        );

        $this->assertTrue($validator->passes());
    }

    public function testAlphaRuleFails(): void
    {
        $validator = Validator::make(
            ['name' => 'John123'],
            ['name' => 'alpha']
        );

        $this->assertTrue($validator->fails());
    }

    public function testAlphanumericRulePasses(): void
    {
        $validator = Validator::make(
            ['username' => 'John123'],
            ['username' => 'alphanumeric']
        );

        $this->assertTrue($validator->passes());
    }

    public function testAlphanumericRuleFails(): void
    {
        $validator = Validator::make(
            ['username' => 'John-123'],
            ['username' => 'alphanumeric']
        );

        $this->assertTrue($validator->fails());
    }

    public function testRegexRulePasses(): void
    {
        $validator = Validator::make(
            ['phone' => '123-456-7890'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']
        );

        $this->assertTrue($validator->passes());
    }

    public function testRegexRuleFails(): void
    {
        $validator = Validator::make(
            ['phone' => 'not-a-phone'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/']
        );

        $this->assertTrue($validator->fails());
    }

    public function testInRulePasses(): void
    {
        $validator = Validator::make(
            ['status' => 'active'],
            ['status' => 'in:active,inactive,pending']
        );

        $this->assertTrue($validator->passes());
    }

    public function testInRuleFails(): void
    {
        $validator = Validator::make(
            ['status' => 'deleted'],
            ['status' => 'in:active,inactive,pending']
        );

        $this->assertTrue($validator->fails());
    }

    public function testNotInRulePasses(): void
    {
        $validator = Validator::make(
            ['role' => 'admin'],
            ['role' => 'notIn:guest,banned']
        );

        $this->assertTrue($validator->passes());
    }

    public function testNotInRuleFails(): void
    {
        $validator = Validator::make(
            ['role' => 'banned'],
            ['role' => 'notIn:guest,banned']
        );

        $this->assertTrue($validator->fails());
    }

    public function testConfirmedRulePasses(): void
    {
        $validator = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed']
        );

        $this->assertTrue($validator->passes());
    }

    public function testConfirmedRuleFails(): void
    {
        $validator = Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        );

        $this->assertTrue($validator->fails());
    }

    public function testDateRulePasses(): void
    {
        $validator = Validator::make(
            ['birthday' => '1990-01-15'],
            ['birthday' => 'date']
        );

        $this->assertTrue($validator->passes());
    }

    public function testDateRuleFails(): void
    {
        $validator = Validator::make(
            ['birthday' => 'not-a-date'],
            ['birthday' => 'date']
        );

        $this->assertTrue($validator->fails());
    }

    public function testBeforeRulePasses(): void
    {
        $validator = Validator::make(
            ['start_date' => '2020-01-01'],
            ['start_date' => 'before:2025-01-01']
        );

        $this->assertTrue($validator->passes());
    }

    public function testBeforeRuleFails(): void
    {
        $validator = Validator::make(
            ['start_date' => '2030-01-01'],
            ['start_date' => 'before:2025-01-01']
        );

        $this->assertTrue($validator->fails());
    }

    public function testAfterRulePasses(): void
    {
        $validator = Validator::make(
            ['end_date' => '2030-01-01'],
            ['end_date' => 'after:2025-01-01']
        );

        $this->assertTrue($validator->passes());
    }

    public function testAfterRuleFails(): void
    {
        $validator = Validator::make(
            ['end_date' => '2020-01-01'],
            ['end_date' => 'after:2025-01-01']
        );

        $this->assertTrue($validator->fails());
    }

    public function testArrayRulePasses(): void
    {
        $validator = Validator::make(
            ['tags' => ['php', 'testing']],
            ['tags' => 'array']
        );

        $this->assertTrue($validator->passes());
    }

    public function testArrayRuleFails(): void
    {
        $validator = Validator::make(
            ['tags' => 'not-an-array'],
            ['tags' => 'array']
        );

        $this->assertTrue($validator->fails());
    }

    public function testBooleanRulePasses(): void
    {
        $validator = Validator::make(
            ['active' => true],
            ['active' => 'boolean']
        );

        $this->assertTrue($validator->passes());

        $validator = Validator::make(
            ['active' => '1'],
            ['active' => 'boolean']
        );

        $this->assertTrue($validator->passes());
    }

    public function testBooleanRuleFails(): void
    {
        $validator = Validator::make(
            ['active' => 'yes'],
            ['active' => 'boolean']
        );

        $this->assertTrue($validator->fails());
    }

    public function testSameRulePasses(): void
    {
        $validator = Validator::make(
            ['password' => 'secret', 'confirm' => 'secret'],
            ['confirm' => 'same:password']
        );

        $this->assertTrue($validator->passes());
    }

    public function testSameRuleFails(): void
    {
        $validator = Validator::make(
            ['password' => 'secret', 'confirm' => 'different'],
            ['confirm' => 'same:password']
        );

        $this->assertTrue($validator->fails());
    }

    public function testDifferentRulePasses(): void
    {
        $validator = Validator::make(
            ['old_password' => 'old', 'new_password' => 'new'],
            ['new_password' => 'different:old_password']
        );

        $this->assertTrue($validator->passes());
    }

    public function testDifferentRuleFails(): void
    {
        $validator = Validator::make(
            ['old_password' => 'same', 'new_password' => 'same'],
            ['new_password' => 'different:old_password']
        );

        $this->assertTrue($validator->fails());
    }

    public function testMultipleRulesCanBeApplied(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'required|email|max:255']
        );

        $this->assertTrue($validator->passes());
    }

    public function testMultipleRulesAsArray(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => ['required', 'email', 'max:255']]
        );

        $this->assertTrue($validator->passes());
    }

    public function testOptionalFieldSkipsValidationWhenEmpty(): void
    {
        $validator = Validator::make(
            ['nickname' => ''],
            ['nickname' => 'min:3|max:20']
        );

        $this->assertTrue($validator->passes());
    }

    public function testNestedFieldValidation(): void
    {
        $validator = Validator::make(
            ['user' => ['email' => 'test@example.com']],
            ['user.email' => 'required|email']
        );

        $this->assertTrue($validator->passes());
    }

    public function testValidatedReturnsOnlyValidFields(): void
    {
        $validator = Validator::make(
            ['name' => 'John', 'email' => 'test@example.com', 'hack' => 'malicious'],
            ['name' => 'required', 'email' => 'required|email']
        );

        $validator->validate();
        $validated = $validator->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('hack', $validated);
    }

    public function testFirstErrorReturnsFirstFieldError(): void
    {
        $validator = Validator::make(
            ['email' => 'invalid'],
            ['email' => 'email']
        );

        $validator->validate();

        $this->assertNotNull($validator->firstError('email'));
        $this->assertStringContainsString('email', $validator->firstError('email'));
    }

    public function testFirstErrorReturnsNullForValidField(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $validator->validate();

        $this->assertNull($validator->firstError('email'));
    }

    public function testAllErrorsReturnsFlattened(): void
    {
        $validator = Validator::make(
            ['name' => '', 'email' => 'invalid'],
            ['name' => 'required', 'email' => 'email']
        );

        $validator->validate();
        $all = $validator->allErrors();

        $this->assertCount(2, $all);
    }

    public function testHasErrorReturnsTrueForInvalidField(): void
    {
        $validator = Validator::make(
            ['email' => 'invalid'],
            ['email' => 'email']
        );

        $validator->validate();

        $this->assertTrue($validator->hasError('email'));
    }

    public function testHasErrorReturnsFalseForValidField(): void
    {
        $validator = Validator::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $validator->validate();

        $this->assertFalse($validator->hasError('email'));
    }

    public function testMakeStaticMethod(): void
    {
        $validator = Validator::make(['name' => 'John'], ['name' => 'required']);

        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertTrue($validator->passes());
    }
}
