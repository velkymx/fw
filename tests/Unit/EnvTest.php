<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Core\Env;
use Fw\Tests\TestCase;

final class EnvTest extends TestCase
{
    private string $tempEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        Env::clear();
        $this->tempEnvFile = sys_get_temp_dir() . '/.env.test.' . uniqid();
    }

    protected function tearDown(): void
    {
        Env::clear();
        if (file_exists($this->tempEnvFile)) {
            unlink($this->tempEnvFile);
        }
        parent::tearDown();
    }

    private function createEnvFile(string $content): void
    {
        file_put_contents($this->tempEnvFile, $content);
    }

    public function testLoadParsesSimpleKeyValue(): void
    {
        $this->createEnvFile("APP_NAME=TestApp\nAPP_DEBUG=true");

        Env::load($this->tempEnvFile);

        $this->assertEquals('TestApp', Env::get('APP_NAME'));
    }

    public function testLoadSkipsComments(): void
    {
        $this->createEnvFile("# This is a comment\nAPP_NAME=TestApp");

        Env::load($this->tempEnvFile);

        $this->assertEquals('TestApp', Env::get('APP_NAME'));
        $this->assertNull(Env::get('# This is a comment'));
    }

    public function testLoadSkipsEmptyLines(): void
    {
        $this->createEnvFile("APP_NAME=TestApp\n\n\nAPP_DEBUG=true");

        Env::load($this->tempEnvFile);

        $this->assertEquals('TestApp', Env::get('APP_NAME'));
        $this->assertTrue(Env::get('APP_DEBUG'));
    }

    public function testLoadStripsDoubleQuotes(): void
    {
        $this->createEnvFile('APP_NAME="My App Name"');

        Env::load($this->tempEnvFile);

        $this->assertEquals('My App Name', Env::get('APP_NAME'));
    }

    public function testLoadStripsSingleQuotes(): void
    {
        $this->createEnvFile("APP_NAME='My App Name'");

        Env::load($this->tempEnvFile);

        $this->assertEquals('My App Name', Env::get('APP_NAME'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertEquals('default', Env::get('MISSING_KEY', 'default'));
    }

    public function testGetReturnsNullWhenMissingNoDefault(): void
    {
        $this->assertNull(Env::get('MISSING_KEY'));
    }

    public function testGetCastsBooleanTrue(): void
    {
        $this->createEnvFile("VAL=true");
        Env::load($this->tempEnvFile);

        $this->assertTrue(Env::get('VAL'));
    }

    public function testGetCastsBooleanFalse(): void
    {
        $this->createEnvFile("VAL=false");
        Env::load($this->tempEnvFile);

        $this->assertFalse(Env::get('VAL'));
    }

    public function testGetCastsNull(): void
    {
        $this->createEnvFile("VAL=null");
        Env::load($this->tempEnvFile);

        $this->assertNull(Env::get('VAL'));
    }

    public function testGetCastsInteger(): void
    {
        $this->createEnvFile("PORT=3000");
        Env::load($this->tempEnvFile);

        $this->assertSame(3000, Env::get('PORT'));
    }

    public function testGetCastsFloat(): void
    {
        $this->createEnvFile("RATE=3.14");
        Env::load($this->tempEnvFile);

        $this->assertSame(3.14, Env::get('RATE'));
    }

    public function testStringReturnsString(): void
    {
        $this->createEnvFile("APP_NAME=TestApp");
        Env::load($this->tempEnvFile);

        $this->assertSame('TestApp', Env::string('APP_NAME'));
    }

    public function testStringReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('default', Env::string('MISSING', 'default'));
    }

    public function testIntReturnsInteger(): void
    {
        $this->createEnvFile("PORT=8080");
        Env::load($this->tempEnvFile);

        $this->assertSame(8080, Env::int('PORT'));
    }

    public function testIntReturnsDefaultWhenMissing(): void
    {
        $this->assertSame(3000, Env::int('MISSING', 3000));
    }

    public function testBoolReturnsTrueForTrueValues(): void
    {
        $this->createEnvFile("A=true\nB=1\nC=yes\nD=on");
        Env::load($this->tempEnvFile);

        $this->assertTrue(Env::bool('A'));
        $this->assertTrue(Env::bool('B'));
        $this->assertTrue(Env::bool('C'));
        $this->assertTrue(Env::bool('D'));
    }

    public function testBoolReturnsFalseForFalseValues(): void
    {
        $this->createEnvFile("A=false\nB=0\nC=no\nD=off");
        Env::load($this->tempEnvFile);

        $this->assertFalse(Env::bool('A'));
        $this->assertFalse(Env::bool('B'));
        $this->assertFalse(Env::bool('C'));
        $this->assertFalse(Env::bool('D'));
    }

    public function testBoolReturnsDefaultWhenMissing(): void
    {
        $this->assertTrue(Env::bool('MISSING', true));
        $this->assertFalse(Env::bool('MISSING', false));
    }

    public function testArrayParsesCommaSeparatedValues(): void
    {
        $this->createEnvFile("DOMAINS=localhost,127.0.0.1,example.com");
        Env::load($this->tempEnvFile);

        $result = Env::array('DOMAINS');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertContains('localhost', $result);
        $this->assertContains('127.0.0.1', $result);
        $this->assertContains('example.com', $result);
    }

    public function testArrayTrimsValues(): void
    {
        $this->createEnvFile("DOMAINS=localhost, 127.0.0.1 , example.com");
        Env::load($this->tempEnvFile);

        $result = Env::array('DOMAINS');

        $this->assertEquals(['localhost', '127.0.0.1', 'example.com'], $result);
    }

    public function testArrayReturnsDefaultWhenMissing(): void
    {
        $default = ['a', 'b'];
        $this->assertEquals($default, Env::array('MISSING', $default));
    }

    public function testArrayReturnsDefaultForEmptyValue(): void
    {
        $this->createEnvFile("DOMAINS=");
        Env::load($this->tempEnvFile);

        $this->assertEquals(['default'], Env::array('DOMAINS', ['default']));
    }

    public function testHasReturnsTrueWhenExists(): void
    {
        $this->createEnvFile("APP_NAME=Test");
        Env::load($this->tempEnvFile);

        $this->assertTrue(Env::has('APP_NAME'));
    }

    public function testHasReturnsFalseWhenMissing(): void
    {
        $this->assertFalse(Env::has('MISSING_KEY'));
    }

    public function testRequireReturnsValue(): void
    {
        $this->createEnvFile("APP_NAME=Test");
        Env::load($this->tempEnvFile);

        $this->assertEquals('Test', Env::require('APP_NAME'));
    }

    public function testRequireThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Required environment variable 'MISSING' is not set");

        Env::require('MISSING');
    }

    public function testIsLoadedReturnsFalseBeforeLoad(): void
    {
        $this->assertFalse(Env::isLoaded());
    }

    public function testIsLoadedReturnsTrueAfterLoad(): void
    {
        $this->createEnvFile("APP_NAME=Test");
        Env::load($this->tempEnvFile);

        $this->assertTrue(Env::isLoaded());
    }

    public function testClearResetsState(): void
    {
        $this->createEnvFile("APP_NAME=Test");
        Env::load($this->tempEnvFile);

        $this->assertTrue(Env::isLoaded());

        Env::clear();

        $this->assertFalse(Env::isLoaded());
    }

    public function testLoadNonexistentFileDoesNotThrow(): void
    {
        Env::load('/nonexistent/path/.env');

        $this->assertFalse(Env::isLoaded());
    }

    public function testLoadHandlesValuesWithEqualsSign(): void
    {
        $this->createEnvFile("CONNECTION_STRING=host=localhost;port=5432");
        Env::load($this->tempEnvFile);

        $this->assertEquals('host=localhost;port=5432', Env::get('CONNECTION_STRING'));
    }
}
