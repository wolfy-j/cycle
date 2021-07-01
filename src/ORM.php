<?php

/**
 * Cycle DataMapper ORM
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Cycle\ORM;

use Cycle\ORM\Command\Branch\Nil;
use Cycle\ORM\Command\CommandInterface;
use Cycle\ORM\Command\ContextCarrierInterface;
use Cycle\ORM\Exception\ORMException;
use Cycle\ORM\Heap\Heap;
use Cycle\ORM\Heap\HeapInterface;
use Cycle\ORM\Heap\Node;
use Cycle\ORM\Promise\PromiseInterface;
use Cycle\ORM\Promise\Reference;
use Cycle\ORM\Promise\ReferenceInterface;
use Cycle\ORM\Select\SourceInterface;

/**
 * Central class ORM, provides access to various pieces of the system and manages schema state.
 */
final class ORM implements ORMInterface
{
    /** @var CommandGenerator */
    private $generator;

    /** @var FactoryInterface */
    private $factory;

    /** @var PromiseFactoryInterface|null */
    private $promiseFactory;

    /** @var HeapInterface */
    private $heap;

    /** @var SchemaInterface|null */
    private $schema;

    /** @var MapperInterface[] */
    private $mappers = [];

    /** @var RepositoryInterface[] */
    private $repositories = [];

    /** @var RelationMap[] */
    private $relmaps = [];

    /** @var array */
    private $indexes = [];

    /** @var SourceInterface[] */
    private $sources = [];

    /**
     * @param FactoryInterface     $factory
     * @param SchemaInterface|null $schema
     */
    public function __construct(FactoryInterface $factory, SchemaInterface $schema = null)
    {
        $this->factory = $factory;
        $this->schema = $schema ?? new Schema([]);

        $this->heap = new Heap();
        $this->generator = new CommandGenerator();
    }

    /**
     * Reset related objects cache.
     */
    public function __clone()
    {
        $this->heap = new Heap();
        $this->mappers = [];
        $this->relmaps = [];
        $this->indexes = [];
        $this->sources = [];
        $this->repositories = [];
    }

    /**
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'schema' => $this->schema
        ];
    }

    /**
     * Automatically resolve role based on object name or instance.
     *
     * @param string|object $entity
     * @return string
     */
    public function resolveRole($entity): string
    {
        if (is_object($entity)) {
            $node = $this->getHeap()->get($entity);
            if ($node !== null) {
                return $node->getRole();
            }

            $class = get_class($entity);
            if (!$this->schema->defines($class)) {
                throw new ORMException("Unable to resolve role of `$class`");
            }

            $entity = $class;
        }

        return $this->schema->resolveAlias($entity);
    }

    /**
     * @inheritdoc
     */
    public function get(string $role, array $scope, bool $load = true)
    {
        $role = $this->resolveRole($role);
        $e = $this->heap->find($role, $scope);

        if ($e !== null) {
            return $e;
        }

        if (!$load) {
            return null;
        }

        return $this->getRepository($role)->findOne($scope);
    }

    /**
     * @inheritdoc
     */
    public function make(string $role, array $data = [], int $node = Node::NEW)
    {
        $m = $this->getMapper($role);

        // unique entity identifier
        $pk = $this->schema->define($role, Schema::PRIMARY_KEY);
        $id = $data[$pk] ?? null;

        if ($node !== Node::NEW && $id !== null) {
            $e = $this->heap->find($role, [$pk => $id]);

            if ($e !== null) {
                $node = $this->heap->get($e);

                // new set of data and relations always overwrite entity state
                return $m->hydrate(
                    $e,
                    $this->getRelationMap($role)->init($node, $data)
                );
            }
        }

        // init entity class and prepared (typecasted) data
        [$e, $prepared] = $m->init($data);

        $node = new Node($node, $prepared, $m->getRole());

        $this->heap->attach($e, $node, $this->getIndexes($m->getRole()));

        // hydrate entity with it's data, relations and proxies
        return $m->hydrate(
            $e,
            $this->getRelationMap($role)->init($node, $prepared)
        );
    }

    /**
     * @inheritdoc
     */
    public function withFactory(FactoryInterface $factory): ORMInterface
    {
        $orm = clone $this;
        $orm->factory = $factory;

        return $orm;
    }

    /**
     * @inheritdoc
     */
    public function getFactory(): FactoryInterface
    {
        return $this->factory;
    }

    /**
     * @inheritdoc
     */
    public function withSchema(SchemaInterface $schema): ORMInterface
    {
        $orm = clone $this;
        $orm->schema = $schema;

        return $orm;
    }

    /**
     * @inheritdoc
     */
    public function getSchema(): SchemaInterface
    {
        if ($this->schema === null) {
            throw new ORMException('ORM is not configured, schema is missing');
        }

        return $this->schema;
    }

    /**
     * @inheritdoc
     */
    public function withHeap(HeapInterface $heap): ORMInterface
    {
        $orm = clone $this;
        $orm->heap = $heap;

        return $orm;
    }

    /**
     * @inheritdoc
     */
    public function getHeap(): HeapInterface
    {
        return $this->heap;
    }

    /**
     * @inheritdoc
     */
    public function getMapper($entity): MapperInterface
    {
        $role = $this->resolveRole($entity);
        if (isset($this->mappers[$role])) {
            return $this->mappers[$role];
        }

        return $this->mappers[$role] = $this->factory->mapper($this, $this->schema, $role);
    }

    /**
     * @inheritdoc
     */
    public function getRepository($entity): RepositoryInterface
    {
        $role = $this->resolveRole($entity);
        if (isset($this->repositories[$role])) {
            return $this->repositories[$role];
        }

        $select = new Select($this, $role);

        return $this->repositories[$role] = $this->factory->repository($this, $this->schema, $role, $select);
    }

    /**
     * @inheritdoc
     */
    public function getSource(string $role): SourceInterface
    {
        if (isset($this->sources[$role])) {
            return $this->sources[$role];
        }

        return $this->sources[$role] = $this->factory->source($this, $this->schema, $role);
    }

    /**
     * Overlay existing promise factory.
     *
     * @param PromiseFactoryInterface $promiseFactory
     * @return ORM
     */
    public function withPromiseFactory(PromiseFactoryInterface $promiseFactory = null): self
    {
        $orm = clone $this;
        $orm->promiseFactory = $promiseFactory;

        return $orm;
    }

    /**
     * @inheritdoc
     *
     * Returns references by default.
     */
    public function promise(string $role, array $scope)
    {
        if (\count($scope) === 1) {
            $e = $this->heap->find($role, $scope);
            if ($e !== null) {
                return $e;
            }
        }

        if ($this->promiseFactory !== null) {
            return $this->promiseFactory->promise($this, $role, $scope);
        }

        return new Reference($role, $scope);
    }

    /**
     * @inheritdoc
     */
    public function queueStore($entity, int $mode = TransactionInterface::MODE_CASCADE): ContextCarrierInterface
    {
        if ($entity instanceof PromiseInterface && $entity->__loaded()) {
            $entity = $entity->__resolve();
        }

        if ($entity instanceof ReferenceInterface) {
            // we do not expect to store promises
            return new Nil();
        }

        $mapper = $this->getMapper($entity);

        $node = $this->heap->get($entity);
        if ($node === null) {
            // automatic entity registration
            $node = new Node(Node::NEW, [], $mapper->getRole());
            $this->heap->attach($entity, $node);
        }

        $cmd = $this->generator->generateStore($mapper, $entity, $node);
        if ($mode !== TransactionInterface::MODE_CASCADE) {
            return $cmd;
        }

        if ($this->schema->define($node->getRole(), Schema::RELATIONS) === []) {
            return $cmd;
        }

        // generate set of commands required to store entity relations
        return $this->getRelationMap($node->getRole())->queueRelations(
            $cmd,
            $entity,
            $node,
            $mapper->extract($entity)
        );
    }

    /**
     * @inheritdoc
     */
    public function queueDelete($entity, int $mode = TransactionInterface::MODE_CASCADE): CommandInterface
    {
        if ($entity instanceof PromiseInterface && $entity->__loaded()) {
            $entity = $entity->__resolve();
        }

        $node = $this->heap->get($entity);
        if ($entity instanceof ReferenceInterface || $node === null) {
            // nothing to do, what about promises?
            return new Nil();
        }

        // currently we rely on db to delete all nested records (or soft deletes)
        return $this->generator->generateDelete($this->getMapper($node->getRole()), $entity, $node);
    }

    /**
     * Get list of keys entity must be indexed in a Heap by.
     *
     * @param string $role
     * @return array
     */
    protected function getIndexes(string $role): array
    {
        if (isset($this->indexes[$role])) {
            return $this->indexes[$role];
        }

        $pk = $this->schema->define($role, Schema::PRIMARY_KEY);
        $keys = $this->schema->define($role, Schema::FIND_BY_KEYS) ?? [];

        return $this->indexes[$role] = array_unique(array_merge([$pk], $keys));
    }

    /**
     * Get relation map associated with the given class.
     *
     * @param string $entity
     * @return RelationMap
     */
    protected function getRelationMap($entity): RelationMap
    {
        $role = $this->resolveRole($entity);
        if (isset($this->relmaps[$role])) {
            return $this->relmaps[$role];
        }

        $relations = [];

        $names = array_keys($this->schema->define($role, Schema::RELATIONS));
        foreach ($names as $relation) {
            $relations[$relation] = $this->factory->relation($this, $this->schema, $role, $relation);
        }

        return $this->relmaps[$role] = new RelationMap($this, $relations);
    }
}
