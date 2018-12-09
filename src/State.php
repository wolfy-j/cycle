<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM;

use Spiral\ORM\Command\ContextCarrierInterface;
use Spiral\ORM\Context\ConsumerInterface;
use Spiral\ORM\Context\ProducerInterface;
use Spiral\ORM\Traits\ClaimTrait;
use Spiral\ORM\Traits\RelationTrait;
use Spiral\ORM\Traits\VisitorTrait;

/**
 * Current node state.
 */
class State implements ConsumerInterface, ProducerInterface
{
    use RelationTrait, ClaimTrait, VisitorTrait;

    /** @var int */
    private $state;

    /** @var array */
    private $data;

    /** @var null|ContextCarrierInterface */
    private $command;

    /** @var ContextCarrierInterface[] */
    private $contextPath;

    /**
     * @param int   $state
     * @param array $data
     */
    public function __construct(int $state, array $data)
    {
        $this->state = $state;
        $this->data = $data;
    }

    /**
     * Set new state value.
     *
     * @param int $state
     */
    public function setStatus(int $state): void
    {
        $this->state = $state;
    }

    /**
     * Get current state.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->state;
    }

    /**
     * Set new state data (will trigger state handlers).
     *
     * @param array $data
     */
    public function setData(array $data)
    {
        if (empty($data)) {
            return;
        }

        foreach ($data as $column => $value) {
            $this->register($column, $value);
        }
    }

    /**
     * Get current state data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set the reference to the object creation command (non executed).
     *
     * @internal
     * @param ContextCarrierInterface|null $cmd
     */
    public function setCommand(ContextCarrierInterface $cmd = null)
    {
        $this->command = $cmd;
    }

    /**
     * @internal
     * @return null|ContextCarrierInterface
     */
    public function getCommand(): ?ContextCarrierInterface
    {
        return $this->command;
    }

    /**
     * @inheritdoc
     */
    public function listen(
        string $key,
        ConsumerInterface $acceptor,
        string $target,
        bool $trigger = false,
        int $stream = ConsumerInterface::DATA
    ) {
        $this->contextPath[$key][] = [$acceptor, $target, $stream];

        if ($trigger || !empty($this->data[$key])) {
            $this->register($key, $this->data[$key] ?? null, false, $stream);
        }
    }

    /**
     * @inheritdoc
     */
    public function register(
        string $key,
        $value,
        bool $update = false,
        int $stream = self::DATA
    ) {
        if (!$update) {
            $update = ($this->data[$key] ?? null) != $value;
        }

        $this->data[$key] = $value;

        // cascade
        if (!empty($this->contextPath[$key])) {
            foreach ($this->contextPath[$key] as $id => $h) {
                /** @var ConsumerInterface $acc */
                $acc = $h[0];
                $acc->register($h[1], $value, $update, $h[2]);
                $update = false;
            }
        }
    }
}