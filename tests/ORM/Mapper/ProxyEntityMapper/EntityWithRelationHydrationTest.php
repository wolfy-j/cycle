<?php
declare(strict_types=1);

namespace Cycle\ORM\Tests\Mapper\ProxyEntityMapper;

use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\Select;
use Cycle\ORM\Tests\Mapper\BaseMapperTest;
use Cycle\ORM\Tests\Traits\TableTrait;

class EntityWithRelationHydrationTest extends BaseMapperTest
{
    use TableTrait;

    public const DRIVER = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTable('user', ['id' => 'primary', 'email' => 'string']);
        $this->makeTable('profile', ['id' => 'primary', 'name' => 'string', 'user_id' => 'integer']);

        $this->getDatabase()->table('user')->insertMultiple(
            ['email'],
            [['hello@world.com'],]
        );

        $this->getDatabase()->table('profile')->insertMultiple(
            ['user_id', 'name'],
            [[1, 'John Smith'],]
        );

        $this->orm = $this->withSchema(new Schema([
            EntityWithRelationHydrationUser::class => [
                Schema::ROLE => 'user',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'user',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'email'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [
                    'profile' => [
                        Relation::TYPE => Relation::HAS_ONE,
                        Relation::TARGET => EntityWithRelationHydrationProfile::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'user_id',
                        ],
                    ]
                ]
            ],
            EntityWithRelationHydrationProfile::class => [
                Schema::ROLE => 'profile',
                Schema::MAPPER => Mapper::class,
                Schema::DATABASE => 'default',
                Schema::TABLE => 'profile',
                Schema::PRIMARY_KEY => 'id',
                Schema::COLUMNS => ['id', 'user_id', 'name'],
                Schema::SCHEMA => [],
                Schema::RELATIONS => [
                    'user' => [
                        Relation::TYPE => Relation::BELONGS_TO,
                        Relation::TARGET => EntityWithRelationHydrationUser::class,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'user_id',
                            Relation::OUTER_KEY => 'id',
                        ],
                    ]
                ]
            ]
        ]));
    }

    function testPrivateRelationPropertyShouldBeFilled()
    {
        $selector = new Select($this->orm, EntityWithRelationHydrationProfile::class);

        $profile = $selector
            ->load('user.profile')
            ->fetchOne();

        $this->assertEquals('1', $profile->getUser()->id);
        $this->assertEquals('hello@world.com', $profile->getUser()->getEmail());
        $this->assertSame($profile, $profile->getUser()->getProfile());
    }
}

class EntityWithRelationHydrationUser
{
    public $id;
    private $email;
    protected EntityWithRelationHydrationProfile $profile;

    public function getEmail()
    {
        return $this->email;
    }

    public function getProfile(): EntityWithRelationHydrationProfile
    {
        return $this->profile;
    }
}

class EntityWithRelationHydrationProfile
{
    public $id;
    private $name;
    private EntityWithRelationHydrationUser $user;

    public function getUser()
    {
        return $this->user;
    }
}