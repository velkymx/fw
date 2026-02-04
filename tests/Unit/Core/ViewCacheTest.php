<?php

declare(strict_types=1);

namespace Fw\Tests\Unit\Core;

use Fw\Core\ViewCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ViewCacheTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/fw_view_cache_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
        if (is_dir($this->cachePath)) {
            $files = glob($this->cachePath . '/*.php');
            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
            @rmdir($this->cachePath);
        }
    }

    #[Test]
    public function constructorCreatesCacheDirectory(): void
    {
        new ViewCache($this->cachePath);
        $this->assertDirectoryExists($this->cachePath);
    }

    #[Test]
    public function setAndGetCachesContent(): void
    {
        $cache = new ViewCache($this->cachePath);
        $cache->set('test_key', '<h1>Hello</h1>');

        $result = $cache->get('test_key');
        $this->assertSame('<h1>Hello</h1>', $result);
    }

    #[Test]
    public function getReturnsNullForMissingKey(): void
    {
        $cache = new ViewCache($this->cachePath);

        $result = $cache->get('nonexistent');
        $this->assertNull($result);
    }

    #[Test]
    public function getReturnsNullForExpiredContent(): void
    {
        $cache = new ViewCache($this->cachePath);
        $cache->set('expired_key', '<p>Old content</p>', 1);

        // Wait for expiration
        sleep(2);

        $result = $cache->get('expired_key');
        $this->assertNull($result);
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $cache = new ViewCache($this->cachePath);
        $cache->set('exists', 'content');

        $this->assertTrue($cache->has('exists'));
    }

    #[Test]
    public function hasReturnsFalseForMissingKey(): void
    {
        $cache = new ViewCache($this->cachePath);

        $this->assertFalse($cache->has('missing'));
    }

    #[Test]
    public function forgetRemovesCachedContent(): void
    {
        $cache = new ViewCache($this->cachePath);
        $cache->set('to_forget', 'content');

        $cache->forget('to_forget');

        $this->assertNull($cache->get('to_forget'));
    }

    #[Test]
    public function flushRemovesAllCachedContent(): void
    {
        $cache = new ViewCache($this->cachePath);
        $cache->set('key1', 'content1');
        $cache->set('key2', 'content2');
        $cache->set('key3', 'content3');

        $cache->flush();

        $this->assertNull($cache->get('key1'));
        $this->assertNull($cache->get('key2'));
        $this->assertNull($cache->get('key3'));
    }

    #[Test]
    public function makeKeyGeneratesConsistentKeys(): void
    {
        $key1 = ViewCache::makeKey('home.index', ['user' => 'john']);
        $key2 = ViewCache::makeKey('home.index', ['user' => 'john']);
        $key3 = ViewCache::makeKey('home.index', ['user' => 'jane']);

        $this->assertSame($key1, $key2);
        $this->assertNotSame($key1, $key3);
    }

    #[Test]
    public function makeKeyFiltersNonCacheableData(): void
    {
        // Closures and objects should be filtered out
        $key1 = ViewCache::makeKey('view', [
            'name' => 'test',
            'callback' => fn() => 'ignored',
        ]);

        $key2 = ViewCache::makeKey('view', [
            'name' => 'test',
        ]);

        $this->assertSame($key1, $key2);
    }

    #[Test]
    public function disabledCacheReturnsNull(): void
    {
        $cache = new ViewCache($this->cachePath, false);
        $cache->set('key', 'content');

        $this->assertNull($cache->get('key'));
    }

    #[Test]
    public function permanentCacheNeverExpires(): void
    {
        $cache = new ViewCache($this->cachePath);
        // TTL of 0 means permanent
        $cache->set('permanent', 'forever', 0);

        // Content should still be available
        $this->assertSame('forever', $cache->get('permanent'));
    }
}
