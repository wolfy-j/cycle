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

class ProfilePromise extends Profile implements PromiseInterface
{
    /** @var ORMInterface|null @internal */
    private $orm;

    /** @var string|null */
    private $target;

    /** @var array */
    private $scope;

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
            $select = new Select($this->orm, $this->target);
            $data = $select->where($this->scope)->fetchData();

            $this->orm->getMapper($this->target)->hydrate($this, $data[0]);

            $this->orm = null;
        }

        return $this;
    }

    public function getID()
    {
        $this->__resolve();

        return parent::getID();
    }

    public function getImage()
    {
        $this->__resolve();

        return parent::getImage();
    }
}
