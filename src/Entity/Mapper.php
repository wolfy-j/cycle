<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Entity;

use Spiral\ORM\Command\ContextCarrierInterface;
use Spiral\ORM\Command\CommandInterface;
use Spiral\ORM\Command\Branch\Nil;
use Spiral\ORM\Command\Branch\Split;
use Spiral\ORM\Command\Database\Delete;
use Spiral\ORM\Command\Database\Insert;
use Spiral\ORM\Command\Database\Update;
use Spiral\ORM\Context\ConsumerInterface;
use Spiral\ORM\MapperInterface;
use Spiral\ORM\ORMInterface;
use Spiral\ORM\Node;
use Spiral\ORM\RepositoryInterface;
use Spiral\ORM\Schema;
use Spiral\ORM\Selector;
use Zend\Hydrator\HydratorInterface;
use Zend\Hydrator\Reflection;

class Mapper implements MapperInterface
{
    // system column to store entity type
    public const ENTITY_TYPE = '_type';

    protected $orm;

    protected $class;

    protected $table;

    protected $primaryKey;

    protected $children;

    protected $columns;

    /**
     * @var HydratorInterface
     */
    private $hydrator;

    public function __construct(ORMInterface $orm, $class)
    {
        $this->orm = $orm;
        $this->class = $class;

        // todo: mass export
        $this->columns = $this->orm->getSchema()->define($class, Schema::COLUMNS);
        $this->table = $this->orm->getSchema()->define($class, Schema::TABLE);
        $this->primaryKey = $this->orm->getSchema()->define($class, Schema::PRIMARY_KEY);
        $this->children = $this->orm->getSchema()->define($class, Schema::CHILDREN) ?? [];
        $this->hydrator = new Reflection();
    }

    public function hydrate($entity, array $data)
    {
        return $this->hydrator->hydrate($data, $entity);
    }

    public function extract($entity): array
    {
        return $this->hydrator->extract($entity);
    }



    public function entityClass(array $data): string
    {
        $class = $this->class;
        if (!empty($this->children) && !empty($data[self::ENTITY_TYPE])) {
            $class = $this->children[$data[self::ENTITY_TYPE]] ?? $class;
        }

        return $class;
    }

    public function prepare(array $data): array
    {
        $class = $this->entityClass($data);

        return [new $class, $data];
    }

    public function getRepository(string $class = null): RepositoryInterface
    {
        // todo: child class select
        return new Source(new Selector($this->orm, $class ?? $this->class));
    }

    // todo: need state as INPUT!!!!
    public function queueStore($entity): ContextCarrierInterface
    {
        /** @var Node $point */
        $point = $this->orm->getHeap()->get($entity);
        if (is_null($point)) {
            // todo: do we need to track PK?
            $point = new Node(
                Node::NEW,
                [],
                $this->orm->getSchema()->define(get_class($entity), Schema::ALIAS)
            );
            $this->orm->getHeap()->attach($entity, $point);
        }

        if ($point == null || $point->getStatus() == Node::NEW) {
            $cmd = $this->queueCreate($entity, $point);
            $point->getState()->setCommand($cmd);

            return $cmd;
        }

        $lastCommand = $point->getState()->getCommand();

        if (empty($lastCommand)) {
            // todo: check multiple update commands working within the split (!)
            return $this->queueUpdate($entity, $point);
        }

        if ($lastCommand instanceof Split) {
            return $lastCommand;
        }

        // todo: do i like it?
        $split = new Split($lastCommand, $this->queueUpdate($entity, $point));
        $point->getState()->setCommand($split);

        return $split;
    }

    public function queueDelete($entity): CommandInterface
    {
        $state = $this->orm->getHeap()->get($entity);
        if ($state == null) {
            // todo: this should not happen, todo: need nullable delete
            return new Nil();
        }

        // todo: delete relations as well

        return $this->buildDelete($entity, $state);
    }

    protected function getColumns($entity): array
    {
        return array_intersect_key($this->extract($entity), array_flip($this->columns));
    }

    // todo: state must not be null
    protected function queueCreate($entity, Node &$state = null): ContextCarrierInterface
    {
        $columns = $this->getColumns($entity);

        $class = get_class($entity);
        if ($class != $this->class) {
            // possibly children
            foreach ($this->children as $alias => $childClass) {
                if ($childClass == $class) {
                    $columns[self::ENTITY_TYPE] = $alias;
                }
            }

            // todo: what is that?
            // todo: exception
        }

        // to the point
        $state->setData($columns);

        $state->setStatus(Node::SCHEDULED_INSERT);

        // todo: this is questionable (what if ID not autogenerated)
        unset($columns[$this->primaryKey]);

        $insert = new Insert($this->orm->getDatabase($entity), $this->table, $columns);
        $insert->onInsert($state, $this->primaryKey);

        return $insert;
    }

    protected function queueUpdate($entity, Node $state): ContextCarrierInterface
    {
        $eData = $this->getColumns($entity);
        $oData = $state->getData();
        $cData = array_diff($eData, $oData);

        // todo: pack changes (???) depends on mode (USE ALL FOR NOW)

        // todo: this part is weird
        unset($cData[$this->primaryKey]);

        $update = new Update($this->orm->getDatabase($entity), $this->table, $cData);
        $state->setStatus(Node::SCHEDULED_UPDATE);
        $state->setData($cData);

        // todo: scope prefix (call immediatelly?)
        $state->listen($this->primaryKey, $update, $this->primaryKey, true, ConsumerInterface::SCOPE);

        return $update;
    }

    protected function buildDelete($entity, Node $state): CommandInterface
    {
        $delete = new Delete($this->orm->getDatabase($entity), $this->table);

        $state->setStatus(Node::SCHEDULED_DELETE);
        $state->getState()->decClaim();

        $delete->waitScope($this->primaryKey);
        $state->listen($this->primaryKey, $delete, $this->primaryKey, true, ConsumerInterface::SCOPE);

        // todo: this must be changed (CORRECT?) BUT HOW?
        //  $delete->onComplete(function () use ($entity) {
        //      $this->orm->getHeap()->detach($entity);
        //  });

        return $delete;
    }
}