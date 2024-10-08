<?php

namespace App\DTO\CaseJson;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

class CFRubricCriterion
{
    public UuidInterface $identifier;
    public string $uri;
    public ?string $category = null;
    public ?string $description = null;

    #[SerializedName('CFItemURI')]
    public ?LinkURI $cfItemURI = null;

    public ?float $weight = null;
    public ?int $position = null;
    public ?string $rubricId = null;
    public \DateTimeInterface $lastChangeDateTime;

    /**
     * @var CFRubricCriterionLevel[]|null
     */
    #[SerializedName('CFRubricCriterionLevels')]
    public ?array $cfRubricCriterionLevels = [];
}
