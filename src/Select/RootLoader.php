<?php

/**
 * Cycle DataMapper ORM
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Cycle\ORM\Select;

use Cycle\ORM\ORMInterface;
use Cycle\ORM\Parser\AbstractNode;
use Cycle\ORM\Parser\RootNode;
use Cycle\ORM\Parser\Typecast;
use Cycle\ORM\Schema;
use Cycle\ORM\Select\Traits\ColumnsTrait;
use Spiral\Database\Query\SelectQuery;
use Spiral\Database\StatementInterface;

/**
 * Primary ORM loader. Loader wraps at top of select query in order to modify it's conditions, joins
 * and etc based on nested loaders.
 *
 * Root load does not load constrain from ORM by default.
 */
final class RootLoader extends AbstractLoader
{
    use ColumnsTrait;

    /** @var array */
    protected $options = [
        'load'      => true,
        'constrain' => true,
    ];

    /** @var SelectQuery */
    private $query;

    /**
     * @param ORMInterface $orm
     * @param string       $target
     */
    public function __construct(ORMInterface $orm, string $target)
    {
        parent::__construct($orm, $target);
        $this->query = $this->getSource()->getDatabase()->select()->from(
            sprintf('%s AS %s', $this->getSource()->getTable(), $this->getAlias())
        );

        foreach ($this->getEagerRelations() as $relation) {
            $this->loadRelation($relation, [], false, true);
        }
    }

    /**
     * Clone the underlying query.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        parent::__clone();
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->target;
    }

    /**
     * Get primary key column identifier (aliased).
     *
     * @return string
     */
    public function getPK(): string
    {
        return $this->getAlias() . '.' . $this->fieldAlias($this->define(Schema::PRIMARY_KEY));
    }

    /**
     * Return base query associated with the loader.
     *
     * @return SelectQuery
     */
    public function getQuery(): SelectQuery
    {
        return $this->query;
    }

    /**
     * Compile query with all needed conditions, columns and etc.
     *
     * @return SelectQuery
     */
    public function buildQuery(): SelectQuery
    {
        return $this->configureQuery(clone $this->query);
    }

    /**
     * {@inheritdoc}
     */
    public function loadData(AbstractNode $node): void
    {
        $statement = $this->buildQuery()->run();

        foreach ($statement->fetchAll(StatementInterface::FETCH_NUM) as $row) {
            $node->parseRow(0, $row);
        }

        $statement->close();

        // loading child datasets
        foreach ($this->load as $relation => $loader) {
            $loader->loadData($node->getNode($relation));
        }
    }

    /**
     * @inheritdoc
     */
    public function isLoaded(): bool
    {
        // root loader is always loaded
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureQuery(SelectQuery $query): SelectQuery
    {
        return parent::configureQuery(
            $this->mountColumns($query, true, '', true)
        );
    }

    /**
     * @inheritdoc
     */
    protected function initNode(): AbstractNode
    {
        $node = new RootNode($this->columnNames(), $this->define(Schema::PRIMARY_KEY));

        $typecast = $this->define(Schema::TYPECAST);
        if ($typecast !== null) {
            $node->setTypecast(new Typecast($typecast, $this->getSource()->getDatabase()));
        }

        return $node;
    }

    /**
     * Relation columns.
     *
     * @return array
     */
    protected function getColumns(): array
    {
        return $this->define(Schema::COLUMNS);
    }
}
