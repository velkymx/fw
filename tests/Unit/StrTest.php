<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use Fw\Support\Str;
use Fw\Support\Stringable;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Str::flushCache();
    }

    // ==========================================
    // OF (STRINGABLE FACTORY) TEST
    // ==========================================

    public function testOfReturnsStringable(): void
    {
        $result = Str::of('hello');
        $this->assertInstanceOf(Stringable::class, $result);
        $this->assertEquals('hello', (string) $result);
    }

    // ==========================================
    // AFTER TESTS
    // ==========================================

    public function testAfterReturnsStringAfterSearch(): void
    {
        $this->assertEquals('World', Str::after('Hello World', 'Hello '));
        $this->assertEquals('bar', Str::after('foo/bar', '/'));
    }

    public function testAfterReturnsOriginalWhenSearchNotFound(): void
    {
        $this->assertEquals('Hello World', Str::after('Hello World', 'xyz'));
    }

    public function testAfterReturnsOriginalWhenSearchEmpty(): void
    {
        $this->assertEquals('Hello', Str::after('Hello', ''));
    }

    // ==========================================
    // AFTER LAST TESTS
    // ==========================================

    public function testAfterLastReturnsStringAfterLastOccurrence(): void
    {
        $this->assertEquals('baz', Str::afterLast('foo/bar/baz', '/'));
        $this->assertEquals('World', Str::afterLast('Hello World World', 'World '));
    }

    public function testAfterLastReturnsOriginalWhenSearchNotFound(): void
    {
        $this->assertEquals('foo/bar', Str::afterLast('foo/bar', 'xyz'));
    }

    // ==========================================
    // BEFORE TESTS
    // ==========================================

    public function testBeforeReturnsStringBeforeSearch(): void
    {
        $this->assertEquals('Hello', Str::before('Hello World', ' World'));
        $this->assertEquals('foo', Str::before('foo/bar', '/'));
    }

    public function testBeforeReturnsOriginalWhenSearchNotFound(): void
    {
        $this->assertEquals('Hello World', Str::before('Hello World', 'xyz'));
    }

    public function testBeforeReturnsOriginalWhenSearchEmpty(): void
    {
        $this->assertEquals('Hello', Str::before('Hello', ''));
    }

    // ==========================================
    // BEFORE LAST TESTS
    // ==========================================

    public function testBeforeLastReturnsStringBeforeLastOccurrence(): void
    {
        $this->assertEquals('foo/bar', Str::beforeLast('foo/bar/baz', '/'));
        $this->assertEquals('Hello World ', Str::beforeLast('Hello World World', 'World'));
    }

    public function testBeforeLastReturnsOriginalWhenSearchNotFound(): void
    {
        $this->assertEquals('foo/bar', Str::beforeLast('foo/bar', 'xyz'));
    }

    // ==========================================
    // BETWEEN TESTS
    // ==========================================

    public function testBetweenReturnsStringBetweenDelimiters(): void
    {
        $this->assertEquals('content', Str::between('[content]', '[', ']'));
        $this->assertEquals('bar', Str::between('foo bar baz', 'foo ', ' baz'));
    }

    public function testBetweenFirstReturnsSmallestPortion(): void
    {
        $this->assertEquals('a', Str::betweenFirst('[a][b]', '[', ']'));
    }

    // ==========================================
    // CAMEL CASE TESTS
    // ==========================================

    public function testCamelConvertsToCase(): void
    {
        $this->assertEquals('fooBar', Str::camel('foo_bar'));
        $this->assertEquals('fooBar', Str::camel('foo-bar'));
        $this->assertEquals('fooBar', Str::camel('Foo Bar'));
        $this->assertEquals('fooBar', Str::camel('FooBar'));
    }

    public function testCamelUsesCache(): void
    {
        $result1 = Str::camel('foo_bar');
        $result2 = Str::camel('foo_bar');
        $this->assertEquals($result1, $result2);
    }

    // ==========================================
    // CONTAINS TESTS
    // ==========================================

    public function testContainsReturnsTrueWhenFound(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'World'));
        $this->assertTrue(Str::contains('Hello World', ['World', 'missing']));
    }

    public function testContainsReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse(Str::contains('Hello World', 'xyz'));
        $this->assertFalse(Str::contains('Hello World', ['abc', 'xyz']));
    }

    public function testContainsIgnoreCase(): void
    {
        $this->assertTrue(Str::contains('Hello World', 'WORLD', ignoreCase: true));
        $this->assertFalse(Str::contains('Hello World', 'WORLD', ignoreCase: false));
    }

    public function testContainsAll(): void
    {
        $this->assertTrue(Str::containsAll('Hello World', ['Hello', 'World']));
        $this->assertFalse(Str::containsAll('Hello World', ['Hello', 'missing']));
    }

    // ==========================================
    // ENDS WITH TESTS
    // ==========================================

    public function testEndsWithReturnsTrueWhenMatches(): void
    {
        $this->assertTrue(Str::endsWith('Hello World', 'World'));
        $this->assertTrue(Str::endsWith('Hello World', ['World', 'missing']));
    }

    public function testEndsWithReturnsFalseWhenNotMatches(): void
    {
        $this->assertFalse(Str::endsWith('Hello World', 'Hello'));
        $this->assertFalse(Str::endsWith('Hello World', ['abc', 'xyz']));
    }

    // ==========================================
    // STARTS WITH TESTS
    // ==========================================

    public function testStartsWithReturnsTrueWhenMatches(): void
    {
        $this->assertTrue(Str::startsWith('Hello World', 'Hello'));
        $this->assertTrue(Str::startsWith('Hello World', ['Hello', 'missing']));
    }

    public function testStartsWithReturnsFalseWhenNotMatches(): void
    {
        $this->assertFalse(Str::startsWith('Hello World', 'World'));
        $this->assertFalse(Str::startsWith('Hello World', ['abc', 'xyz']));
    }

    // ==========================================
    // FINISH / START TESTS
    // ==========================================

    public function testFinishAddsCapOnce(): void
    {
        $this->assertEquals('path/', Str::finish('path', '/'));
        $this->assertEquals('path/', Str::finish('path/', '/'));
        $this->assertEquals('path/', Str::finish('path//', '/'));
    }

    public function testStartAddsPrefixOnce(): void
    {
        $this->assertEquals('/path', Str::start('path', '/'));
        $this->assertEquals('/path', Str::start('/path', '/'));
        $this->assertEquals('/path', Str::start('//path', '/'));
    }

    // ==========================================
    // WRAP TESTS
    // ==========================================

    public function testWrapWrapsString(): void
    {
        $this->assertEquals('"hello"', Str::wrap('hello', '"'));
        $this->assertEquals('[hello]', Str::wrap('hello', '[', ']'));
    }

    // ==========================================
    // IS (PATTERN MATCHING) TESTS
    // ==========================================

    public function testIsMatchesExactString(): void
    {
        $this->assertTrue(Str::is('hello', 'hello'));
        $this->assertFalse(Str::is('hello', 'world'));
    }

    public function testIsMatchesWildcard(): void
    {
        $this->assertTrue(Str::is('hello*', 'hello world'));
        $this->assertTrue(Str::is('*world', 'hello world'));
        $this->assertTrue(Str::is('*llo*', 'hello world'));
    }

    public function testIsMatchesMultiplePatterns(): void
    {
        $this->assertTrue(Str::is(['hello', 'world'], 'hello'));
        $this->assertTrue(Str::is(['foo*', 'bar*'], 'foobar'));
        $this->assertFalse(Str::is(['foo*', 'bar*'], 'baz'));
    }

    // ==========================================
    // IS ASCII TESTS
    // ==========================================

    public function testIsAscii(): void
    {
        $this->assertTrue(Str::isAscii('Hello World'));
        $this->assertFalse(Str::isAscii('Hello Wörld'));
        $this->assertFalse(Str::isAscii('こんにちは'));
    }

    // ==========================================
    // IS JSON TESTS
    // ==========================================

    public function testIsJson(): void
    {
        $this->assertTrue(Str::isJson('{"name":"John"}'));
        $this->assertTrue(Str::isJson('[1, 2, 3]'));
        $this->assertTrue(Str::isJson('"string"'));
        $this->assertFalse(Str::isJson('not json'));
        $this->assertFalse(Str::isJson(''));
    }

    // ==========================================
    // IS UUID TESTS
    // ==========================================

    public function testIsUuid(): void
    {
        $this->assertTrue(Str::isUuid('550e8400-e29b-41d4-a716-446655440000'));
        $this->assertTrue(Str::isUuid('550E8400-E29B-41D4-A716-446655440000'));
        $this->assertFalse(Str::isUuid('not-a-uuid'));
        $this->assertFalse(Str::isUuid('550e8400-e29b-41d4-a716'));
    }

    // ==========================================
    // IS ULID TESTS
    // ==========================================

    public function testIsUlid(): void
    {
        $this->assertTrue(Str::isUlid('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertFalse(Str::isUlid('not-a-ulid'));
        $this->assertFalse(Str::isUlid('too-short'));
    }

    // ==========================================
    // KEBAB CASE TESTS
    // ==========================================

    public function testKebabConvertsToCase(): void
    {
        $this->assertEquals('foo-bar', Str::kebab('fooBar'));
        $this->assertEquals('foo-bar', Str::kebab('FooBar'));
        // Note: kebab converts camelCase to kebab-case, not snake_case to kebab-case
        $this->assertEquals('foo-bar', Str::kebab('Foo Bar'));
    }

    // ==========================================
    // LENGTH TESTS
    // ==========================================

    public function testLengthReturnsCharacterCount(): void
    {
        $this->assertEquals(5, Str::length('hello'));
        $this->assertEquals(5, Str::length('héllo'));
        $this->assertEquals(3, Str::length('日本語'));
    }

    // ==========================================
    // LIMIT TESTS
    // ==========================================

    public function testLimitTruncatesWithEllipsis(): void
    {
        $this->assertEquals('Hello...', Str::limit('Hello World', 5));
        $this->assertEquals('Hello>>>', Str::limit('Hello World', 5, '>>>'));
    }

    public function testLimitDoesNotTruncateShortStrings(): void
    {
        $this->assertEquals('Hello', Str::limit('Hello', 10));
    }

    // ==========================================
    // LOWER / UPPER TESTS
    // ==========================================

    public function testLowerConvertsToLowerCase(): void
    {
        $this->assertEquals('hello world', Str::lower('HELLO WORLD'));
        $this->assertEquals('hello world', Str::lower('Hello World'));
    }

    public function testUpperConvertsToUpperCase(): void
    {
        $this->assertEquals('HELLO WORLD', Str::upper('hello world'));
        $this->assertEquals('HELLO WORLD', Str::upper('Hello World'));
    }

    // ==========================================
    // TITLE TESTS
    // ==========================================

    public function testTitleConvertsToTitleCase(): void
    {
        $this->assertEquals('Hello World', Str::title('hello world'));
        $this->assertEquals('Hello World', Str::title('HELLO WORLD'));
    }

    // ==========================================
    // HEADLINE TESTS
    // ==========================================

    public function testHeadlineConvertsToHeadline(): void
    {
        $this->assertEquals('Hello World', Str::headline('hello_world'));
        $this->assertEquals('Hello World', Str::headline('helloWorld'));
        $this->assertEquals('Hello World', Str::headline('hello-world'));
    }

    // ==========================================
    // WORDS TESTS
    // ==========================================

    public function testWordsLimitsWordCount(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog';
        $this->assertEquals('The quick brown...', Str::words($text, 3));
        $this->assertEquals('The quick brown>>>',Str::words($text, 3, '>>>'));
    }

    public function testWordsDoesNotTruncateShortStrings(): void
    {
        $this->assertEquals('Hello World', Str::words('Hello World', 10));
    }

    // ==========================================
    // MASK TESTS
    // ==========================================

    public function testMaskMasksPortionOfString(): void
    {
        $this->assertEquals('hel**', Str::mask('hello', '*', 3));
        $this->assertEquals('***lo', Str::mask('hello', '*', 0, 3));
        $this->assertEquals('h***o', Str::mask('hello', '*', 1, 3));
    }

    public function testMaskWithNegativeIndex(): void
    {
        $this->assertEquals('hel**', Str::mask('hello', '*', -2));
    }

    public function testMaskWithEmptyCharacterReturnsOriginal(): void
    {
        $this->assertEquals('hello', Str::mask('hello', '', 3));
    }

    // ==========================================
    // PAD TESTS
    // ==========================================

    public function testPadBoth(): void
    {
        $this->assertEquals('  hi  ', Str::padBoth('hi', 6));
        $this->assertEquals('--hi--', Str::padBoth('hi', 6, '-'));
    }

    public function testPadLeft(): void
    {
        $this->assertEquals('    hi', Str::padLeft('hi', 6));
        $this->assertEquals('----hi', Str::padLeft('hi', 6, '-'));
    }

    public function testPadRight(): void
    {
        $this->assertEquals('hi    ', Str::padRight('hi', 6));
        $this->assertEquals('hi----', Str::padRight('hi', 6, '-'));
    }

    // ==========================================
    // RANDOM TESTS
    // ==========================================

    public function testRandomGeneratesString(): void
    {
        $result = Str::random(16);
        $this->assertEquals(16, strlen($result));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $result);
    }

    public function testRandomGeneratesUniqueStrings(): void
    {
        $result1 = Str::random();
        $result2 = Str::random();
        $this->assertNotEquals($result1, $result2);
    }

    // ==========================================
    // REPEAT TESTS
    // ==========================================

    public function testRepeatRepeatsString(): void
    {
        $this->assertEquals('abcabc', Str::repeat('abc', 2));
        $this->assertEquals('---', Str::repeat('-', 3));
    }

    // ==========================================
    // REPLACE TESTS
    // ==========================================

    public function testReplaceReplacesString(): void
    {
        $this->assertEquals('Hello World', Str::replace('foo', 'Hello', 'foo World'));
    }

    public function testReplaceFirstReplacesFirstOccurrence(): void
    {
        $this->assertEquals('Hello bar bar', Str::replaceFirst('bar', 'Hello', 'bar bar bar'));
    }

    public function testReplaceLastReplacesLastOccurrence(): void
    {
        $this->assertEquals('bar bar Hello', Str::replaceLast('bar', 'Hello', 'bar bar bar'));
    }

    public function testReplaceArray(): void
    {
        $result = Str::replaceArray('?', ['foo', 'bar'], '? and ?');
        $this->assertEquals('foo and bar', $result);
    }

    public function testReplaceCaseInsensitive(): void
    {
        $this->assertEquals('Hello World', Str::replace('FOO', 'Hello', 'foo World', caseSensitive: false));
    }

    // ==========================================
    // REVERSE TESTS
    // ==========================================

    public function testReverseReversesString(): void
    {
        $this->assertEquals('olleh', Str::reverse('hello'));
        $this->assertEquals('日本', Str::reverse('本日'));
    }

    // ==========================================
    // SLUG TESTS
    // ==========================================

    public function testSlugGeneratesUrlFriendlyString(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
        $this->assertEquals('hello-world', Str::slug('Hello  World'));
        $this->assertEquals('hello-world', Str::slug('Hello_World'));
    }

    public function testSlugWithCustomSeparator(): void
    {
        $this->assertEquals('hello_world', Str::slug('Hello World', '_'));
    }

    public function testSlugWithDictionary(): void
    {
        $this->assertEquals('hello-at-world', Str::slug('Hello@World'));
    }

    // ==========================================
    // SNAKE CASE TESTS
    // ==========================================

    public function testSnakeConvertsToCase(): void
    {
        $this->assertEquals('foo_bar', Str::snake('fooBar'));
        $this->assertEquals('foo_bar', Str::snake('FooBar'));
        $this->assertEquals('foo_bar', Str::snake('foo bar'));
    }

    public function testSnakeUsesCache(): void
    {
        $result1 = Str::snake('fooBar');
        $result2 = Str::snake('fooBar');
        $this->assertEquals($result1, $result2);
    }

    // ==========================================
    // SQUISH TESTS
    // ==========================================

    public function testSquishRemovesExtraWhitespace(): void
    {
        $this->assertEquals('hello world', Str::squish('  hello   world  '));
        $this->assertEquals('hello world', Str::squish("hello\n\tworld"));
    }

    // ==========================================
    // STUDLY CASE TESTS
    // ==========================================

    public function testStudlyConvertsToCase(): void
    {
        $this->assertEquals('FooBar', Str::studly('foo_bar'));
        $this->assertEquals('FooBar', Str::studly('foo-bar'));
        $this->assertEquals('FooBar', Str::studly('foo bar'));
    }

    public function testStudlyUsesCache(): void
    {
        $result1 = Str::studly('foo_bar');
        $result2 = Str::studly('foo_bar');
        $this->assertEquals($result1, $result2);
    }

    // ==========================================
    // SUBSTR TESTS
    // ==========================================

    public function testSubstrReturnsSubstring(): void
    {
        $this->assertEquals('llo', Str::substr('hello', 2));
        $this->assertEquals('ell', Str::substr('hello', 1, 3));
    }

    public function testSubstrCount(): void
    {
        $this->assertEquals(3, Str::substrCount('foo bar foo baz foo', 'foo'));
    }

    public function testSubstrReplace(): void
    {
        $this->assertEquals('fooXXX', Str::substrReplace('foobar', 'XXX', 3));
        $this->assertEquals('fXXXar', Str::substrReplace('foobar', 'XXX', 1, 3));
    }

    // ==========================================
    // SWAP TESTS
    // ==========================================

    public function testSwapReplacesMultipleKeywords(): void
    {
        $result = Str::swap(['foo' => 'bar', 'hello' => 'world'], 'foo hello');
        $this->assertEquals('bar world', $result);
    }

    // ==========================================
    // TAKE TESTS
    // ==========================================

    public function testTakeReturnsFirstCharacters(): void
    {
        $this->assertEquals('hel', Str::take('hello', 3));
    }

    public function testTakeReturnsLastCharactersWithNegative(): void
    {
        $this->assertEquals('llo', Str::take('hello', -3));
    }

    // ==========================================
    // TRIM TESTS
    // ==========================================

    public function testTrimRemovesWhitespace(): void
    {
        $this->assertEquals('hello', Str::trim('  hello  '));
    }

    public function testTrimWithCustomCharlist(): void
    {
        $this->assertEquals('hello', Str::trim('---hello---', '-'));
    }

    public function testLtrimRemovesLeftWhitespace(): void
    {
        $this->assertEquals('hello  ', Str::ltrim('  hello  '));
    }

    public function testRtrimRemovesRightWhitespace(): void
    {
        $this->assertEquals('  hello', Str::rtrim('  hello  '));
    }

    // ==========================================
    // UCFIRST / LCFIRST TESTS
    // ==========================================

    public function testUcfirstMakesFirstCharUppercase(): void
    {
        $this->assertEquals('Hello', Str::ucfirst('hello'));
        $this->assertEquals('Hello world', Str::ucfirst('hello world'));
    }

    public function testLcfirstMakesFirstCharLowercase(): void
    {
        $this->assertEquals('hello', Str::lcfirst('Hello'));
        $this->assertEquals('hELLO WORLD', Str::lcfirst('HELLO WORLD'));
    }

    // ==========================================
    // UCSPLIT TESTS
    // ==========================================

    public function testUcsplitSplitsByUppercase(): void
    {
        $this->assertEquals(['Foo', 'Bar', 'Baz'], Str::ucsplit('FooBarBaz'));
        $this->assertEquals(['foo', 'Bar', 'Baz'], Str::ucsplit('fooBarBaz'));
    }

    // ==========================================
    // WORD COUNT TESTS
    // ==========================================

    public function testWordCountReturnsCount(): void
    {
        $this->assertEquals(4, Str::wordCount('Hello World Foo Bar'));
        $this->assertEquals(1, Str::wordCount('Hello'));
    }

    // ==========================================
    // WORD WRAP TESTS
    // ==========================================

    public function testWordWrapWrapsText(): void
    {
        $text = 'The quick brown fox';
        $result = Str::wordWrap($text, 10, "\n");
        $this->assertStringContainsString("\n", $result);
    }

    // ==========================================
    // UUID TESTS
    // ==========================================

    public function testUuidGeneratesValidUuid(): void
    {
        $uuid = Str::uuid();
        $this->assertTrue(Str::isUuid($uuid));
        $this->assertEquals(36, strlen($uuid));
    }

    public function testUuidGeneratesUniqueValues(): void
    {
        $uuid1 = Str::uuid();
        $uuid2 = Str::uuid();
        $this->assertNotEquals($uuid1, $uuid2);
    }

    // ==========================================
    // ULID TESTS
    // ==========================================

    public function testUlidGeneratesValidUlid(): void
    {
        $ulid = Str::ulid();
        $this->assertTrue(Str::isUlid($ulid));
        $this->assertEquals(26, strlen($ulid));
    }

    public function testUlidGeneratesUniqueValues(): void
    {
        $ulid1 = Str::ulid();
        $ulid2 = Str::ulid();
        $this->assertNotEquals($ulid1, $ulid2);
    }

    // ==========================================
    // ASCII TESTS
    // ==========================================

    public function testAsciiTransliteratesUnicode(): void
    {
        $this->assertEquals('Hello', Str::ascii('Hello'));
        // Note: transliteration depends on intl extension
    }

    // ==========================================
    // IS EMPTY TESTS
    // ==========================================

    public function testIsEmptyReturnsTrueForEmpty(): void
    {
        $this->assertTrue(Str::isEmpty(''));
        $this->assertTrue(Str::isEmpty('   '));
        $this->assertTrue(Str::isEmpty(null));
    }

    public function testIsEmptyReturnsFalseForNonEmpty(): void
    {
        $this->assertFalse(Str::isEmpty('hello'));
        $this->assertFalse(Str::isEmpty(' a '));
    }

    public function testIsNotEmptyReturnsTrueForNonEmpty(): void
    {
        $this->assertTrue(Str::isNotEmpty('hello'));
        $this->assertFalse(Str::isNotEmpty(''));
    }

    // ==========================================
    // PARSE CALLBACK TESTS
    // ==========================================

    public function testParseCallbackSplitsClassMethod(): void
    {
        $this->assertEquals(['UserController', 'index'], Str::parseCallback('UserController@index'));
    }

    public function testParseCallbackReturnsDefaultMethod(): void
    {
        $this->assertEquals(['UserController', 'handle'], Str::parseCallback('UserController', 'handle'));
        $this->assertEquals(['UserController', null], Str::parseCallback('UserController'));
    }

    // ==========================================
    // PLURAL / SINGULAR TESTS
    // ==========================================

    public function testPluralReturnsPlural(): void
    {
        $this->assertEquals('dogs', Str::plural('dog'));
        $this->assertEquals('children', Str::plural('child'));
        $this->assertEquals('boxes', Str::plural('box'));
        $this->assertEquals('quizzes', Str::plural('quiz'));
    }

    public function testPluralReturnsSingularForCountOne(): void
    {
        $this->assertEquals('dog', Str::plural('dog', 1));
        $this->assertEquals('dogs', Str::plural('dog', 2));
        $this->assertEquals('dog', Str::plural('dog', -1));
    }

    public function testPluralWithCountable(): void
    {
        $this->assertEquals('dog', Str::plural('dog', ['item']));
        $this->assertEquals('dogs', Str::plural('dog', ['item', 'item']));
    }

    public function testSingularReturnsSingular(): void
    {
        $this->assertEquals('dog', Str::singular('dogs'));
        $this->assertEquals('child', Str::singular('children'));
        $this->assertEquals('box', Str::singular('boxes'));
        $this->assertEquals('quiz', Str::singular('quizzes'));
    }

    // ==========================================
    // EXCERPT TESTS
    // ==========================================

    public function testExcerptExtractsText(): void
    {
        $text = 'This is a long text that contains a special phrase in the middle of it.';
        $result = Str::excerpt($text, 'special');

        $this->assertNotNull($result);
        $this->assertStringContainsString('special', $result);
    }

    public function testExcerptReturnsNullWhenPhraseNotFound(): void
    {
        $text = 'This is some text.';
        $result = Str::excerpt($text, 'missing');

        $this->assertNull($result);
    }

    // ==========================================
    // FLUSH CACHE TESTS
    // ==========================================

    public function testFlushCacheClearsCaches(): void
    {
        // Populate caches
        Str::camel('test_value');
        Str::snake('testValue');
        Str::studly('test_value');

        // Flush caches
        Str::flushCache();

        // Caches should be clear but methods should still work
        $this->assertEquals('testValue', Str::camel('test_value'));
        $this->assertEquals('test_value', Str::snake('testValue'));
        $this->assertEquals('TestValue', Str::studly('test_value'));
    }
}
