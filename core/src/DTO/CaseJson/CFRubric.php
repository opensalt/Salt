<?php

namespace App\DTO\CaseJson;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

class CFRubric
{
    public UuidInterface $identifier;
    public string $uri;
    public ?string $title = null;
    public ?string $description = null;
    public \DateTimeInterface $lastChangeDateTime;

    /**
     * @var CFRubricCriterion[]|null
     */
    #[SerializedName('CFRubricCriteria')]
    public ?array $cfRubricCriteria = [];
}
