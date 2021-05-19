<?php

declare(strict_types=1);

namespace Cycle\ORM;

use Cycle\ORM\Exception\SchemaException;

/**
 * Static schema with automatic class name => role aliasing.
 */
final class Schema implements SchemaInterface
{
    private array $aliases;

    private array $schema = [];

    public function __construct(array $schema)
    {
        // split into two?
        [$this->schema, $this->aliases] = $this->normalize($schema);
    }

    public static function __set_state(array $an_array): Schema
    {
        $schema = new self([]);
        $schema->schema = $an_array['schema'];
        $schema->aliases = $an_array['aliases'];

        return $schema;
    }

    public function getRoles(): array
    {
        return array_keys($this->schema);
    }

    public function getRelations(string $role): array
    {
        return array_keys($this->define($role, self::RELATIONS));
    }

    /**
     * @return array [role => [relation name => relation schema]]
     */
    public function getOuterRelations(string $role): array
    {
        // return null;
        $result = [];
        foreach ($this->schema as $roleName => $entitySchema) {
            if ($roleName === $role) {
                continue;
            }
            foreach ($entitySchema[SchemaInterface::RELATIONS] ?? [] as $relName => $item) {
                if ($item[Relation::TARGET] === $role) {
                    $result[$roleName][$relName] = $item;
                }
            }
        }
        return $result;
    }
    /**
     * @return array [relation name => relation schema]
     */
    public function getInnerRelations(string $role): array
    {
        return $this->schema[$role][SchemaInterface::RELATIONS] ?? [];
    }

    public function defines(string $role): bool
    {
        return isset($this->schema[$role]) || isset($this->aliases[$role]);
    }

    public function define(string $role, int $property)
    {
        $role = $this->resolveAlias($role) ?? $role;

        if (!isset($this->schema[$role])) {
            throw new SchemaException("Undefined schema `{$role}`, not found");
        }

        return $this->schema[$role][$property] ?? null;
    }

    public function defineRelation(string $role, string $relation): array
    {
        $relations = $this->define($role, self::RELATIONS);

        if (!isset($relations[$relation])) {
            throw new SchemaException("Undefined relation `{$role}`.`{$relation}`");
        }

        return $relations[$relation];
    }

    public function resolveAlias(string $role): ?string
    {
        // walk throught all children until parent entity found
        while (isset($this->aliases[$role])) {
            $role = $this->aliases[$role];
        }

        return $role;
    }

    /**
     * Automatically replace class names with their aliases.
     *
     * @return array Pair of [schema, aliases]
     */
    protected function normalize(array $schema): array
    {
        $result = $aliases = [];

        foreach ($schema as $key => $item) {
            $role = $key;
            if (!isset($item[self::ENTITY])) {
                // legacy type of declaration (class => schema)
                $item[self::ENTITY] = $key;
            }

            if (class_exists($key)) {
                $role = $item[self::ROLE] ?? $key;
                if ($role !== $key) {
                    $aliases[$key] = $role;
                }
            }

            if ($item[self::ENTITY] !== $role && class_exists($item[self::ENTITY])) {
                $aliases[$item[self::ENTITY]] = $role;
            }

            unset($item[self::ROLE]);
            $result[$role] = $item;
        }

        // normalizing relation associations
        foreach ($result as &$item) {
            if (isset($item[self::RELATIONS])) {
                $item[self::RELATIONS] = iterator_to_array($this->normalizeRelations(
                    $item[self::RELATIONS],
                    $aliases
                ));
            }

            unset($item);
        }

        return [$result, $aliases];
    }

    private function normalizeRelations(array $relations, array $aliases): \Generator
    {
        foreach ($relations as $name => &$rel) {
            $target = $rel[Relation::TARGET];
            while (isset($aliases[$target])) {
                $target = $aliases[$target];
            }

            $rel[Relation::TARGET] = $target;

            yield $name => $rel;
        }
    }
}
