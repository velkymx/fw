<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Log\Logger;
use Fw\Log\LogLevel;
use Fw\Tests\TestCase;

final class LoggerTest extends TestCase
{
    private string $tempLogDir;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        Logger::reset();
        $this->tempLogDir = sys_get_temp_dir() . '/fw-logs-' . uniqid();
        mkdir($this->tempLogDir, 0755, true);

        $this->logger = Logger::create($this->tempLogDir);
    }

    protected function tearDown(): void
    {
        // Clean up log files
        $files = glob($this->tempLogDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempLogDir);

        Logger::reset();
        parent::tearDown();
    }

    private function getLogContent(): string
    {
        $files = glob($this->tempLogDir . '/*.log');
        if (empty($files)) {
            return '';
        }
        return file_get_contents($files[0]) ?: '';
    }

    public function testDebugLogsMessage(): void
    {
        $this->logger->debug('Test debug message');

        $content = $this->getLogContent();

        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('Test debug message', $content);
    }

    public function testInfoLogsMessage(): void
    {
        $this->logger->info('Test info message');

        $content = $this->getLogContent();

        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Test info message', $content);
    }

    public function testWarningLogsMessage(): void
    {
        $this->logger->warning('Test warning message');

        $content = $this->getLogContent();

        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('Test warning message', $content);
    }

    public function testErrorLogsMessage(): void
    {
        $this->logger->error('Test error message');

        $content = $this->getLogContent();

        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Test error message', $content);
    }

    public function testCriticalLogsMessage(): void
    {
        $this->logger->critical('Test critical message');

        $content = $this->getLogContent();

        $this->assertStringContainsString('CRITICAL', $content);
        $this->assertStringContainsString('Test critical message', $content);
    }

    public function testLogIncludesTimestamp(): void
    {
        $this->logger->info('Test message');

        $content = $this->getLogContent();

        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testLogInterpolatesContext(): void
    {
        $this->logger->info('User {name} logged in from {ip}', [
            'name' => 'John',
            'ip' => '192.168.1.1',
        ]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('User John logged in from 192.168.1.1', $content);
    }

    public function testLogIncludesExceptionDetails(): void
    {
        $exception = new \RuntimeException('Test exception message');

        $this->logger->error('An error occurred', ['exception' => $exception]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('Exception: RuntimeException', $content);
        $this->assertStringContainsString('Message: Test exception message', $content);
        $this->assertStringContainsString('File:', $content);
        $this->assertStringContainsString('Trace:', $content);
    }

    public function testLogIncludesContextAsJson(): void
    {
        $this->logger->info('Test message', [
            'user_id' => 123,
            'action' => 'login',
        ]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('Context:', $content);
        $this->assertStringContainsString('"user_id":123', $content);
        $this->assertStringContainsString('"action":"login"', $content);
    }

    public function testMinimumLevelFiltersLogs(): void
    {
        $this->logger->setMinimumLevel(LogLevel::WARNING);

        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');

        $content = $this->getLogContent();

        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringContainsString('Warning message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function testDisableStopsLogging(): void
    {
        $this->logger->disable();
        $this->logger->error('This should not be logged');

        $content = $this->getLogContent();

        $this->assertEmpty($content);
    }

    public function testEnableResumesLogging(): void
    {
        $this->logger->disable();
        $this->logger->enable();
        $this->logger->error('This should be logged');

        $content = $this->getLogContent();

        $this->assertStringContainsString('This should be logged', $content);
    }

    public function testIsEnabledReturnsCorrectState(): void
    {
        $this->assertTrue($this->logger->isEnabled());

        $this->logger->disable();
        $this->assertFalse($this->logger->isEnabled());

        $this->logger->enable();
        $this->assertTrue($this->logger->isEnabled());
    }

    public function testGetPathReturnsConfiguredPath(): void
    {
        $this->assertEquals($this->tempLogDir, $this->logger->getPath());
    }

    public function testGetMinimumLevelReturnsConfiguredLevel(): void
    {
        $this->assertEquals(LogLevel::DEBUG, $this->logger->getMinimumLevel());

        $this->logger->setMinimumLevel(LogLevel::ERROR);
        $this->assertEquals(LogLevel::ERROR, $this->logger->getMinimumLevel());
    }

    public function testDailyRotationCreatesCorrectFilename(): void
    {
        $this->logger->info('Test message');

        $expectedPattern = '/fw-' . date('Y-m-d') . '\.log$/';
        $files = glob($this->tempLogDir . '/*.log');

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression($expectedPattern, $files[0]);
    }

    public function testMultipleLogsAppendToSameFile(): void
    {
        $this->logger->info('First message');
        $this->logger->info('Second message');
        $this->logger->info('Third message');

        $files = glob($this->tempLogDir . '/*.log');
        $this->assertCount(1, $files);

        $content = $this->getLogContent();
        $this->assertStringContainsString('First message', $content);
        $this->assertStringContainsString('Second message', $content);
        $this->assertStringContainsString('Third message', $content);
    }

    public function testLogInterpolatesArrayContext(): void
    {
        $this->logger->info('Data: {data}', [
            'data' => ['a' => 1, 'b' => 2],
        ]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('{"a":1,"b":2}', $content);
    }

    public function testLogInterpolatesBooleanContext(): void
    {
        $this->logger->info('Active: {active}, Deleted: {deleted}', [
            'active' => true,
            'deleted' => false,
        ]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('Active: true', $content);
        $this->assertStringContainsString('Deleted: false', $content);
    }

    public function testLogInterpolatesNullContext(): void
    {
        $this->logger->info('Value: {value}', ['value' => null]);

        $content = $this->getLogContent();

        $this->assertStringContainsString('Value: null', $content);
    }
}
