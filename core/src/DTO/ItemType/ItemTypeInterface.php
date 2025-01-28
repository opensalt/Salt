<?php

namespace App\DTO\ItemType;

use App\Entity\Framework\LsItem;

interface ItemTypeInterface
{
    public const int ITEM_TYPE_IDENTIFIER = 0;
    public const string ITEM_TYPE_FORM = 'MUST_OVERRIDE';

    public static function fromItem(LsItem $item): self;

    public function applyToItem(LsItem $item): void;
}
