<?php

namespace Message\Cog\Test\Filter;

use Message\Cog\Filter\DataBinder;

/**
 * Class DataBinderTest
 * @package Message\Cog\Test\Filter
 *
 * @author  Thomas Marchant <thomas@mothership.ec>
 */
class DataBinderTest extends \PHPUnit_Framework_TestCase
{
	const FILTER_NAME_1 = 'name_1';
	const FILTER_NAME_2 = 'name_2';
	const NON_FILTER = 'non_filter';

	private $_dataBinder;

	private $_filter1;
	private $_filter2;

	/**
	 * Create mocks of filters, as well as instanciate the DataBuilder class
	 */
	protected function setUp()
	{
		$this->_dataBinder = new DataBinder;

		$this->_filter1 = $this->getMockBuilder('Message\\Cog\\Test\\Filter\\FauxFilter')
			->setConstructorArgs([self::FILTER_NAME_1, self::FILTER_NAME_1])
			->setMethods(['getName', 'setValue'])
			->getMock()
		;

		$this->_filter2 = $this->getMockBuilder('Message\\Cog\\Test\\Filter\\FauxFilter')
			->setConstructorArgs([self::FILTER_NAME_2, self::FILTER_NAME_2])
			->setMethods(['getName', 'setValue'])
			->getMock()
		;
	}

	/**
	 * Test to ensure that binding one value to the filter collection will call the
	 * appropriate methods and return a filter collection with one filter in it
	 */
	public function testBindDataOneValue()
	{
		$data = [
			self::FILTER_NAME_1 => 'foo',
		];

		$filters = $this->getMockBuilder('Message\\Cog\\Filter\\FilterCollection')
			->setConstructorArgs([[$this->_filter1]])
			->setMethods(['exists', 'offsetGet', 'setKey', 'addValidator'])
			->getMock()
		;

		$this->_filter1->expects($this->once())
			->method('setValue')
		;

		$this->_filter1->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_1)
		;

		$filters->expects($this->exactly(1))
			->method('offsetGet')
			->with(self::FILTER_NAME_1)
			->willReturn($this->_filter1)
		;

		$filters->expects($this->once())
			->method('exists')
			->with(self::FILTER_NAME_1)
			->willReturn(true)
		;

		$bound = $this->_dataBinder->bindData($data, $filters);

		$this->assertInstanceOf('Message\\Cog\\Filter\\FilterCollection', $bound);
		$this->assertSame(1, $bound->count());
		$this->assertTrue($bound->exists(self::FILTER_NAME_1));
	}

	/**
	 * Test to ensure that binding multiple values will to the filter collection will
	 * call the appropriate methods and return a filter collection with a matching number
	 * of filters in it
	 */
	public function testBindDataMultipleValues()
	{
		$data = [
			self::FILTER_NAME_1 => 'foo',
			self::FILTER_NAME_2 => 'bar',
		];

		$filters = $this->getMockBuilder('Message\\Cog\\Filter\\FilterCollection')
			->setConstructorArgs([[$this->_filter1, $this->_filter2]])
			->setMethods(['exists', 'offsetGet', 'setKey', 'addValidator'])
			->getMock()
		;

		$this->_filter1->expects($this->once())
			->method('setValue')
		;

		$this->_filter2->expects($this->once())
			->method('setValue')
		;

		$this->_filter1->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_1)
		;

		$this->_filter2->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_2)
		;

		$filters->expects($this->at(0))
			->method('exists')
			->with(self::FILTER_NAME_1)
			->willReturn(true)
		;

		$filters->expects($this->at(1))
			->method('offsetGet')
			->with(self::FILTER_NAME_1)
			->willReturn($this->_filter1)
		;

		$filters->expects($this->at(2))
			->method('exists')
			->with(self::FILTER_NAME_2)
			->willReturn(true)
		;

		$filters->expects($this->at(3))
			->method('offsetGet')
			->with(self::FILTER_NAME_2)
			->willReturn($this->_filter2)
		;

		$bound = $this->_dataBinder->bindData($data, $filters);

		$this->assertInstanceOf('Message\\Cog\\Filter\\FilterCollection', $bound);
		$this->assertSame(2, $bound->count());
		$this->assertTrue($bound->exists(self::FILTER_NAME_1));
		$this->assertTrue($bound->exists(self::FILTER_NAME_2));
	}

	/**
	 * Test to ensure that any values that exist in the data array but do not have a corresponding
	 * filter in the filter collection will be discarded
	 */
	public function testBindDataTooManyValues()
	{
		$data = [
			self::FILTER_NAME_1 => 'foo',
			self::FILTER_NAME_2 => 'bar',
			self::NON_FILTER => 'baz'
		];

		$filters = $this->getMockBuilder('Message\\Cog\\Filter\\FilterCollection')
			->setConstructorArgs([[$this->_filter1, $this->_filter2]])
			->setMethods(['exists', 'offsetGet', 'setKey', 'addValidator'])
			->getMock()
		;

		$this->_filter1->expects($this->once())
			->method('setValue')
		;

		$this->_filter2->expects($this->once())
			->method('setValue')
		;

		$this->_filter1->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_1)
		;

		$this->_filter2->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_2)
		;

		$filters->expects($this->at(0))
			->method('exists')
			->with(self::FILTER_NAME_1)
			->willReturn(true)
		;

		$filters->expects($this->at(1))
			->method('offsetGet')
			->with(self::FILTER_NAME_1)
			->willReturn($this->_filter1)
		;

		$filters->expects($this->at(2))
			->method('exists')
			->with(self::FILTER_NAME_2)
			->willReturn(true)
		;

		$filters->expects($this->at(3))
			->method('offsetGet')
			->with(self::FILTER_NAME_2)
			->willReturn($this->_filter2)
		;

		$filters->expects($this->at(4))
			->method('exists')
			->with(self::NON_FILTER)
			->willReturn(false)
		;

		$bound = $this->_dataBinder->bindData($data, $filters);

		$this->assertInstanceOf('Message\\Cog\\Filter\\FilterCollection', $bound);
		$this->assertSame(2, $bound->count());
		$this->assertTrue($bound->exists(self::FILTER_NAME_1));
		$this->assertTrue($bound->exists(self::FILTER_NAME_2));
		$this->assertFalse($bound->exists(self::NON_FILTER));
	}

	/**
	 * Test to ensure that filters that do not have values attributed to them in the data array
	 * will not be present in the returned filter collection
	 */
	public function testBindFewerValues()
	{
		$data = [
			self::FILTER_NAME_1 => 'foo',
		];

		$filters = $this->getMockBuilder('Message\\Cog\\Filter\\FilterCollection')
			->setConstructorArgs([[$this->_filter1, $this->_filter2]])
			->setMethods(['exists', 'offsetGet', 'setKey', 'addValidator'])
			->getMock()
		;

		$this->_filter1->expects($this->once())
			->method('setValue')
		;

		$this->_filter1->expects($this->any())
			->method('getName')
			->willReturn(self::FILTER_NAME_1)
		;

		$filters->expects($this->at(0))
			->method('exists')
			->with(self::FILTER_NAME_1)
			->willReturn(true)
		;

		$filters->expects($this->at(1))
			->method('offsetGet')
			->with(self::FILTER_NAME_1)
			->willReturn($this->_filter1)
		;

		$bound = $this->_dataBinder->bindData($data, $filters);

		$this->assertInstanceOf('Message\\Cog\\Filter\\FilterCollection', $bound);
		$this->assertSame(1, $bound->count());
		$this->assertTrue($bound->exists(self::FILTER_NAME_1));
		$this->assertFalse($bound->exists(self::FILTER_NAME_2));
	}
}