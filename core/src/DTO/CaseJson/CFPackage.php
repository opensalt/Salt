<?php

namespace App\DTO\CaseJson;

use Symfony\Component\Serializer\Annotation\SerializedName;

class CFPackage
{
    #[SerializedName('CFDocument')]
    public CFPackageDocument $cfDocument;

    /**
     * @var CFPackageItem[]|null
     */
    #[SerializedName('CFItems')]
    public ?array $cfItems = [];

    /**
     * @var CFPackageAssociation[]|null
     */
    #[SerializedName('CFAssociations')]
    public ?array $cfAssociations = [];

    #[SerializedName('CFDefinitions')]
    public ?CFDefinition $cfDefinitions = null;

    /**
     * @var CFRubric[]|null
     */
    #[SerializedName('CFRubrics')]
    public ?array $cfRubrics = [];
}
