<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\ORM\Tests\Command;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Spiral\ORM\Command\Control\PrimarySequence;
use Spiral\ORM\Command\Control\Nil;
use Spiral\ORM\Command\Control\Sequence;
use Spiral\ORM\Command\Database\Insert;

class SequenceCommandTest extends TestCase
{
    public function testNestedCommands()
    {
        $command = new Sequence();

        $command->addCommand(new Nil());
        $command->addCommand(new Nil());
        $command->addCommand(m::mock(Insert::class));
        $command->addCommand(m::mock(Insert::class));

        $count = 0;
        foreach ($command as $sub) {
            $this->assertInstanceOf(Insert::class, $sub);
            $count++;
        }

        $this->assertSame(2, $count);

        //Nothing
        $command->execute();
        $command->complete();
        $command->rollBack();
    }

    /**
     * @expectedException \Spiral\ORM\Exception\CommandException
     */
    public function testGetLeadingBad()
    {
        $command = new PrimarySequence();
        $command->getContext();
    }

    public function testGetContext()
    {
        $command = new PrimarySequence();
        $command->addPrimary($lead = m::mock(Insert::class));

        $lead->shouldReceive('getContext')->andReturn(['hi']);

        $this->assertSame(['hi'], $command->getContext());
    }

    public function testAddContext()
    {
        $command = new PrimarySequence();
        $command->addPrimary($lead = m::mock(Insert::class));

        $lead->shouldReceive('setContext')->with('name', 'value');

        $command->setContext('name', 'value');
        $this->assertTrue(true);
    }

    public function testPassCallbackExecute()
    {
        $command = new PrimarySequence();
        $command->addPrimary($lead = m::mock(Insert::class));

        $f = function () {
        };

        $lead->shouldReceive('onExecute')->with($f);
        $command->onExecute($f);
        $this->assertTrue(true);
    }

    public function testPassCallbackComplete()
    {
        $command = new PrimarySequence();
        $command->addPrimary($lead = m::mock(Insert::class));

        $f = function () {
        };

        $lead->shouldReceive('onComplete')->with($f);
        $command->onComplete($f);
        $this->assertTrue(true);
    }

    public function testPassCallbackRollback()
    {
        $command = new PrimarySequence();
        $command->addPrimary($lead = m::mock(Insert::class));

        $f = function () {
        };

        $lead->shouldReceive('onRollback')->with($f);
        $command->onRollBack($f);
        $this->assertTrue(true);
    }
}