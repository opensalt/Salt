<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsItem;
use App\Form\Type\LsItemCredentialType;
use Symfony\Component\Validator\Constraints as Assert;

class CredentialDto implements ItemTypeInterface
{
    public const int ITEM_TYPE_IDENTIFIER = LsItem::TYPES['credential'];
    public const string ITEM_TYPE_FORM = LsItemCredentialType::class;
    public const string CREDENTIAL_KEY = 'ob3';

    public function __construct(
        #[Assert\NotBlank()]
        public ?string $credential = null,
    ) {
    }

    public static function fromItem(LsItem $item): self
    {
        $jobItemInfo = $item->getExtraProperty('extendedItem');

        return new self(
            $jobItemInfo[self::CREDENTIAL_KEY] ?? null,
        );
    }

    public function applyToItem(LsItem $item): void
    {
        $credentialInfo = json5_decode($this->credential, true);
        $item->setAbbreviatedStatement($credentialInfo['name'] ?? null);
        $item->setFullStatement($credentialInfo['description'] ?? null);
        $item->setHumanCodingScheme($credentialInfo['humanCode'] ?? null);
        $item->setLanguage($credentialInfo['inLanguage'] ?? null);
        $item->setConceptKeywordsArray($credentialInfo['tag'] ?? null);

        $itemInfo = [
            'type' => 'credential',
        ];
        $itemInfo[self::CREDENTIAL_KEY] = $this->credential;
        $item->setExtraProperty('extendedItem', $itemInfo);
    }
}
