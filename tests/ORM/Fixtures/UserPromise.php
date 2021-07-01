<?php

/**
 * Cycle DataMapper ORM
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Cycle\ORM\Tests\Fixtures;

use Cycle\ORM\ORMInterface;
use Cycle\ORM\Promise\PromiseInterface;
use Cycle\ORM\Select;

class UserPromise extends User implements PromiseInterface
{
    /** @var ORMInterface|null @internal */
    private $orm;

    /** @var string|null */
    private $target;

    /** @var array */
    private $scope;

    /** @var User */
    private $resolved;

    /**
     * @param ORMInterface $orm
     * @param string       $target
     * @param array        $scope
     */
    public function __construct(ORMInterface $orm, string $target, array $scope)
    {
        $this->orm = $orm;
        $this->target = $target;
        $this->scope = $scope;
    }

    /**
     * @inheritdoc
     */
    public function __loaded(): bool
    {
        return $this->orm === null;
    }

    /**
     * @inheritdoc
     */
    public function __role(): string
    {
        return $this->target;
    }

    /**
     * @inheritdoc
     */
    public function __scope(): array
    {
        return $this->scope;
    }

    /**
     * @inheritdoc
     */
    public function __resolve()
    {
        if (!is_null($this->orm)) {
            // entity has already been loaded in memory
            if (!is_null($e = $this->orm->getHeap()->find($this->target, $this->scope))) {
                $this->orm = null;
                return $this->resolved = $e;
            }

            // Fetching from the database
            $select = new Select($this->orm, $this->target);
            $this->resolved = $select->fetchOne($this->scope);

            $this->orm = null;
        }

        return $this->resolved;
    }

    public function getID()
    {
        return $this->__resolve()->getID();
    }

    public function addComment(Comment $c): void
    {
        $this->__resolve()->addComment($c);
    }
}
