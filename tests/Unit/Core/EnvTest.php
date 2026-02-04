<?php

declare(strict_types=1);

namespace Fw\Tests\Unit\Core;

use Fw\Core\Env;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Env::class)]
final class EnvTest extends TestCase
{
    private string $testEnvFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testEnvFile = sys_get_temp_dir() . '/fw_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }
        parent::tearDown();
    }

    #[Test]
    public function itLoadsEnvFile(): void
    {
        file_put_contents($this->testEnvFile, "TEST_VAR=hello\nTEST_NUM=42");

        Env::load($this->testEnvFile);

        $this->assertSame('hello', Env::string('TEST_VAR'));
        $this->assertSame(42, Env::int('TEST_NUM'));
    }

    #[Test]
    public function itReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('default', Env::string('NONEXISTENT_KEY', 'default'));
        $this->assertSame(100, Env::int('NONEXISTENT_INT', 100));
        $this->assertTrue(Env::bool('NONEXISTENT_BOOL', true));
    }

    #[Test]
    public function itParsesBooleanValues(): void
    {
        putenv('TEST_BOOL_TRUE=true');
        putenv('TEST_BOOL_FALSE=false');
        putenv('TEST_BOOL_ONE=1');
        putenv('TEST_BOOL_ZERO=0');

        $this->assertTrue(Env::bool('TEST_BOOL_TRUE'));
        $this->assertFalse(Env::bool('TEST_BOOL_FALSE'));
        $this->assertTrue(Env::bool('TEST_BOOL_ONE'));
        $this->assertFalse(Env::bool('TEST_BOOL_ZERO'));

        // Cleanup
        putenv('TEST_BOOL_TRUE');
        putenv('TEST_BOOL_FALSE');
        putenv('TEST_BOOL_ONE');
        putenv('TEST_BOOL_ZERO');
    }

    #[Test]
    public function itHandlesQuotedValues(): void
    {
        file_put_contents($this->testEnvFile, <<<'ENV'
            QUOTED_SINGLE='single quoted'
            QUOTED_DOUBLE="double quoted"
            QUOTED_SPACES="  spaces around  "
            ENV);

        Env::load($this->testEnvFile);

        $this->assertSame('single quoted', Env::string('QUOTED_SINGLE'));
        $this->assertSame('double quoted', Env::string('QUOTED_DOUBLE'));
        $this->assertSame('  spaces around  ', Env::string('QUOTED_SPACES'));
    }

    #[Test]
    public function itIgnoresComments(): void
    {
        file_put_contents($this->testEnvFile, <<<'ENV'
            # This is a comment
            VALID_KEY=value
            # Another comment
            ENV);

        Env::load($this->testEnvFile);

        $this->assertSame('value', Env::string('VALID_KEY'));
    }
}
