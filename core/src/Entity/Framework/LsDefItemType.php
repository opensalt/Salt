<?php

namespace App\Entity\Framework;

use App\Repository\Framework\LsDefItemTypeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'ls_def_item_type')]
#[ORM\Entity(repositoryClass: LsDefItemTypeRepository::class)]
class LsDefItemType extends AbstractLsDefinition implements CaseApiInterface
{
    #[ORM\Column(name: 'code', type: 'string', length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(name: 'hierarchy_code', type: 'string', length: 255)]
    private string $hierarchyCode;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getHierarchyCode(): string
    {
        return $this->hierarchyCode;
    }

    public function setHierarchyCode(string $hierarchyCode): void
    {
        $this->hierarchyCode = $hierarchyCode;
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? $this->getIdentifier();
    }
}
