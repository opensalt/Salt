<?php

namespace App\Form\DTO;

use App\Entity\Framework\Mirror\OAuthCredential;
use App\Entity\Framework\Mirror\Server;
use Symfony\Component\Validator\Constraints as Assert;

class MirroredServerDTO
{
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    public ?string $url = null;

    #[Assert\NotNull]
    public bool $autoAddFoundFrameworks = false;

    public ?OAuthCredential $credentials = null;

    public string $status = Server::STATUS_ACTIVE;
}
