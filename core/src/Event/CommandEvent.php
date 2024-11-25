<?php

namespace App\Event;

use App\Command\CommandInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class CommandEvent extends GenericEvent
{
    public function __construct(?CommandInterface $subject = null, array $arguments = [])
    {
        parent::__construct($subject, $arguments);
    }

    public function getCommand(): CommandInterface
    {
        return $this->getSubject();
    }
}
