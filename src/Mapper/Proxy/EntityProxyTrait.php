<?php

declare(strict_types=1);

namespace Cycle\ORM\Mapper\Proxy;

use Closure;
use Cycle\ORM\Mapper\Hydrator\PropertyMap;
use Cycle\ORM\Reference\ReferenceInterface;
use Cycle\ORM\RelationMap;
use RuntimeException;

trait EntityProxyTrait
{
    public RelationMap $__cycle_orm_rel_map;
    public PropertyMap $__cycle_orm_relation_props;
    public array $__cycle_orm_rel_data = [];

    public function __get(string $name)
    {
        $relation = $this->__cycle_orm_rel_map->getRelations()[$name] ?? null;
        if ($relation === null) {
            if (method_exists(get_parent_class(static::class), '__get')) {
                return parent::__get($name);
            }

            return $this->$name;
        }

        $value = $this->__cycle_orm_rel_data[$name] ?? null;
        if ($value instanceof ReferenceInterface) {
            $this->$name = $relation->collect($relation->resolve($value, true));
            unset($this->__cycle_orm_rel_data[$name]);
            return $this->$name;
        }

        throw new RuntimeException(sprintf('Property %s.%s is not initialized.', get_parent_class(static::class), $name));
    }

    public function __set(string $name, $value): void
    {
        if (!array_key_exists($name, $this->__cycle_orm_rel_map->getRelations())) {
            if (method_exists(get_parent_class(static::class), '__set')) {
                parent::__set($name, $value);
                return;
            }

            // throw new \RuntimeException("Property {$name} is protected.");
            return;
        }

        if ($value instanceof ReferenceInterface) {
            $this->__cycle_orm_rel_data[$name] = $value;
            return;
        }
        unset($this->__cycle_orm_rel_data[$name]);

        $propertyClass = $this->__cycle_orm_relation_props->getPropertyClass($name);
        if ($propertyClass === PropertyMap::PUBLIC_CLASS) {
            $this->$name = $value;
        } else {
            Closure::bind(static function (object $object, string $property, $value): void {
                $object->{$property} = $value;
            }, null, $propertyClass)($this, $name, $value);
        }
    }

    public function __debugInfo(): array
    {
        $result = (array)$this;
        unset($result['__cycle_orm_rel_map'], $result['__cycle_orm_rel_data'], $result['__cycle_orm_relation_props']);
        return $result;
    }
}
