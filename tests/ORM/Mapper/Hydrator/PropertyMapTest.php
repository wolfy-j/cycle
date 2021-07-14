<?php
declare(strict_types=1);

namespace Cycle\ORM\Tests\Mapper\Hydrator;

use Cycle\ORM\Mapper\Hydrator\PropertyMap;
use PHPUnit\Framework\TestCase;

class PropertyMapTest extends TestCase
{
    private PropertyMap $properties;

    protected function setUp(): void
    {
        parent::setUp();

        $this->properties = new PropertyMap('User', [
            '' => [
                'id' => 'id',
                'username' => 'username'
            ],
            'Test\User\ExtendedUser' => [
                'is_verified' => 'is_verified',
                'has_avatar' => 'has_avatar',
            ],
            'Test\User\SuperUser' => [
                'is_admin' => 'is_admin',
                'is_blocked' => 'is_blocked',
            ]
        ]);
    }

    function testGetsPropertyClass()
    {
        $this->assertEquals('', $this->properties->getPropertyClass('id'));
        $this->assertEquals('', $this->properties->getPropertyClass('username'));

        $this->assertEquals('Test\User\ExtendedUser', $this->properties->getPropertyClass('is_verified'));
        $this->assertEquals('Test\User\ExtendedUser', $this->properties->getPropertyClass('has_avatar'));

        $this->assertEquals('Test\User\SuperUser', $this->properties->getPropertyClass('is_admin'));
        $this->assertEquals('Test\User\SuperUser', $this->properties->getPropertyClass('is_blocked'));
    }

    function testCheckPropertyPublicity()
    {
        $this->assertTrue($this->properties->isPublicProperty('id'));
        $this->assertTrue($this->properties->isPublicProperty('username'));

        $this->assertFalse($this->properties->isPublicProperty('is_verified'));
        $this->assertFalse($this->properties->isPublicProperty('has_avatar'));

        $this->assertFalse($this->properties->isPublicProperty('is_admin'));
        $this->assertFalse($this->properties->isPublicProperty('is_blocked'));
    }

    function testGetsProperties()
    {
        $this->assertIsArray($this->properties->getProperties());
    }

    function testGetsClass()
    {
        $this->assertEquals('User', $this->properties->getClass());
    }
}