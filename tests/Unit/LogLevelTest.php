<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Log\LogLevel;
use Fw\Tests\TestCase;

final class LogLevelTest extends TestCase
{
    public function testPriorityReturnsCorrectOrder(): void
    {
        // Lower priority = more severe
        $this->assertLessThan(LogLevel::ALERT->priority(), LogLevel::EMERGENCY->priority());
        $this->assertLessThan(LogLevel::CRITICAL->priority(), LogLevel::ALERT->priority());
        $this->assertLessThan(LogLevel::ERROR->priority(), LogLevel::CRITICAL->priority());
        $this->assertLessThan(LogLevel::WARNING->priority(), LogLevel::ERROR->priority());
        $this->assertLessThan(LogLevel::NOTICE->priority(), LogLevel::WARNING->priority());
        $this->assertLessThan(LogLevel::INFO->priority(), LogLevel::NOTICE->priority());
        $this->assertLessThan(LogLevel::DEBUG->priority(), LogLevel::INFO->priority());
    }

    public function testShouldLogReturnsTrueForSameLevelOrHigher(): void
    {
        $minimumLevel = LogLevel::WARNING;

        // More severe levels should be logged
        $this->assertTrue(LogLevel::EMERGENCY->shouldLog($minimumLevel));
        $this->assertTrue(LogLevel::ALERT->shouldLog($minimumLevel));
        $this->assertTrue(LogLevel::CRITICAL->shouldLog($minimumLevel));
        $this->assertTrue(LogLevel::ERROR->shouldLog($minimumLevel));
        $this->assertTrue(LogLevel::WARNING->shouldLog($minimumLevel));

        // Less severe levels should not be logged
        $this->assertFalse(LogLevel::NOTICE->shouldLog($minimumLevel));
        $this->assertFalse(LogLevel::INFO->shouldLog($minimumLevel));
        $this->assertFalse(LogLevel::DEBUG->shouldLog($minimumLevel));
    }

    public function testFromStringParsesDebug(): void
    {
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString('debug'));
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString('DEBUG'));
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString('Debug'));
    }

    public function testFromStringParsesInfo(): void
    {
        $this->assertEquals(LogLevel::INFO, LogLevel::fromString('info'));
        $this->assertEquals(LogLevel::INFO, LogLevel::fromString('INFO'));
    }

    public function testFromStringParsesNotice(): void
    {
        $this->assertEquals(LogLevel::NOTICE, LogLevel::fromString('notice'));
    }

    public function testFromStringParsesWarning(): void
    {
        $this->assertEquals(LogLevel::WARNING, LogLevel::fromString('warning'));
        $this->assertEquals(LogLevel::WARNING, LogLevel::fromString('warn'));
    }

    public function testFromStringParsesError(): void
    {
        $this->assertEquals(LogLevel::ERROR, LogLevel::fromString('error'));
    }

    public function testFromStringParsesCritical(): void
    {
        $this->assertEquals(LogLevel::CRITICAL, LogLevel::fromString('critical'));
    }

    public function testFromStringParsesAlert(): void
    {
        $this->assertEquals(LogLevel::ALERT, LogLevel::fromString('alert'));
    }

    public function testFromStringParsesEmergency(): void
    {
        $this->assertEquals(LogLevel::EMERGENCY, LogLevel::fromString('emergency'));
    }

    public function testFromStringDefaultsToDebugForUnknown(): void
    {
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString('invalid'));
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString(''));
        $this->assertEquals(LogLevel::DEBUG, LogLevel::fromString('unknown'));
    }

    public function testValueReturnsLowercaseString(): void
    {
        $this->assertEquals('debug', LogLevel::DEBUG->value);
        $this->assertEquals('info', LogLevel::INFO->value);
        $this->assertEquals('notice', LogLevel::NOTICE->value);
        $this->assertEquals('warning', LogLevel::WARNING->value);
        $this->assertEquals('error', LogLevel::ERROR->value);
        $this->assertEquals('critical', LogLevel::CRITICAL->value);
        $this->assertEquals('alert', LogLevel::ALERT->value);
        $this->assertEquals('emergency', LogLevel::EMERGENCY->value);
    }
}
