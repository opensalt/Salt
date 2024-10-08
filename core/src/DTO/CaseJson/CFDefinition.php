<?php

namespace App\DTO\CaseJson;

use Symfony\Component\Serializer\Annotation\SerializedName;

class CFDefinition
{
    /**
     * @var CFConcept[]|null
     */
    #[SerializedName('CFConcepts')]
    public ?array $cfConcepts = [];

    /**
     * @var CFSubject[]|null
     */
    #[SerializedName('CFSubjects')]
    public ?array $cfSubjects = [];

    /**
     * @var CFLicense[]|null
     */
    #[SerializedName('CFLicenses')]
    public ?array $cfLicenses = [];

    /**
     * @var CFItemType[]|null
     */
    #[SerializedName('CFItemTypes')]
    public ?array $cfItemTypes = [];

    /**
     * @var CFAssociationGrouping[]|null
     */
    #[SerializedName('CFAssociationGroupings')]
    public ?array $cfAssociationGroupings = [];
}
