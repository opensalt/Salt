<?php

namespace App\Form\DTO;

use App\Entity\Framework\Mirror\OAuthCredential;
use Symfony\Component\Validator\Constraints as Assert;

class MirroredFrameworkDTO
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
    public $visible;

    /**
     * @var OAuthCredential|null
     */
    public $credentials;
}
