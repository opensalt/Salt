<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsItem;
use App\Form\Type\LsItemOrganizationType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class OrganizationDto implements ItemTypeInterface
{
    public const int ITEM_TYPE_IDENTIFIER = LsItem::TYPES['organization'];
    public const string ITEM_TYPE_FORM = LsItemOrganizationType::class;
    public const string TYPE_KEY = 'ceterms:agentType';
    public const string WEBPAGE_KEY = 'ceterms:subjectWebpage';
    public const string JURISDICTION_KEY = 'ceterms:jurisdiction';

    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(max: 255)]
        public ?string $name = null,
        #[Assert\NotBlank()]
        public ?string $description = null,
        public ?string $type = null,
        #[Assert\Url(requireTld: true)]
        public ?string $webpage = null,
        public ?string $jurisdiction = null,
    ) {
    }

    public static function fromItem(LsItem $item): self
    {
        $jobItemInfo = $item->getExtraProperty('extendedItem');

        return new self(
            $item->getAbbreviatedStatement(),
            $item->getFullStatement(),
            $jobItemInfo[self::TYPE_KEY] ?? null,
            $jobItemInfo[self::WEBPAGE_KEY] ?? null,
            $jobItemInfo[self::JURISDICTION_KEY] ?? null,
        );
    }

    public function applyToItem(LsItem $item, HtmlSanitizerInterface $htmlSanitizer): void
    {
        $item->setAbbreviatedStatement($this->name);
        $item->setFullStatement($this->description);
        $itemInfo = [
            'type' => 'organization',
        ];
        if ($this->type) {
            $itemInfo[self::TYPE_KEY] = $this->type;
        }
        if ($this->webpage) {
            $itemInfo[self::WEBPAGE_KEY] = $this->webpage;
        }
        if ($this->jurisdiction) {
            $itemInfo[self::JURISDICTION_KEY] = $this->jurisdiction;
        }
        $item->setExtraProperty('extendedItem', $itemInfo);
    }
}
