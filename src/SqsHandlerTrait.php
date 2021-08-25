<?php

namespace App;

use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;

trait SqsHandlerTrait
{
    abstract public function handleSqs(SqsEvent $event, Context $context): void;

    public function handle($event, Context $context): void
    {
        $this->handleSqs(new SqsEvent($event), $context);
    }
}
