<?php

declare(strict_types=1);

namespace Cycle\ORM;

use Cycle\ORM\Heap\Node;

/**
 * Iterates over given data-set and instantiates objects.
 */
final class Iterator implements \IteratorAggregate
{
    private \Cycle\ORM\ORMInterface $orm;

    private string $class;

    private iterable $source;

    public function __construct(ORMInterface $orm, string $class, iterable $source)
    {
        $this->orm = $orm;
        $this->class = $class;
        $this->source = $source;
    }

    /**
     * Generate entities using incoming data stream. Pivoted data would be
     * returned as key value if set.
     */
    public function getIterator(): \Generator
    {
        foreach ($this->source as $index => $data) {
            // through-like relations
            if (isset($data['@'])) {
                $index = $data;
                unset($index['@']);
                $data = $data['@'];
            }

            // add pipeline filter support?

            yield $index => $this->orm->make($this->class, $data, Node::MANAGED);
        }
    }
}
