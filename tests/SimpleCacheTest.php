<?php

use Sy\Cache\SimpleCache;
use Psr\SimpleCache\InvalidArgumentException;

class SimpleCacheTest extends \PHPUnit\Framework\TestCase {

	protected $cache;

	protected function setUp() : void {
		$this->cache = new SimpleCache(__DIR__);
	}

	protected function tearDown() : void {
		$this->cache->clear();
	}

	public function testSetGet() {
		$this->assertTrue($this->cache->set('foo', 'bar'));
		$this->assertEquals('bar', $this->cache->get('foo'));
	}

	public function testDelete() {
		$this->cache->delete('foo');
		$this->assertNull($this->cache->get('foo'));
	}

	public function testKey() {
		$this->expectException(InvalidArgumentException::class);
		$this->assertTrue($this->cache->set('a', 'value1'));
		$this->assertTrue($this->cache->set('b/c', 'value2'));
		$this->assertTrue($this->cache->set('d/e/f', 'value3'));
		$this->assertEquals($this->cache->get('a'), 'value1');
		$this->assertEquals($this->cache->get('b/c'), 'value2');
		$this->assertEquals($this->cache->get('d/e/f'), 'value3');
		$this->cache->get(null);
	}

	public function testMiss() {
		$this->assertNull($this->cache->get('notfound'));
		$this->assertEquals($this->cache->get('notfound', 'default'), 'default');
	}

	public function testClear() {
		$this->assertTrue($this->cache->clear());
		$this->assertDirectoryDoesNotExist(__DIR__ . '/cache');
	}

	public function testHas() {
		$this->cache->set('foo', 'bar');
		$this->assertTrue($this->cache->has('foo'));
	}

	public function testHasNot() {
		$this->assertFalse($this->cache->has('boo'));
	}

	public function testSetGetMultiple() {
		$values = [
			'key1' => 'value1',
			'key2' => 'value2',
			'key3' => 'value3',
		];
		$cache = $this->cache;
		$cache->setMultiple($values);
		$result = $cache->getMultiple(array_keys($values));
		foreach ($result as $key => $value) {
			$this->assertTrue(isset($values[$key]));
			$this->assertEquals($values[$key], $value);
			unset($values[$key]);
		}
		// The list of values should now be empty
		$this->assertEquals([], $values);
	}

	public function testKeys() {
		$this->expectException(InvalidArgumentException::class);
		$this->cache->getMultiple(null);
	}

	public function testArray() {
		$this->assertTrue($this->cache->set('array', ['one', 'two', 'three']));
		$this->assertEquals($this->cache->get('array'), ['one', 'two', 'three']);
	}

	public function testObject() {
		$object = new stdClass();
		$this->assertTrue($this->cache->set('object', $object));
		$this->assertEquals($this->cache->get('object'), $object);
	}

}