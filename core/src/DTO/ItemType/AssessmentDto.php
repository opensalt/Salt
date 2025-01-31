<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsItem;
use App\Form\Type\LsItemAssessmentType;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AssessmentDto implements ItemTypeInterface
{
    public const int ITEM_TYPE_IDENTIFIER = LsItem::TYPES['assessment'];
    public const string ITEM_TYPE_FORM = LsItemAssessmentType::class;
    public const string WEBPAGE_KEY = 'ceterms:subjectWebpage';
    public const string DELIVERY_TYPE_KEY = 'ceterms:deliveryType';

    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(max: 255)]
        public ?string $name = null,
        #[Assert\NotBlank()]
        public ?string $description = null,
        public ?string $deliveryType = null,
        public ?string $inLanguage = null,
        public ?string $keywords = null,
        #[Assert\Url(message: 'The webpage must be a valid URL.', requireTld: true)]
        public ?string $webpage = null,
    ) {
    }

    public static function fromItem(LsItem $item): self
    {
        $jobItemInfo = $item->getExtraProperty('extendedItem');

        return new self(
            $item->getAbbreviatedStatement(),
            $item->getFullStatement(),
            $jobItemInfo[self::DELIVERY_TYPE_KEY] ?? null,
            $item->getLanguage(),
            $item->getConceptKeywordsString(),
            $jobItemInfo[self::WEBPAGE_KEY] ?? null,
        );
    }

    public function applyToItem(LsItem $item, HtmlSanitizerInterface $htmlSanitizer): void
    {
        $item->setAbbreviatedStatement($this->name);
        $item->setFullStatement($this->description);
        $item->setConceptKeywordsString($this->keywords);
        $item->setLanguage($this->inLanguage);
        $itemInfo = [
            'type' => 'assessment',
        ];
        if ($this->webpage) {
            $itemInfo[self::WEBPAGE_KEY] = $this->webpage;
        }
        if ($this->deliveryType) {
            $itemInfo[self::DELIVERY_TYPE_KEY] = $this->deliveryType;
        }
        $item->setExtraProperty('extendedItem', $itemInfo);
    }
}
