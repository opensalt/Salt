<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsDefItemType;
use App\Entity\Framework\LsItem;
use App\Form\Type\LsItemCourseType;
use Symfony\Component\Validator\Constraints as Assert;

class CourseDto implements ItemTypeInterface
{
    public const string ITEM_TYPE_IDENTIFIER = LsDefItemType::TYPE_COURSE_IDENTIFIER;
    public const string ITEM_TYPE_FORM = LsItemCourseType::class;
    public const string WEBPAGE_KEY = 'ceterms:subjectWebpage';
    public const string DELIVERY_TYPE_KEY = 'ceterms:deliveryType';

    public function __construct(
        #[Assert\NotBlank()]
        #[Assert\Length(max: 255)]
        public ?string $name = null,
        #[Assert\NotBlank()]
        public ?string $description = null,
        #[Assert\Url(requireTld: true)]
        public ?string $webpage = null,
        public ?string $codedNotation = null,
        public ?string $inLanguage = null,
        public ?string $deliveryType = null,
    ) {
    }

    public static function fromItem(LsItem $item): self
    {
        $jobItemInfo = $item->getExtraProperty('extendedItem');

        return new self(
            $item->getAbbreviatedStatement(),
            $item->getFullStatement(),
            $jobItemInfo[self::WEBPAGE_KEY] ?? null,
            $item->getHumanCodingScheme(),
            $item->getLanguage(),
            $jobItemInfo[self::DELIVERY_TYPE_KEY] ?? null
        );
    }

    public function applyToItem(LsItem $item): void
    {
        $item->setAbbreviatedStatement($this->name);
        $item->setFullStatement($this->description);
        $item->setHumanCodingScheme($this->codedNotation);
        $item->setLanguage($this->inLanguage);
        $itemInfo = [
            'type' => 'course',
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
