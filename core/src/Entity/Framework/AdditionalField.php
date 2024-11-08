<?php

namespace App\Entity\Framework;

use App\Repository\Framework\AdditionalFieldRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdditionalFieldRepository::class)]
#[ORM\Table(name: 'salt_additional_field')]
#[ORM\Index(columns: ['applies_to'], name: 'applies_idx')]
#[UniqueEntity(fields: ['name'], message: 'The custom field name is already used.')]
class AdditionalField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9_]+$/', message: 'The name may only have lower case alpha-numeric characters and underscores.')]
    #[Assert\Regex(pattern: '/(^_)|(_$)|(_[^a-z])/', message: 'An underscore (_) must be before a letter (not a number or underscore), and must not be at the beginning of the name.', match: false)]
    #[Assert\Regex(pattern: '/^[a-z]/', message: 'The name must start with a lower case letter.', match: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    private ?string $appliesTo = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    private ?string $displayName = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotNull]
    #[Assert\Choice(callback: 'getTypes', message: 'The field type is not a valid type.')]
    private ?string $type = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $typeInfo = null;

    /**
     * Return the valid field types available.
     */
    public static function getTypes(): array
    {
        return [
            'string',
        ];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAppliesTo(): ?string
    {
        return $this->appliesTo;
    }

    public function setAppliesTo(string $appliesTo): void
    {
        $this->appliesTo = $appliesTo;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getTypeInfo(): ?array
    {
        return $this->typeInfo;
    }

    /**
     * Set typeInfo.
     *
     * @param array|null $typeInfo Used to define how different field types need to behave
     */
    public function setTypeInfo(?array $typeInfo): void
    {
        $this->typeInfo = $typeInfo;
    }
}
