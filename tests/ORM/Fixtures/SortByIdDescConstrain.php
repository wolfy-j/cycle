<?php

/**
 * Cycle DataMapper ORM
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Cycle\ORM\Tests\Fixtures;

use Cycle\ORM\Select\ConstrainInterface;
use Cycle\ORM\Select\QueryBuilder;

class SortByIdDescConstrain implements ConstrainInterface
{
    public function apply(QueryBuilder $query): void
    {
        $query->orderBy('id', 'DESC');
    }
}
