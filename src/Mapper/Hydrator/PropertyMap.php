<?php

declare(strict_types=1);

namespace Cycle\ORM\Mapper\Hydrator;

class PropertyMap
{
    public const PUBLIC_CLASS = '';

    private array $properties;
    private string $class;

    public function __construct(string $class, array $properties)
    {
        $this->class = $class;
        $this->properties = $properties;
    }

    public function isPublicProperty(string $name): bool
    {
        return $this->getPropertyClass($name) === static::PUBLIC_CLASS;
    }

    public function getPropertyClass(string $name): ?string
    {
        foreach ($this->properties as $class => $properties) {
            if (in_array($name, $properties)) {
                return $class;
            }
        }

        return null;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getClass(): string
    {
        return $this->class;
    }
}