<?php

namespace Modler\Tests;

use PHPUnit\Framework\TestCase;
use Modler\Tests\TestModel;
use InvalidArgumentException;

class ModelTest extends TestCase
{
    private TestModel $model;

    public function setUp(): void
    {
        $this->model = new TestModel();
    }
    public function tearDown(): void
    {
        unset($this->model);
    }

    /**
     * Test that the data given at init is loaded
     */
    public function testLoadData(): void
    {
        $data = array('test' => 'foo');
        $model = new TestModel($data);

        $this->assertEquals($data, $model->toArray());
    }

    /**
     * Test that a property not known in the properties
     *     isn't set when loaded
     */
    public function testLoadUnknownProperty(): void
    {
        $this->model->load(array('foo' => 'bar'));
        $this->assertEmpty($this->model->toArray());
    }

    /**
     * Test the getter/setter for properties
     */
    public function testGetSetProperties(): void
    {
        $property = array(
            'description' => 'This is a test'
        );
        $this->model->addProperty('testing', $property);

        $this->assertTrue(
            array_key_exists('testing', $this->model->getProperties())
        );
        $this->assertEquals(
            $this->model->getProperty('testing'),
            $property
        );
    }

    /**
     * Test that an exception is thrown when you try to add
     *     a property that already exists
     *
     * @expectedException \InvalidArgumentException
     */
    public function testSetExistingProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->addProperty('test', array(
            'description' => 'This is a duplicate'
        ));
    }

    /**
     * Test that a property can be overrideen with the
     *     extra property (replacing one already there)
     */
    public function testSetExistingPropertyOverride(): void
    {
        $this->model->addProperty('test', array(
            'description' => 'This is a duplicate'
        ), true);

        $properties = $this->model->getProperties();
        $this->assertEquals(
            $properties['test']['description'],
            'This is a duplicate'
        );
    }

    /**
     * Test the getter/setter for model values
     */
    public function testGetSetValues(): void
    {
        $value = 'test';
        $this->model->setValue('foo', $value);

        $this->assertEquals($this->model->getValue('foo'), $value);
    }

    /**
     * Test that the magic get/set methods are doing their job
     */
    public function testMagicGetSetProperty(): void
    {
        $value = 'testing123';
        $this->model->test = $value;

        $this->assertEquals($this->model->test, $value);
    }

    /**
     * Test that an exception is thrown when you try to __set
     *     a property that doesn't exist
     *
     * @expectedException \InvalidArgumentException
     */
    public function testMagicSetInvalidProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->foo = 'test';
    }

    /**
     * Test that an exception is thrown when an invalid property is requested
     *
     * @expectedException \InvalidArgumentException
     */
    public function testMagicGetInvalidProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        echo $this->model->foobar;
    }

    /**
     * Test that the get* handling is working for property values
     */
    public function testMagicGetFunction(): void
    {
        $this->model->test = 'foo';
        $this->assertEquals('foo', $this->model->getTest());
    }

    /**
     * Test that the get* call on an invalid property returns null
     */
    public function testMagicGetFunctionInvalid(): void
    {
        $this->assertNull($this->model->getFoo());
    }

    /**
     * Test that the relationship from TestModel and OtherModel
     *     is correctly linked. If the link is correct, "callMeMaybe"
     *     is executed and the "test" value is set
     */
    public function testGetRelationValid(): void
    {
        $this->model->test = 'woo';
        $result = $this->model->relateToMe;

        $this->assertEquals(get_class($result), 'Modler\\Tests\\OtherModel');
        $this->assertEquals($result->test, 'foobarbaz - woo');
    }

    /**
     * Test that the "return value" works correctly
     */
    public function testGetRelationReturnValue(): void
    {
        $this->model->test = 'woo';
        $result = $this->model->relateToMeValue;

        $this->assertEquals($result, 'this is a value: woo');
    }

    /**
     * Test that an exception is thrown when a bad model is named in
     *     the relationship
     *
     * @expectedException \InvalidArgumentException
     */
    public function testGetRelationInvalidModel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->badModel;
    }

    /**
     * Test that an exception is thrown when a bad method is named in
     *     the relationship
     *
     * @expectedException \InvalidArgumentException
     */
    public function testGetRelationInvalidMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->badMethod;
    }

    /**
     * Test that, when the required field is set, verification passes
     */
    public function testVerifyPass(): void
    {
        $this->model->imRequired = 'test';
        $this->assertTrue($this->model->verify());
    }

    /**
     * Test that when the required field is missing, an exception is thrown
     *
     * @expectedException \InvalidArgumentException
     */
    public function testVerifyFail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->verify();
    }

    /**
     * Test the "ignore" property handling
     */
    public function testVerifyIgnorePass(): void
    {
        $ignore = array('imRequired');
        $this->assertTrue($this->model->verify($ignore));
    }

    /**
     * Test that the validation passes with the correct (matching) value
     */
    public function testValidateMethodPass(): void
    {
        $this->model->imRequired = 'test';
        $this->model->testValidate = 'test1234';

        $this->assertTrue($this->model->verify());
    }

    /**
     * Test the exception thrown when the property validation fails
     *
     * @expectedException \InvalidArgumentException
     */
    public function testValidateMethodFail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->model->imRequired = 'test';
        $this->model->testValidate = '1234test';

        $this->assertTrue($this->model->verify());
    }

    /**
     * Try to set a guarded value on load
     */
    public function testSetGuardedOnLoad(): void
    {
        $data = array('guarded' => 'this will not work');
        $this->model->load($data);

        $this->assertNull($this->model->guarded);
    }

    /**
     * Try to set a guarded value as a property
     */
    public function testSetGuardedProperty(): void
    {
        $this->model->guarded = 'this will not work either';
        $this->assertNull($this->model->guarded);
    }

    /**
     * Test the "enforce guard" parameter on the load
     *     This allows us to override the check (useful for database loads)
     */
    public function testSetGuardedOnLoadNotEnforced(): void
    {
        $data = array('guarded' => 'this will work this time');
        $this->model->load($data, false);

        $this->assertEquals(
            $this->model->guarded,
            $data['guarded']
        );
    }

    /**
     * Test the removal of values through a "filter" in the toArray method
     */
    public function testFilterRemoveValues(): void
    {
        $this->model->test = 'foobar';
        $filter = array('test');
        $this->assertEmpty($this->model->toArray($filter));
    }
}
