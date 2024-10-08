<?php

namespace App\Form\DTO;

use App\Entity\Framework\Mirror\OAuthCredential;
use Symfony\Component\Validator\Constraints as Assert;

class MirroredServerDTO
{
    /**
     * @var string
     */
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Url(requireTld: true)]
    public $url;

    /**
     * @var bool
     */
    #[Assert\NotNull]
    public $autoAddFoundFrameworks = false;

    /**
     * @var OAuthCredential|null
     */
    public $credentials;
}
