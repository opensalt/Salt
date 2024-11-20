<?php

namespace App\Scheduler\Task;

use App\Service\MirrorServer;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask('* * * * *', jitter: 10)]
readonly class UpdateNextMirrorServer
{
    public function __construct(
        private MirrorServer $mirrorServer,
    ) {
    }

    public function __invoke(): void
    {
        $this->mirrorServer->updateNext();
    }
}
