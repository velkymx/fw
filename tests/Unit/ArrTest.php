<?php

declare(strict_types=1);

namespace Fw\Tests\Unit;

use ArrayAccess;
use Fw\Support\Arr;
use PHPUnit\Framework\TestCase;

final class ArrTest extends TestCase
{
    // ==========================================
    // GET TESTS
    // ==========================================

    public function testGetReturnsValueWithSimpleKey(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $this->assertEquals('John', Arr::get($array, 'name'));
        $this->assertEquals(30, Arr::get($array, 'age'));
    }

    public function testGetReturnsValueWithDotNotation(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                    'zip' => '10001',
                ],
            ],
        ];

        $this->assertEquals('John', Arr::get($array, 'user.name'));
        $this->assertEquals('New York', Arr::get($array, 'user.address.city'));
        $this->assertEquals('10001', Arr::get($array, 'user.address.zip'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $array = ['name' => 'John'];
        $this->assertEquals('default', Arr::get($array, 'missing', 'default'));
        $this->assertNull(Arr::get($array, 'missing'));
    }

    public function testGetReturnsDefaultForMissingNestedKey(): void
    {
        $array = ['user' => ['name' => 'John']];
        $this->assertEquals('N/A', Arr::get($array, 'user.email', 'N/A'));
        $this->assertEquals('N/A', Arr::get($array, 'user.address.city', 'N/A'));
    }

    public function testGetReturnsArrayWhenKeyIsNull(): void
    {
        $array = ['name' => 'John'];
        $this->assertEquals($array, Arr::get($array, null));
    }

    public function testGetWithNumericKey(): void
    {
        $array = ['a', 'b', 'c'];
        $this->assertEquals('b', Arr::get($array, 1));
    }

    public function testGetWithArrayAccess(): void
    {
        $arrayAccess = new class implements ArrayAccess {
            private array $data = ['name' => 'John', 'nested' => ['value' => 'test']];

            public function offsetExists(mixed $offset): bool
            {
                return isset($this->data[$offset]);
            }

            public function offsetGet(mixed $offset): mixed
            {
                return $this->data[$offset];
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
                $this->data[$offset] = $value;
            }

            public function offsetUnset(mixed $offset): void
            {
                unset($this->data[$offset]);
            }
        };

        $this->assertEquals('John', Arr::get($arrayAccess, 'name'));
    }

    // ==========================================
    // SET TESTS
    // ==========================================

    public function testSetWithSimpleKey(): void
    {
        $array = [];
        Arr::set($array, 'name', 'John');
        $this->assertEquals(['name' => 'John'], $array);
    }

    public function testSetWithDotNotation(): void
    {
        $array = [];
        Arr::set($array, 'user.name', 'John');
        $this->assertEquals(['user' => ['name' => 'John']], $array);
    }

    public function testSetCreatesNestedStructure(): void
    {
        $array = [];
        Arr::set($array, 'user.address.city', 'New York');
        $this->assertEquals([
            'user' => [
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ], $array);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $array = ['user' => ['name' => 'John']];
        Arr::set($array, 'user.name', 'Jane');
        $this->assertEquals('Jane', $array['user']['name']);
    }

    public function testSetWithNullKeyReplacesEntireArray(): void
    {
        $array = ['old' => 'value'];
        $result = Arr::set($array, null, ['new' => 'value']);
        $this->assertEquals(['new' => 'value'], $result);
    }

    // ==========================================
    // HAS TESTS
    // ==========================================

    public function testHasReturnsTrueForExistingKey(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $this->assertTrue(Arr::has($array, 'name'));
        $this->assertTrue(Arr::has($array, 'age'));
    }

    public function testHasReturnsTrueForNestedKey(): void
    {
        $array = ['user' => ['name' => 'John']];
        $this->assertTrue(Arr::has($array, 'user.name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $array = ['name' => 'John'];
        $this->assertFalse(Arr::has($array, 'missing'));
        $this->assertFalse(Arr::has($array, 'user.name'));
    }

    public function testHasWithMultipleKeys(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $this->assertTrue(Arr::has($array, ['name', 'age']));
        $this->assertFalse(Arr::has($array, ['name', 'missing']));
    }

    public function testHasReturnsFalseForEmptyArray(): void
    {
        $this->assertFalse(Arr::has([], 'any'));
        $this->assertFalse(Arr::has(['name' => 'John'], []));
    }

    // ==========================================
    // FORGET TESTS
    // ==========================================

    public function testForgetRemovesKey(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        Arr::forget($array, 'name');
        $this->assertEquals(['age' => 30], $array);
    }

    public function testForgetRemovesNestedKey(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        Arr::forget($array, 'user.name');
        $this->assertEquals(['user' => ['age' => 30]], $array);
    }

    public function testForgetRemovesMultipleKeys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        Arr::forget($array, ['a', 'c']);
        $this->assertEquals(['b' => 2], $array);
    }

    public function testForgetHandlesMissingKeysGracefully(): void
    {
        $array = ['name' => 'John'];
        Arr::forget($array, 'missing');
        $this->assertEquals(['name' => 'John'], $array);
    }

    // ==========================================
    // PLUCK TESTS
    // ==========================================

    public function testPluckExtractsValues(): void
    {
        $array = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $this->assertEquals(['John', 'Jane'], Arr::pluck($array, 'name'));
    }

    public function testPluckWithKey(): void
    {
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $this->assertEquals([1 => 'John', 2 => 'Jane'], Arr::pluck($array, 'name', 'id'));
    }

    public function testPluckWithNestedKey(): void
    {
        $array = [
            ['user' => ['name' => 'John']],
            ['user' => ['name' => 'Jane']],
        ];

        $this->assertEquals(['John', 'Jane'], Arr::pluck($array, 'user.name'));
    }

    // ==========================================
    // ONLY / EXCEPT TESTS
    // ==========================================

    public function testOnlyReturnsSubset(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $this->assertEquals(['a' => 1, 'c' => 3], Arr::only($array, ['a', 'c']));
    }

    public function testExceptReturnsArrayWithoutKeys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $this->assertEquals(['b' => 2, 'd' => 4], Arr::except($array, ['a', 'c']));
    }

    // ==========================================
    // FIRST / LAST TESTS
    // ==========================================

    public function testFirstReturnsFirstElement(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertEquals(1, Arr::first($array));
    }

    public function testFirstWithCallbackReturnsFirstMatch(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertEquals(3, Arr::first($array, fn($v) => $v > 2));
    }

    public function testFirstReturnsDefaultWhenEmpty(): void
    {
        $this->assertEquals('default', Arr::first([], null, 'default'));
    }

    public function testFirstReturnsDefaultWhenNoMatch(): void
    {
        $array = [1, 2, 3];
        $this->assertEquals('none', Arr::first($array, fn($v) => $v > 10, 'none'));
    }

    public function testLastReturnsLastElement(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertEquals(5, Arr::last($array));
    }

    public function testLastWithCallbackReturnsLastMatch(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertEquals(5, Arr::last($array, fn($v) => $v > 2));
    }

    public function testLastReturnsDefaultWhenEmpty(): void
    {
        $this->assertEquals('default', Arr::last([], null, 'default'));
    }

    // ==========================================
    // FLATTEN TESTS
    // ==========================================

    public function testFlattenMultiDimensionalArray(): void
    {
        $array = [[1, 2], [3, 4], [5]];
        $this->assertEquals([1, 2, 3, 4, 5], Arr::flatten($array));
    }

    public function testFlattenWithDepth(): void
    {
        $array = [1, [2, [3, [4, 5]]]];
        $this->assertEquals([1, 2, [3, [4, 5]]], Arr::flatten($array, 1));
        $this->assertEquals([1, 2, 3, [4, 5]], Arr::flatten($array, 2));
    }

    public function testFlattenDeeplyNested(): void
    {
        $array = [1, [2, [3, [4, [5]]]]];
        $this->assertEquals([1, 2, 3, 4, 5], Arr::flatten($array));
    }

    // ==========================================
    // DOT / UNDOT TESTS
    // ==========================================

    public function testDotFlattensWithDotKeys(): void
    {
        $array = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ];

        $expected = [
            'user.name' => 'John',
            'user.address.city' => 'New York',
        ];

        $this->assertEquals($expected, Arr::dot($array));
    }

    public function testDotWithPrepend(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $expected = ['user.name' => 'John', 'user.age' => 30];

        $this->assertEquals($expected, Arr::dot($array, 'user.'));
    }

    public function testUndotExpandsDotNotation(): void
    {
        $array = [
            'user.name' => 'John',
            'user.address.city' => 'New York',
        ];

        $expected = [
            'user' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                ],
            ],
        ];

        $this->assertEquals($expected, Arr::undot($array));
    }

    // ==========================================
    // WRAP TESTS
    // ==========================================

    public function testWrapReturnsArrayAsIs(): void
    {
        $array = [1, 2, 3];
        $this->assertEquals([1, 2, 3], Arr::wrap($array));
    }

    public function testWrapWrapsScalar(): void
    {
        $this->assertEquals(['value'], Arr::wrap('value'));
        $this->assertEquals([42], Arr::wrap(42));
    }

    public function testWrapReturnsEmptyArrayForNull(): void
    {
        $this->assertEquals([], Arr::wrap(null));
    }

    // ==========================================
    // WHERE TESTS
    // ==========================================

    public function testWhereFiltersArray(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Arr::where($array, fn($v) => $v > 2);
        $this->assertEquals([2 => 3, 3 => 4, 4 => 5], $result);
    }

    public function testWhereNotNullFiltersNulls(): void
    {
        $array = [1, null, 2, null, 3];
        $result = Arr::whereNotNull($array);
        $this->assertEquals([0 => 1, 2 => 2, 4 => 3], $result);
    }

    // ==========================================
    // GROUP BY TESTS
    // ==========================================

    public function testGroupByField(): void
    {
        $array = [
            ['status' => 'active', 'name' => 'John'],
            ['status' => 'active', 'name' => 'Jane'],
            ['status' => 'inactive', 'name' => 'Bob'],
        ];

        $result = Arr::groupBy($array, 'status');

        $this->assertCount(2, $result);
        $this->assertCount(2, $result['active']);
        $this->assertCount(1, $result['inactive']);
    }

    public function testGroupByCallback(): void
    {
        $array = [1, 2, 3, 4, 5, 6];
        $result = Arr::groupBy($array, fn($v) => $v % 2 === 0 ? 'even' : 'odd');

        $this->assertEquals([1, 3, 5], $result['odd']);
        $this->assertEquals([2, 4, 6], $result['even']);
    }

    public function testGroupByPreservesKeys(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 1];
        $result = Arr::groupBy($array, fn($v) => $v, preserveKeys: true);

        $this->assertEquals(['a' => 1, 'c' => 1], $result[1]);
        $this->assertEquals(['b' => 2], $result[2]);
    }

    // ==========================================
    // KEY BY TESTS
    // ==========================================

    public function testKeyByField(): void
    {
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $result = Arr::keyBy($array, 'id');

        $this->assertEquals('John', $result[1]['name']);
        $this->assertEquals('Jane', $result[2]['name']);
    }

    public function testKeyByCallback(): void
    {
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $result = Arr::keyBy($array, fn($item) => $item['name']);

        $this->assertEquals(1, $result['John']['id']);
        $this->assertEquals(2, $result['Jane']['id']);
    }

    // ==========================================
    // SORT BY TESTS
    // ==========================================

    public function testSortByKey(): void
    {
        $array = [
            ['name' => 'Charlie'],
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ];

        $result = array_values(Arr::sortBy($array, 'name'));

        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
        $this->assertEquals('Charlie', $result[2]['name']);
    }

    public function testSortByDesc(): void
    {
        $array = [
            ['age' => 30],
            ['age' => 25],
            ['age' => 35],
        ];

        $result = array_values(Arr::sortByDesc($array, 'age'));

        $this->assertEquals(35, $result[0]['age']);
        $this->assertEquals(30, $result[1]['age']);
        $this->assertEquals(25, $result[2]['age']);
    }

    // ==========================================
    // UNIQUE TESTS
    // ==========================================

    public function testUniqueRemovesDuplicates(): void
    {
        $array = [1, 2, 2, 3, 3, 3];
        $this->assertEquals([1, 2, 3], array_values(Arr::unique($array)));
    }

    public function testUniqueByKey(): void
    {
        $array = [
            ['type' => 'a', 'value' => 1],
            ['type' => 'b', 'value' => 2],
            ['type' => 'a', 'value' => 3],
        ];

        $result = array_values(Arr::unique($array, 'type'));

        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['value']);
        $this->assertEquals(2, $result[1]['value']);
    }

    // ==========================================
    // COLLAPSE TESTS
    // ==========================================

    public function testCollapseArrayOfArrays(): void
    {
        $array = [[1, 2], [3, 4], [5]];
        $this->assertEquals([1, 2, 3, 4, 5], Arr::collapse($array));
    }

    public function testCollapseIgnoresNonArrays(): void
    {
        $array = [[1, 2], 'string', [3, 4]];
        $this->assertEquals([1, 2, 3, 4], Arr::collapse($array));
    }

    // ==========================================
    // PREPEND TESTS
    // ==========================================

    public function testPrependAddsToBeginning(): void
    {
        $array = [2, 3, 4];
        $this->assertEquals([1, 2, 3, 4], Arr::prepend($array, 1));
    }

    public function testPrependWithKey(): void
    {
        $array = ['b' => 2];
        $this->assertEquals(['a' => 1, 'b' => 2], Arr::prepend($array, 1, 'a'));
    }

    // ==========================================
    // PULL TESTS
    // ==========================================

    public function testPullRemovesAndReturnsValue(): void
    {
        $array = ['name' => 'John', 'age' => 30];
        $value = Arr::pull($array, 'name');

        $this->assertEquals('John', $value);
        $this->assertEquals(['age' => 30], $array);
    }

    public function testPullReturnsDefaultForMissingKey(): void
    {
        $array = ['name' => 'John'];
        $value = Arr::pull($array, 'missing', 'default');

        $this->assertEquals('default', $value);
    }

    // ==========================================
    // RANDOM TESTS
    // ==========================================

    public function testRandomReturnsSingleValue(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Arr::random($array);
        $this->assertContains($result, $array);
    }

    public function testRandomReturnsMultipleValues(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Arr::random($array, 3);

        $this->assertCount(3, $result);
        foreach ($result as $value) {
            $this->assertContains($value, $array);
        }
    }

    public function testRandomThrowsForTooManyRequested(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Arr::random([1, 2], 5);
    }

    public function testRandomReturnsEmptyArrayForZero(): void
    {
        $this->assertEquals([], Arr::random([1, 2, 3], 0));
    }

    // ==========================================
    // SHUFFLE TESTS
    // ==========================================

    public function testShuffleReturnsArrayWithSameElements(): void
    {
        $array = [1, 2, 3, 4, 5];
        $shuffled = Arr::shuffle($array);

        sort($shuffled);
        $this->assertEquals([1, 2, 3, 4, 5], $shuffled);
    }

    // ==========================================
    // ANY / ALL TESTS
    // ==========================================

    public function testAnyReturnsTrueWhenSomeMatch(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertTrue(Arr::any($array, fn($v) => $v > 3));
    }

    public function testAnyReturnsFalseWhenNoneMatch(): void
    {
        $array = [1, 2, 3];
        $this->assertFalse(Arr::any($array, fn($v) => $v > 10));
    }

    public function testAllReturnsTrueWhenAllMatch(): void
    {
        $array = [2, 4, 6, 8];
        $this->assertTrue(Arr::all($array, fn($v) => $v % 2 === 0));
    }

    public function testAllReturnsFalseWhenNotAllMatch(): void
    {
        $array = [2, 4, 5, 8];
        $this->assertFalse(Arr::all($array, fn($v) => $v % 2 === 0));
    }

    // ==========================================
    // DIVIDE TESTS
    // ==========================================

    public function testDivideSplitsKeysAndValues(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        [$keys, $values] = Arr::divide($array);

        $this->assertEquals(['a', 'b', 'c'], $keys);
        $this->assertEquals([1, 2, 3], $values);
    }

    // ==========================================
    // CROSS JOIN TESTS
    // ==========================================

    public function testCrossJoinReturnsAllCombinations(): void
    {
        $result = Arr::crossJoin([1, 2], ['a', 'b']);

        $this->assertCount(4, $result);
        $this->assertContains([1, 'a'], $result);
        $this->assertContains([1, 'b'], $result);
        $this->assertContains([2, 'a'], $result);
        $this->assertContains([2, 'b'], $result);
    }

    public function testCrossJoinWithThreeArrays(): void
    {
        $result = Arr::crossJoin([1], ['a', 'b'], ['x', 'y']);

        $this->assertCount(4, $result);
        $this->assertContains([1, 'a', 'x'], $result);
        $this->assertContains([1, 'a', 'y'], $result);
        $this->assertContains([1, 'b', 'x'], $result);
        $this->assertContains([1, 'b', 'y'], $result);
    }

    // ==========================================
    // SLICE TESTS
    // ==========================================

    public function testSliceReturnsSubset(): void
    {
        $array = [1, 2, 3, 4, 5];
        $this->assertEquals([3, 4], Arr::slice($array, 2, 2));
    }

    public function testSliceWithPreserveKeys(): void
    {
        $array = [1, 2, 3, 4, 5];
        $result = Arr::slice($array, 2, 2, true);
        $this->assertEquals([2 => 3, 3 => 4], $result);
    }

    // ==========================================
    // MAP TESTS
    // ==========================================

    public function testMapAppliesCallback(): void
    {
        $array = [1, 2, 3];
        $result = Arr::map($array, fn($v) => $v * 2);
        $this->assertEquals([2, 4, 6], $result);
    }

    public function testMapPreservesKeys(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $result = Arr::map($array, fn($v) => $v * 2);
        $this->assertEquals(['a' => 2, 'b' => 4], $result);
    }

    public function testMapWithKeysProvidesKeyToCallback(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $result = Arr::map($array, fn($v, $k) => "{$k}:{$v}");
        $this->assertEquals(['a' => 'a:1', 'b' => 'b:2'], $result);
    }

    // ==========================================
    // MAP WITH KEYS TESTS
    // ==========================================

    public function testMapWithKeysTransformsKeysAndValues(): void
    {
        $array = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $result = Arr::mapWithKeys($array, fn($item) => [$item['id'] => $item['name']]);

        $this->assertEquals([1 => 'John', 2 => 'Jane'], $result);
    }

    // ==========================================
    // MERGE RECURSIVE DISTINCT TESTS
    // ==========================================

    public function testMergeRecursiveDistinct(): void
    {
        $array1 = ['a' => ['b' => 1]];
        $array2 = ['a' => ['c' => 2]];

        $result = Arr::mergeRecursiveDistinct($array1, $array2);

        $this->assertEquals(['a' => ['b' => 1, 'c' => 2]], $result);
    }

    public function testMergeRecursiveDistinctWithCallback(): void
    {
        $array1 = ['a' => 1];
        $array2 = ['a' => 2];

        $result = Arr::mergeRecursiveDistinct($array1, $array2, fn($a, $b) => $a + $b);

        $this->assertEquals(['a' => 3], $result);
    }

    // ==========================================
    // UTILITY METHOD TESTS
    // ==========================================

    public function testAccessibleReturnsTrueForArrays(): void
    {
        $this->assertTrue(Arr::accessible([]));
        $this->assertTrue(Arr::accessible([1, 2, 3]));
    }

    public function testAccessibleReturnsTrueForArrayAccess(): void
    {
        $arrayAccess = new class implements ArrayAccess {
            public function offsetExists(mixed $offset): bool
            {
                return true;
            }

            public function offsetGet(mixed $offset): mixed
            {
                return null;
            }

            public function offsetSet(mixed $offset, mixed $value): void
            {
            }

            public function offsetUnset(mixed $offset): void
            {
            }
        };

        $this->assertTrue(Arr::accessible($arrayAccess));
    }

    public function testAccessibleReturnsFalseForScalars(): void
    {
        $this->assertFalse(Arr::accessible('string'));
        $this->assertFalse(Arr::accessible(123));
        $this->assertFalse(Arr::accessible(null));
    }

    public function testExistsReturnsTrueForExistingKey(): void
    {
        $this->assertTrue(Arr::exists(['a' => 1], 'a'));
        $this->assertTrue(Arr::exists([0 => 'a'], 0));
    }

    public function testExistsReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(Arr::exists(['a' => 1], 'b'));
    }

    public function testIsAssocReturnsTrueForAssociativeArray(): void
    {
        $this->assertTrue(Arr::isAssoc(['a' => 1, 'b' => 2]));
        $this->assertTrue(Arr::isAssoc([1 => 'a', 0 => 'b']));
    }

    public function testIsAssocReturnsFalseForList(): void
    {
        $this->assertFalse(Arr::isAssoc([1, 2, 3]));
        $this->assertFalse(Arr::isAssoc(['a', 'b', 'c']));
    }

    public function testIsListReturnsTrueForList(): void
    {
        $this->assertTrue(Arr::isList([1, 2, 3]));
        $this->assertTrue(Arr::isList(['a', 'b', 'c']));
        $this->assertTrue(Arr::isList([]));
    }

    public function testIsListReturnsFalseForAssociative(): void
    {
        $this->assertFalse(Arr::isList(['a' => 1]));
        $this->assertFalse(Arr::isList([1 => 'a', 0 => 'b']));
    }

    public function testToArrayConvertsIterables(): void
    {
        $generator = (function () {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $this->assertEquals(['a', 'b', 'c'], Arr::toArray($generator));
    }

    public function testToArrayReturnsArrayAsIs(): void
    {
        $array = [1, 2, 3];
        $this->assertSame($array, Arr::toArray($array));
    }
}
