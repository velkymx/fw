<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Fw\Support\DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DateTimeTest extends TestCase
{
    // ========================================
    // FACTORY TESTS
    // ========================================

    public function testNowCreatesCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $now = DateTime::now();
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $now->timestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $now->timestamp());
    }

    public function testTodayCreatesMidnight(): void
    {
        $today = DateTime::today();

        $this->assertEquals(0, $today->hour());
        $this->assertEquals(0, $today->minute());
        $this->assertEquals(0, $today->second());
    }

    public function testYesterdayCreatesCorrectDate(): void
    {
        $yesterday = DateTime::yesterday();
        $today = DateTime::today();

        // diffInDays returns how far "other" is from "this"
        // today is 1 day ahead of yesterday, so positive
        $this->assertEquals(1, $yesterday->diffInDays($today));
    }

    public function testTomorrowCreatesCorrectDate(): void
    {
        $tomorrow = DateTime::tomorrow();
        $today = DateTime::today();

        // today is 1 day behind tomorrow, so negative
        $this->assertEquals(-1, $tomorrow->diffInDays($today));
    }

    public function testParseValidString(): void
    {
        $date = DateTime::parse('2024-06-15 14:30:00');

        $this->assertEquals(2024, $date->year());
        $this->assertEquals(6, $date->month());
        $this->assertEquals(15, $date->day());
        $this->assertEquals(14, $date->hour());
        $this->assertEquals(30, $date->minute());
    }

    public function testParseInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DateTime::parse('not a date');
    }

    public function testFromTimestamp(): void
    {
        $timestamp = 1718457000; // 2024-06-15 12:30:00 UTC
        $date = DateTime::fromTimestamp($timestamp);

        $this->assertEquals($timestamp, $date->timestamp());
    }

    public function testCreate(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals(2024, $date->year());
        $this->assertEquals(6, $date->month());
        $this->assertEquals(15, $date->day());
        $this->assertEquals(14, $date->hour());
        $this->assertEquals(30, $date->minute());
        $this->assertEquals(45, $date->second());
    }

    public function testFromDateTimeImmutable(): void
    {
        $immutable = new DateTimeImmutable('2024-06-15 14:30:00');
        $date = DateTime::from($immutable);

        $this->assertEquals($immutable->getTimestamp(), $date->timestamp());
    }

    public function testWrapReturnsExistingInstance(): void
    {
        $original = DateTime::now();
        $wrapped = DateTime::wrap($original);

        $this->assertSame($original, $wrapped);
    }

    public function testWrapParsesString(): void
    {
        $date = DateTime::wrap('2024-06-15');

        $this->assertEquals(2024, $date->year());
        $this->assertEquals(6, $date->month());
        $this->assertEquals(15, $date->day());
    }

    public function testWrapConvertsDateTimeInterface(): void
    {
        $mutable = new \DateTime('2024-06-15');
        $date = DateTime::wrap($mutable);

        $this->assertEquals(2024, $date->year());
    }

    // ========================================
    // COMPONENT ACCESSOR TESTS
    // ========================================

    public function testYearAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $this->assertEquals(2024, $date->year());
    }

    public function testMonthAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $this->assertEquals(6, $date->month());
    }

    public function testDayAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $this->assertEquals(15, $date->day());
    }

    public function testHourAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $this->assertEquals(14, $date->hour());
    }

    public function testMinuteAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $this->assertEquals(30, $date->minute());
    }

    public function testSecondAccessor(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $this->assertEquals(45, $date->second());
    }

    public function testDayOfWeek(): void
    {
        // 2024-06-15 is a Saturday
        $date = DateTime::create(2024, 6, 15);
        $this->assertEquals(6, $date->dayOfWeek());
    }

    public function testDayOfYear(): void
    {
        // 2024-01-15 is the 15th day
        $date = DateTime::create(2024, 1, 15);
        $this->assertEquals(15, $date->dayOfYear());
    }

    public function testWeekOfYear(): void
    {
        $date = DateTime::create(2024, 1, 15);
        $this->assertGreaterThan(0, $date->weekOfYear());
    }

    public function testDaysInMonth(): void
    {
        $february2024 = DateTime::create(2024, 2, 1); // Leap year
        $this->assertEquals(29, $february2024->daysInMonth());

        $february2023 = DateTime::create(2023, 2, 1); // Not leap year
        $this->assertEquals(28, $february2023->daysInMonth());
    }

    // ========================================
    // DAY CHECK TESTS
    // ========================================

    public function testIsWeekday(): void
    {
        // 2024-06-14 is a Friday
        $friday = DateTime::create(2024, 6, 14);
        $this->assertTrue($friday->isWeekday());

        // 2024-06-15 is a Saturday
        $saturday = DateTime::create(2024, 6, 15);
        $this->assertFalse($saturday->isWeekday());
    }

    public function testIsWeekend(): void
    {
        $saturday = DateTime::create(2024, 6, 15);
        $this->assertTrue($saturday->isWeekend());

        $sunday = DateTime::create(2024, 6, 16);
        $this->assertTrue($sunday->isWeekend());

        $monday = DateTime::create(2024, 6, 17);
        $this->assertFalse($monday->isWeekend());
    }

    public function testDayOfWeekMethods(): void
    {
        $monday = DateTime::create(2024, 6, 17);
        $this->assertTrue($monday->isMonday());

        $tuesday = DateTime::create(2024, 6, 18);
        $this->assertTrue($tuesday->isTuesday());

        $wednesday = DateTime::create(2024, 6, 19);
        $this->assertTrue($wednesday->isWednesday());

        $thursday = DateTime::create(2024, 6, 20);
        $this->assertTrue($thursday->isThursday());

        $friday = DateTime::create(2024, 6, 21);
        $this->assertTrue($friday->isFriday());

        $saturday = DateTime::create(2024, 6, 22);
        $this->assertTrue($saturday->isSaturday());

        $sunday = DateTime::create(2024, 6, 23);
        $this->assertTrue($sunday->isSunday());
    }

    public function testIsToday(): void
    {
        $today = DateTime::today();
        $this->assertTrue($today->isToday());

        $yesterday = DateTime::yesterday();
        $this->assertFalse($yesterday->isToday());
    }

    public function testIsYesterday(): void
    {
        $yesterday = DateTime::yesterday();
        $this->assertTrue($yesterday->isYesterday());

        $today = DateTime::today();
        $this->assertFalse($today->isYesterday());
    }

    public function testIsTomorrow(): void
    {
        $tomorrow = DateTime::tomorrow();
        $this->assertTrue($tomorrow->isTomorrow());

        $today = DateTime::today();
        $this->assertFalse($today->isTomorrow());
    }

    public function testIsPast(): void
    {
        $yesterday = DateTime::yesterday();
        $this->assertTrue($yesterday->isPast());

        $tomorrow = DateTime::tomorrow();
        $this->assertFalse($tomorrow->isPast());
    }

    public function testIsFuture(): void
    {
        $tomorrow = DateTime::tomorrow();
        $this->assertTrue($tomorrow->isFuture());

        $yesterday = DateTime::yesterday();
        $this->assertFalse($yesterday->isFuture());
    }

    public function testIsLeapYear(): void
    {
        $leapYear = DateTime::create(2024, 1, 1);
        $this->assertTrue($leapYear->isLeapYear());

        $nonLeapYear = DateTime::create(2023, 1, 1);
        $this->assertFalse($nonLeapYear->isLeapYear());
    }

    // ========================================
    // ADD MANIPULATION TESTS
    // ========================================

    public function testAddYears(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->addYears(2);

        $this->assertEquals(2026, $result->year());
        $this->assertEquals(2024, $date->year()); // Original unchanged
    }

    public function testAddMonths(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->addMonths(3);

        $this->assertEquals(9, $result->month());
    }

    public function testAddWeeks(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->addWeeks(2);

        $this->assertEquals(29, $result->day());
    }

    public function testAddDays(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->addDays(10);

        $this->assertEquals(25, $result->day());
    }

    public function testAddHours(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 0, 0);
        $result = $date->addHours(5);

        $this->assertEquals(15, $result->hour());
    }

    public function testAddMinutes(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->addMinutes(45);

        $this->assertEquals(11, $result->hour());
        $this->assertEquals(15, $result->minute());
    }

    public function testAddSeconds(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 30);
        $result = $date->addSeconds(45);

        $this->assertEquals(31, $result->minute());
        $this->assertEquals(15, $result->second());
    }

    public function testAddInterval(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $interval = new DateInterval('P1Y2M3D');
        $result = $date->add($interval);

        $this->assertEquals(2025, $result->year());
        $this->assertEquals(8, $result->month());
        $this->assertEquals(18, $result->day());
    }

    // ========================================
    // SUBTRACT MANIPULATION TESTS
    // ========================================

    public function testSubYears(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->subYears(2);

        $this->assertEquals(2022, $result->year());
    }

    public function testSubMonths(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->subMonths(3);

        $this->assertEquals(3, $result->month());
    }

    public function testSubWeeks(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->subWeeks(2);

        $this->assertEquals(1, $result->day());
    }

    public function testSubDays(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->subDays(10);

        $this->assertEquals(5, $result->day());
    }

    public function testSubHours(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 0, 0);
        $result = $date->subHours(5);

        $this->assertEquals(5, $result->hour());
    }

    public function testSubMinutes(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->subMinutes(45);

        $this->assertEquals(9, $result->hour());
        $this->assertEquals(45, $result->minute());
    }

    public function testSubSeconds(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 30);
        $result = $date->subSeconds(45);

        $this->assertEquals(29, $result->minute());
        $this->assertEquals(45, $result->second());
    }

    // ========================================
    // BOUNDARY MANIPULATION TESTS
    // ========================================

    public function testStartOfDay(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $result = $date->startOfDay();

        $this->assertEquals(0, $result->hour());
        $this->assertEquals(0, $result->minute());
        $this->assertEquals(0, $result->second());
        $this->assertEquals(15, $result->day());
    }

    public function testEndOfDay(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $result = $date->endOfDay();

        $this->assertEquals(23, $result->hour());
        $this->assertEquals(59, $result->minute());
        $this->assertEquals(59, $result->second());
    }

    public function testStartOfMonth(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $result = $date->startOfMonth();

        $this->assertEquals(1, $result->day());
        $this->assertEquals(0, $result->hour());
    }

    public function testEndOfMonth(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->endOfMonth();

        $this->assertEquals(30, $result->day()); // June has 30 days
        $this->assertEquals(23, $result->hour());
    }

    public function testStartOfYear(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->startOfYear();

        $this->assertEquals(1, $result->month());
        $this->assertEquals(1, $result->day());
    }

    public function testEndOfYear(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->endOfYear();

        $this->assertEquals(12, $result->month());
        $this->assertEquals(31, $result->day());
    }

    public function testStartOfWeek(): void
    {
        // 2024-06-15 is Saturday
        $saturday = DateTime::create(2024, 6, 15);
        $result = $saturday->startOfWeek();

        // Week starts Monday = 2024-06-10
        $this->assertEquals(10, $result->day());
        $this->assertTrue($result->isMonday());
    }

    public function testEndOfWeek(): void
    {
        // 2024-06-15 is Saturday
        $saturday = DateTime::create(2024, 6, 15);
        $result = $saturday->endOfWeek();

        // Week ends Sunday = 2024-06-16
        $this->assertEquals(16, $result->day());
        $this->assertTrue($result->isSunday());
    }

    // ========================================
    // SET MANIPULATION TESTS
    // ========================================

    public function testSetYear(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->setYear(2030);

        $this->assertEquals(2030, $result->year());
        $this->assertEquals(6, $result->month());
    }

    public function testSetMonth(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->setMonth(12);

        $this->assertEquals(12, $result->month());
    }

    public function testSetDay(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->setDay(25);

        $this->assertEquals(25, $result->day());
    }

    public function testSetHour(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->setHour(18);

        $this->assertEquals(18, $result->hour());
        $this->assertEquals(30, $result->minute());
    }

    public function testSetMinute(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->setMinute(45);

        $this->assertEquals(10, $result->hour());
        $this->assertEquals(45, $result->minute());
    }

    public function testSetSecond(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->setSecond(59);

        $this->assertEquals(59, $result->second());
    }

    public function testSetDate(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $result = $date->setDate(2025, 12, 25);

        $this->assertEquals(2025, $result->year());
        $this->assertEquals(12, $result->month());
        $this->assertEquals(25, $result->day());
    }

    public function testSetTime(): void
    {
        $date = DateTime::create(2024, 6, 15, 10, 30, 0);
        $result = $date->setTime(23, 59, 59);

        $this->assertEquals(23, $result->hour());
        $this->assertEquals(59, $result->minute());
        $this->assertEquals(59, $result->second());
    }

    public function testSetTimezone(): void
    {
        $date = DateTime::create(2024, 6, 15, 12, 0, 0, new DateTimeZone('UTC'));
        $result = $date->setTimezone('America/New_York');

        $this->assertEquals('America/New_York', $result->timezone()->getName());
    }

    // ========================================
    // COMPARISON TESTS
    // ========================================

    public function testEquals(): void
    {
        $date1 = DateTime::create(2024, 6, 15, 12, 0, 0);
        $date2 = DateTime::create(2024, 6, 15, 12, 0, 0);
        $date3 = DateTime::create(2024, 6, 15, 12, 0, 1);

        $this->assertTrue($date1->equals($date2));
        $this->assertFalse($date1->equals($date3));
    }

    public function testIsBefore(): void
    {
        $earlier = DateTime::create(2024, 6, 15);
        $later = DateTime::create(2024, 6, 16);

        $this->assertTrue($earlier->isBefore($later));
        $this->assertFalse($later->isBefore($earlier));
        $this->assertFalse($earlier->isBefore($earlier));
    }

    public function testIsAfter(): void
    {
        $earlier = DateTime::create(2024, 6, 15);
        $later = DateTime::create(2024, 6, 16);

        $this->assertTrue($later->isAfter($earlier));
        $this->assertFalse($earlier->isAfter($later));
    }

    public function testIsBeforeOrEqual(): void
    {
        $date1 = DateTime::create(2024, 6, 15);
        $date2 = DateTime::create(2024, 6, 15);
        $date3 = DateTime::create(2024, 6, 16);

        $this->assertTrue($date1->isBeforeOrEqual($date2));
        $this->assertTrue($date1->isBeforeOrEqual($date3));
        $this->assertFalse($date3->isBeforeOrEqual($date1));
    }

    public function testIsAfterOrEqual(): void
    {
        $date1 = DateTime::create(2024, 6, 15);
        $date2 = DateTime::create(2024, 6, 15);
        $date3 = DateTime::create(2024, 6, 14);

        $this->assertTrue($date1->isAfterOrEqual($date2));
        $this->assertTrue($date1->isAfterOrEqual($date3));
        $this->assertFalse($date3->isAfterOrEqual($date1));
    }

    public function testIsBetween(): void
    {
        $start = DateTime::create(2024, 6, 10);
        $middle = DateTime::create(2024, 6, 15);
        $end = DateTime::create(2024, 6, 20);

        $this->assertTrue($middle->isBetween($start, $end));
        $this->assertTrue($start->isBetween($start, $end, inclusive: true));
        $this->assertFalse($start->isBetween($start, $end, inclusive: false));
    }

    public function testIsSameDay(): void
    {
        $date1 = DateTime::create(2024, 6, 15, 10, 0, 0);
        $date2 = DateTime::create(2024, 6, 15, 23, 59, 59);
        $date3 = DateTime::create(2024, 6, 16, 0, 0, 0);

        $this->assertTrue($date1->isSameDay($date2));
        $this->assertFalse($date1->isSameDay($date3));
    }

    public function testIsSameMonth(): void
    {
        $date1 = DateTime::create(2024, 6, 1);
        $date2 = DateTime::create(2024, 6, 30);
        $date3 = DateTime::create(2024, 7, 1);

        $this->assertTrue($date1->isSameMonth($date2));
        $this->assertFalse($date1->isSameMonth($date3));
    }

    public function testIsSameYear(): void
    {
        $date1 = DateTime::create(2024, 1, 1);
        $date2 = DateTime::create(2024, 12, 31);
        $date3 = DateTime::create(2025, 1, 1);

        $this->assertTrue($date1->isSameYear($date2));
        $this->assertFalse($date1->isSameYear($date3));
    }

    public function testMin(): void
    {
        $earlier = DateTime::create(2024, 6, 15);
        $later = DateTime::create(2024, 6, 20);

        $this->assertEquals($earlier->timestamp(), $earlier->min($later)->timestamp());
        $this->assertEquals($earlier->timestamp(), $later->min($earlier)->timestamp());
    }

    public function testMax(): void
    {
        $earlier = DateTime::create(2024, 6, 15);
        $later = DateTime::create(2024, 6, 20);

        $this->assertEquals($later->timestamp(), $earlier->max($later)->timestamp());
        $this->assertEquals($later->timestamp(), $later->max($earlier)->timestamp());
    }

    // ========================================
    // DIFFERENCE TESTS
    // ========================================

    public function testDiffInYears(): void
    {
        $date1 = DateTime::create(2020, 6, 15);
        $date2 = DateTime::create(2024, 6, 15);

        // date2 is 4 years ahead of date1
        $this->assertEquals(4, $date1->diffInYears($date2));
        // date1 is 4 years behind date2
        $this->assertEquals(-4, $date2->diffInYears($date1));
    }

    public function testDiffInMonths(): void
    {
        $date1 = DateTime::create(2024, 1, 15);
        $date2 = DateTime::create(2024, 6, 15);

        // date2 is 5 months ahead of date1
        $this->assertEquals(5, $date1->diffInMonths($date2));
    }

    public function testDiffInWeeks(): void
    {
        $date1 = DateTime::create(2024, 6, 1);
        $date2 = DateTime::create(2024, 6, 15);

        // date2 is 2 weeks ahead of date1
        $this->assertEquals(2, $date1->diffInWeeks($date2));
    }

    public function testDiffInDays(): void
    {
        $date1 = DateTime::create(2024, 6, 10);
        $date2 = DateTime::create(2024, 6, 15);

        // date2 is 5 days ahead of date1
        $this->assertEquals(5, $date1->diffInDays($date2));
        // date1 is 5 days behind date2
        $this->assertEquals(-5, $date2->diffInDays($date1));
    }

    public function testDiffInHours(): void
    {
        $date1 = DateTime::create(2024, 6, 15, 10, 0, 0);
        $date2 = DateTime::create(2024, 6, 15, 15, 0, 0);

        // date2 is 5 hours ahead
        $this->assertEquals(5, $date1->diffInHours($date2));
    }

    public function testDiffInMinutes(): void
    {
        $date1 = DateTime::create(2024, 6, 15, 10, 0, 0);
        $date2 = DateTime::create(2024, 6, 15, 10, 30, 0);

        // date2 is 30 minutes ahead
        $this->assertEquals(30, $date1->diffInMinutes($date2));
    }

    public function testDiffInSeconds(): void
    {
        $date1 = DateTime::create(2024, 6, 15, 10, 0, 0);
        $date2 = DateTime::create(2024, 6, 15, 10, 0, 45);

        $this->assertEquals(45, $date1->diffInSeconds($date2));
    }

    public function testDiffForHumans(): void
    {
        $now = DateTime::now();

        $twoHoursAgo = $now->subHours(2);
        $this->assertStringContainsString('2 hours ago', $twoHoursAgo->diffForHumans());

        // Use a fixed date comparison to avoid timing issues
        $past = DateTime::create(2024, 1, 1, 12, 0, 0);
        $future = DateTime::create(2024, 1, 4, 12, 0, 0);
        $this->assertStringContainsString('3 days after', $future->diffForHumans($past));
        $this->assertStringContainsString('3 days before', $past->diffForHumans($future));
    }

    public function testDiffForHumansShort(): void
    {
        $now = DateTime::now();
        $twoHoursAgo = $now->subHours(2);

        $result = $twoHoursAgo->diffForHumans(short: true);
        $this->assertStringContainsString('2h', $result);
    }

    public function testDiffForHumansMultipleParts(): void
    {
        $now = DateTime::now();
        $past = $now->subHours(2)->subMinutes(30);

        $result = $past->diffForHumans(parts: 2);
        $this->assertStringContainsString('2 hours', $result);
        $this->assertStringContainsString('30 minutes', $result);
    }

    public function testAge(): void
    {
        $birthDate = DateTime::now()->subYears(25);

        $this->assertEquals(25, $birthDate->age());
    }

    // ========================================
    // FORMATTING TESTS
    // ========================================

    public function testFormat(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals('2024-06-15', $date->format('Y-m-d'));
        $this->assertEquals('14:30:45', $date->format('H:i:s'));
    }

    public function testToDateString(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals('2024-06-15', $date->toDateString());
    }

    public function testToTimeString(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals('14:30:45', $date->toTimeString());
    }

    public function testToDateTimeString(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals('2024-06-15 14:30:45', $date->toDateTimeString());
    }

    public function testToIso8601(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $result = $date->toIso8601();

        $this->assertStringContainsString('2024-06-15', $result);
        $this->assertStringContainsString('14:30:45', $result);
    }

    public function testToString(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);

        $this->assertEquals('2024-06-15 14:30:45', (string) $date);
    }

    public function testJsonSerialize(): void
    {
        $date = DateTime::create(2024, 6, 15, 14, 30, 45);
        $json = json_encode(['date' => $date]);

        $this->assertJson($json);
        $this->assertStringContainsString('2024-06-15', $json);
    }

    // ========================================
    // CONVERSION TESTS
    // ========================================

    public function testToDateTimeImmutable(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $immutable = $date->toDateTimeImmutable();

        $this->assertInstanceOf(DateTimeImmutable::class, $immutable);
        $this->assertEquals($date->timestamp(), $immutable->getTimestamp());
    }

    public function testToDateTime(): void
    {
        $date = DateTime::create(2024, 6, 15);
        $mutable = $date->toDateTime();

        $this->assertInstanceOf(\DateTime::class, $mutable);
        $this->assertEquals($date->timestamp(), $mutable->getTimestamp());
    }

    // ========================================
    // TIMEZONE TESTS
    // ========================================

    public function testCreateWithTimezone(): void
    {
        $utc = DateTime::create(2024, 6, 15, 12, 0, 0, new DateTimeZone('UTC'));
        $ny = DateTime::create(2024, 6, 15, 12, 0, 0, new DateTimeZone('America/New_York'));

        $this->assertNotEquals($utc->timestamp(), $ny->timestamp());
    }

    public function testNowWithTimezone(): void
    {
        $utc = DateTime::now(new DateTimeZone('UTC'));
        $tokyo = DateTime::now(new DateTimeZone('Asia/Tokyo'));

        // Same instant, different representation
        $diff = abs($utc->timestamp() - $tokyo->timestamp());
        $this->assertLessThan(2, $diff); // Allow 1 second margin
    }

    // ========================================
    // IMMUTABILITY TESTS
    // ========================================

    public function testImmutability(): void
    {
        $original = DateTime::create(2024, 6, 15);
        $modified = $original->addDays(5);

        $this->assertEquals(15, $original->day());
        $this->assertEquals(20, $modified->day());
        $this->assertNotSame($original, $modified);
    }
}
