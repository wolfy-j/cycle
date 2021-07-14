<?php

declare(strict_types=1);

namespace Cycle\ORM\Tests\Mapper\ProxyEntityMapper;

use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Schema;
use Cycle\ORM\Tests\Mapper\BaseMapperTest;

class EntityHydrationTest extends BaseMapperTest
{
    public const DRIVER = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        $this->orm = $this->withSchema(
            new Schema(
                [
                    EntityHydrationUser::class => [
                        Schema::MAPPER => Mapper::class,
                        Schema::DATABASE => 'default',
                        Schema::TABLE => 'user',
                        Schema::PRIMARY_KEY => 'uuid',
                        Schema::COLUMNS => ['id', 'username', 'email'],
                        Schema::TYPECAST => [],
                        Schema::SCHEMA => [],
                        Schema::RELATIONS => []
                    ],
                    ExtendedEntityHydrationUser::class => [
                        Schema::MAPPER => Mapper::class,
                        Schema::DATABASE => 'default',
                        Schema::TABLE => 'user',
                        Schema::PRIMARY_KEY => 'uuid',
                        Schema::COLUMNS => ['id', 'username', 'email', 'isVerified', 'profileId'],
                        Schema::TYPECAST => [],
                        Schema::SCHEMA => [],
                        Schema::RELATIONS => []
                    ]
                ]
            )
        );
    }

    function testDataForBaseClassShouldBeExtracted()
    {
        $user = new EntityHydrationUser(123, 'guest', 'guest@site.com');

        $mapper = $this->orm->getMapper($user);

        $this->assertEquals([
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'attributes' => []
        ], $mapper->extract($user));
    }

    function testDataForExtendedClassShouldBeExtracted()
    {
        $user = new ExtendedEntityHydrationUser(123, 'guest', 'guest@site.com', true, 234);

        $mapper = $this->orm->getMapper($user);

        $this->assertEquals([
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'isVerified' => true,
            'profileId' => 234,
            'attributes' => []
        ], $mapper->extract($user));
    }

    function testDataShouldBeHydratedToBaseClass()
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);
        $this->assertInstanceOf(EntityHydrationUser::class, $emptyObject);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com'
        ]);

        $this->assertSame(123, $user->getId());
        $this->assertSame('guest', $user->getUsername());
        $this->assertSame('guest@site.com', $user->getEmail());
    }

    function testDataShouldBeHydratedToExtendedClass()
    {
        $mapper = $this->orm->getMapper(ExtendedEntityHydrationUser::class);

        $emptyObject = $mapper->init([]);
        $this->assertInstanceOf(ExtendedEntityHydrationUser::class, $emptyObject);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'username' => 'guest',
            'email' => 'guest@site.com',
            'isVerified' => true,
            'profileId' => 234,
        ]);

        $this->assertSame(123, $user->getId());
        $this->assertSame('guest', $user->getUsername());
        $this->assertSame('guest@site.com', $user->getEmail());
        $this->assertSame(true, $user->isVerified());
        $this->assertSame(234, $user->getProfileId());
    }

    function testUndefinedPropertiesShouldBePassedThroughSetter()
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'tag' => 'test',
            'username' => 'guest',
            'email' => 'guest@site.com'
        ]);

        $this->assertEquals(['tag' => 'test'], $user->getAttributes());
    }

    function testRequestedUndefinedPropertiesShouldBePassedThroughGetter()
    {
        $mapper = $this->orm->getMapper(EntityHydrationUser::class);

        $emptyObject = $mapper->init([]);

        $user = $mapper->hydrate($emptyObject, [
            'id' => 123,
            'tag' => 'test',
            'username' => 'guest',
            'email' => 'guest@site.com'
        ]);

        $this->assertEquals('test', $user->tag);
    }
}

class EntityHydrationUser
{
    public int $id;
    protected string $username;
    private string $email;
    private array $attributes = [];

    public function __construct(int $id, string $username, string $email)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function __set(string $name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function __get(string $name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}


class ExtendedEntityHydrationUser extends EntityHydrationUser
{
    protected bool $isVerified;
    private int $profileId;

    public function __construct(int $id, string $username, string $email, bool $isVerified, int $profileId)
    {
        parent::__construct($id, $username, $email);

        $this->isVerified = $isVerified;
        $this->profileId = $profileId;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getProfileId(): int
    {
        return $this->profileId;
    }
}