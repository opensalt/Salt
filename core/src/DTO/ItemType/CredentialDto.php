<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsItem;
use App\Form\Type\LsItemCredentialType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
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

    public function applyToItem(LsItem $item, HtmlSanitizerInterface $htmlSanitizer): void
    {
        $credentialInfo = json5_decode($this->credential, true);
        dump($credentialInfo);
        $description = $credentialInfo['description'] ?? null;
        if (null !== $description) {
            $credentialInfo['description'] = $htmlSanitizer->sanitizeFor('div', $description);
        }
        $narrative = $credentialInfo['criteria']['narrative'] ?? null;
        if (null !== $narrative) {
            $credentialInfo['criteria']['narrative'] = $htmlSanitizer->sanitizeFor('div', $narrative);
        }
        dump($description, $narrative);
        dump($credentialInfo);

        $item->setAbbreviatedStatement($credentialInfo['name'] ?? null);
        $item->setFullStatement($credentialInfo['description'] ?? null);
        $item->setHumanCodingScheme($credentialInfo['humanCode'] ?? null);
        $item->setLanguage($credentialInfo['inLanguage'] ?? null);
        $item->setConceptKeywordsArray($credentialInfo['tag'] ?? null);

        $itemInfo = [
            'type' => 'credential',
        ];
        $itemInfo[self::CREDENTIAL_KEY] = json_encode($credentialInfo, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $item->setExtraProperty('extendedItem', $itemInfo);
    }
}
