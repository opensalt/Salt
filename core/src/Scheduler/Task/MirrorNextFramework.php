<?php

namespace App\Scheduler\Task;

use App\Service\MirrorFramework;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[AsCronTask('* * * * *', jitter: 10)]
readonly class MirrorNextFramework
{
    public function __construct(
        private MirrorFramework $mirrorFramework,
    ) {
    }

    public function __invoke(): void
    {
        $this->mirrorFramework->mirrorNext();
    }
}
