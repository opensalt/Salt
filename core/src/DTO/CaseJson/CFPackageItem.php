<?php

namespace App\DTO\CaseJson;

use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Annotation\SerializedName;

class CFPackageItem
{
    public UuidInterface $identifier;
    public string $uri;
    public string $fullStatement;
    public ?string $alternativeLabel = null;

    #[SerializedName('CFItemType')]
    public ?string $cfItemType = null;

    public ?string $humanCodingScheme = null;
    public ?string $listEnumeration = null;
    public ?string $abbreviatedStatement = null;

    /** @var string[]|array|null */
    public ?array $conceptKeywords = null;

    public ?LinkURI $conceptKeywordsURI = null;
    public ?string $notes = null;
    public ?string $language = null;

    /** @var string[]|string|null */
    public string|array|null $educationLevel = null;

    #[SerializedName('CFItemTypeURI')]
    public ?LinkURI $cfItemTypeURI = null;
    public ?LinkURI $licenseURI = null;
    public ?\DateTimeInterface $statusStartDate = null;
    public ?\DateTimeInterface $statusEndDate = null;
    public \DateTimeInterface $lastChangeDateTime;
}
