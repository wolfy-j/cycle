<?php

/**
 * Cycle DataMapper ORM
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Cycle\ORM\Relation\Traits;

use Cycle\ORM\Command\ContextCarrierInterface as CC;
use Cycle\ORM\Command\ScopeCarrierInterface as CS;
use Cycle\ORM\Context\ConsumerInterface;
use Cycle\ORM\Heap\Node;

/**
 * Provides the ability to set the promises for command context and scopes linked
 * to related entity state change.
 */
trait ContextTrait
{

    /**
     * True if given relation is not required for the object to be saved (i.e. NULL).
     *
     * @return bool
     */
    abstract public function isNullable(): bool;

    /**
     * Configure context parameter using value from parent entity. Created promise.
     *
     * @param Node         $from
     * @param array $fromKey
     * @param CC           $carrier
     * @param null|Node    $to
     * @param array $toKey
     * @return CC
     */
    protected function forwardContext(Node $from, array $fromKeys, CC $carrier, Node $to, array $toKeys): CC
    {
        foreach ($fromKeys as $i => $fromKey) {
            $toKey = $toKeys[$i];

            $toColumn = $this->columnName($to, $toKey);

            // do not execute until the key is given
            $carrier->waitContext($toColumn, !$this->isNullable());

            // forward key from state to the command (on change)
            $to->forward($toKey, $carrier, $toColumn);

            // link 2 keys and trigger cascade falling right now (if exists)
            $from->forward($fromKey, $to, $toKey, true);

            // edge case while updating transitive key (exists in acceptor but does not exists in provider)
            if (!array_key_exists($fromKey, $from->getInitialData())) {
                $carrier->waitContext($toColumn, !$this->isNullable());
            }
        }

        return $carrier;
    }

    /**
     * Configure where parameter in scoped command based on key provided by the
     * parent entity. Creates promise.
     *
     * @param Node   $from
     * @param string[] $fromKeys
     * @param CS     $carrier
     * @param string[] $toKeys
     * @return CS
     */
    protected function forwardScope(Node $from, array $fromKeys, CS $carrier, array $toKeys): CS
    {
        foreach ($fromKeys as $i => $fromKey) {
            $column = $this->columnName($from, $toKeys[$i]);

            $carrier->waitScope($column);
            $from->forward($fromKey, $carrier, $column, true, ConsumerInterface::SCOPE);
        }

        return $carrier;
    }

    /**
     * Fetch key from the state.
     *
     * @param Node   $node
     * @param string $key
     * @return mixed|null
     */
    protected function fetchKey(?Node $node, string $key)
    {
        if ($node === null) {
            return null;
        }

        return $node->getData()[$key] ?? null;
    }

    /**
     * Return column name in database.
     *
     * @param Node   $node
     * @param string $field
     * @return string
     */
    abstract protected function columnName(Node $node, string $field): string;
}
