<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Mapper\Hydrator;

use Cycle\ORM\Mapper\Hydrator\ClassPropertiesExtractor;
use PHPUnit\Framework\TestCase;

class ClassPropertiesExtractorTest extends TestCase
{
    private ClassPropertiesExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new ClassPropertiesExtractor();
    }

    function testPropertyFromBaseClassShouldBeExtracted()
    {
        $class = User::class;

        $map = $this->extractor->extract($class, []);

        $this->assertEquals([
            '' => [
                'id' => 'id',
                'comments' => 'comments'
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\User' => [
                'username' => 'username',
                'email' => 'email',
            ]
        ], $map['class']->getProperties());

        $this->assertEquals([
        ], $map['relations']->getProperties());
    }

    function testPropertyFromBaseClassWithRelationsShouldBeExtracted()
    {
        $class = User::class;

        $map = $this->extractor->extract($class, ['comments']);

        $this->assertEquals([
            '' => [
                'id' => 'id'
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\User' => [
                'username' => 'username',
                'email' => 'email',
            ]
        ], $map['class']->getProperties());

        $this->assertEquals([
            '' => [
                'comments' => 'comments'
            ]
        ], $map['relations']->getProperties());
    }

    function testPropertyFromExtendedClassShouldBeExtracted()
    {
        $class = SuperUser::class;

        $map = $this->extractor->extract($class, []);

        $this->assertEquals([
            '' => [
                'id' => 'id',
                'age' => 'age',
                'totalLogin' => 'totalLogin',
                'comments' => 'comments'
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\User' => [
                'username' => 'username',
                'email' => 'email',
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\ExtendedUser' => [
                'isVerified' => 'isVerified',
                'profileId' => 'profileId',
                'tags' => 'tags',
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\SuperUser' => [
                'isAdmin' => 'isAdmin',
            ]
        ], $map['class']->getProperties());

        $this->assertEquals([

        ], $map['relations']->getProperties());
    }

    function testPropertyFromExtendedClassWithRelationsShouldBeExtracted()
    {
        $class = SuperUser::class;

        $map = $this->extractor->extract($class, ['comments', 'tags']);

        $this->assertEquals([
            '' => [
                'id' => 'id',
                'age' => 'age',
                'totalLogin' => 'totalLogin'
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\User' => [
                'username' => 'username',
                'email' => 'email',
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\ExtendedUser' => [
                'isVerified' => 'isVerified',
                'profileId' => 'profileId',
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\SuperUser' => [
                'isAdmin' => 'isAdmin',
            ]
        ], $map['class']->getProperties());

        $this->assertEquals([
            '' => [
                'comments' => 'comments'
            ],
            'Cycle\ORM\Tests\Mapper\Hydrator\ExtendedUser' => [
                'tags' => 'tags',
            ],
        ], $map['relations']->getProperties());
    }
}


class User
{
    public int $id;
    protected string $username;
    private string $email;
    public array $comments;
}

class ExtendedUser extends User
{
    protected bool $isVerified;
    private int $profileId;
    public int $age;
    private array $tags;
}

class SuperUser extends ExtendedUser
{
    private int $isAdmin;
    public int $totalLogin;
}