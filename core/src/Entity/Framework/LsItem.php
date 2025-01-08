<?php

namespace App\Entity\Framework;

use App\Entity\LockableInterface;
use App\Repository\Framework\LsItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'ls_item')]
#[ORM\Entity(repositoryClass: LsItemRepository::class)]
#[UniqueEntity('uri')]
class LsItem extends AbstractLsBase implements CaseApiInterface, LockableInterface
{
    use AccessAdditionalFieldTrait;

    #[ORM\Column(name: 'ls_doc_identifier', type: 'string', length: 300, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private string $lsDocIdentifier;

    #[ORM\Column(name: 'ls_doc_uri', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $lsDocUri = null;

    #[ORM\ManyToOne(targetEntity: LsDoc::class, inversedBy: 'lsItems')]
    #[Assert\NotBlank]
    private LsDoc $lsDoc;

    #[ORM\Column(name: 'human_coding_scheme', type: 'string', length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $humanCodingScheme = null;

    #[ORM\Column(name: 'list_enum_in_source', type: 'string', length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $listEnumInSource = null;

    #[ORM\Column(name: 'full_statement', type: 'text', nullable: false)]
    #[Assert\NotBlank]
    private string $fullStatement;

    #[ORM\Column(name: 'abbreviated_statement', type: 'text', nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $abbreviatedStatement = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(name: 'concept_keywords', type: 'json', nullable: true)]
    #[Assert\All([new Assert\Type('string')])]
    private ?array $conceptKeywords = [];

    /**
     * @var Collection<array-key, LsDefConcept>
     */
    #[ORM\ManyToMany(targetEntity: LsDefConcept::class)]
    #[ORM\JoinTable(name: 'ls_item_concept')]
    #[ORM\JoinColumn(name: 'ls_item_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'concept_id', referencedColumnName: 'id')]
    private Collection $concepts;

    #[ORM\Column(name: 'notes', type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'language', type: 'string', length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $language = null;

    #[ORM\Column(name: 'educational_alignment', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $educationalAlignment = null;

    #[ORM\ManyToOne(targetEntity: LsDefItemType::class)]
    #[ORM\JoinColumn(name: 'item_type_id', referencedColumnName: 'id')]
    private ?LsDefItemType $itemType = null;

    #[ORM\Column(name: 'item_type_text', type: 'string', nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $itemTypeText = null;

    #[ORM\Column(name: 'alternative_label', type: 'text', nullable: true)]
    private ?string $alternativeLabel = null;

    #[ORM\Column(name: 'status_start', type: 'date', nullable: true)]
    private ?\DateTimeInterface $statusStart = null;

    #[ORM\Column(name: 'status_end', type: 'date', nullable: true)]
    private ?\DateTimeInterface $statusEnd = null;

    #[ORM\ManyToOne(targetEntity: LsDefLicence::class)]
    #[ORM\JoinColumn(name: 'licence_id', referencedColumnName: 'id', nullable: true)]
    private ?LsDefLicence $licence = null;

    /**
     * @var Collection<array-key, LsAssociation>
     */
    #[ORM\OneToMany(mappedBy: 'originLsItem', targetEntity: LsAssociation::class, cascade: ['persist'], indexBy: 'id')]
    private Collection $associations;

    /**
     * @var Collection<array-key, LsAssociation>
     */
    #[ORM\OneToMany(mappedBy: 'destinationLsItem', targetEntity: LsAssociation::class, cascade: ['persist'], indexBy: 'id')]
    private Collection $inverseAssociations;

    /**
     * @var Collection<array-key, CfRubricCriterion>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: CfRubricCriterion::class)]
    private Collection $criteria;

    public function __construct(UuidInterface|string|null $identifier = null)
    {
        parent::__construct($identifier);

        $this->associations = new ArrayCollection();
        $this->inverseAssociations = new ArrayCollection();
        $this->criteria = new ArrayCollection();
        $this->concepts = new ArrayCollection();
    }

    /**
     * Representation of this item as a string.
     */
    public function __toString(): string
    {
        return $this->getUri();
    }

    /**
     * Clone the LsItem - Do not carry over any associations.
     */
    public function __clone()
    {
        parent::__clone();

        // Clear values for new item
        $this->associations = new ArrayCollection();
        $this->inverseAssociations = new ArrayCollection();
    }

    /**
     * Create a copy of the lsItem into a new document.
     *
     * @throws \UnexpectedValueException
     */
    public function copyToLsDoc(LsDoc $newLsDoc, ?LsDefAssociationGrouping $assocGroup = null, bool $exactMatchAssocs = true): static
    {
        // Clear out values so the clone will work without an out of memory error
        $associations = $this->associations;
        $this->associations = new ArrayCollection();
        $inverseAssociations = $this->inverseAssociations;
        $this->inverseAssociations = new ArrayCollection();
        $concepts = $this->concepts;
        $this->concepts = new ArrayCollection();
        $criteria = $this->criteria;
        $this->criteria = new ArrayCollection();

        $newItem = clone $this;

        // Add the values back to the original
        $this->associations = $associations;
        $this->inverseAssociations = $inverseAssociations;
        $this->concepts = $concepts;
        $this->criteria = $criteria;

        // Add values to the new item
        foreach ($this->concepts as $concept) {
            $newItem->addConcept($concept);
        }
        foreach ($this->criteria as $criteria) {
            $newItem->addCriterion($criteria);
        }

        $newItem->setLsDoc($newLsDoc);

        // Add an "Exact" relationship to the original
        if ($exactMatchAssocs) {
            $exactMatch = $newLsDoc->createAssociation();
            $exactMatch->setOrigin($newItem);
            $exactMatch->setType(LsAssociation::EXACT_MATCH_OF);
            $exactMatch->setDestination($this);

            // PW: set assocGroup if provided and non-null
            // TODO: should the assocGroup be on both associations, or just the first association, or just the inverse association??
            if (null !== $assocGroup) {
                $exactMatch->setGroup($assocGroup);
            }
            $newItem->addAssociation($exactMatch);
            $this->addInverseAssociation($exactMatch);
        }

        $seq = 0;
        foreach ($this->getChildren() as $child) {
            $newChild = $child->copyToLsDoc($newLsDoc, $assocGroup, $exactMatchAssocs);
            $newItem->addChild($newChild, $assocGroup, ++$seq);
        }

        return $newItem;
    }

    /**
     * Create a duplicate of the lsItem into a new document.
     *
     * @throws \UnexpectedValueException
     */
    public function duplicateToLsDoc(LsDoc $newLsDoc, ?LsDefAssociationGrouping $assocGroup = null): static
    {
        // Clear out values so the clone will work without an out of memory error
        $associations = $this->associations;
        $this->associations = new ArrayCollection();
        $inverseAssociations = $this->inverseAssociations;
        $this->inverseAssociations = new ArrayCollection();
        $concepts = $this->concepts;
        $this->concepts = new ArrayCollection();
        $criteria = $this->criteria;
        $this->criteria = new ArrayCollection();

        $newItem = clone $this;

        // Add the values back to the original
        $this->associations = $associations;
        $this->inverseAssociations = $inverseAssociations;
        $this->concepts = $concepts;
        $this->criteria = $criteria;

        // Add values to the new item
        foreach ($this->concepts as $concept) {
            $newItem->addConcept($concept);
        }
        foreach ($this->criteria as $criteria) {
            $newItem->addCriterion($criteria);
        }

        $newItem->setLsDoc($newLsDoc);

        foreach ($this->getAssociations() as $association) {
            if (LsAssociation::CHILD_OF === $association->getType()) {
                continue;
            }

            $newAssoc = $newLsDoc->createAssociation();
            $newAssoc->setOrigin($newItem);
            $newAssoc->setType($association->getType());
            $newAssoc->setDestination($association->getDestination(), $association->getDestinationNodeIdentifier());
            $newItem->addAssociation($newAssoc);
        }

        foreach ($this->getChildren() as $child) {
            $newChild = $child->duplicateToLsDoc($newLsDoc, $assocGroup);
            $newItem->addChild($newChild, $assocGroup);
        }

        return $newItem;
    }

    public function createItem(UuidInterface|string|null $identifier = null): LsItem
    {
        return $this->getLsDoc()->createItem($identifier);
    }

    public function createAssociation(UuidInterface|string|null $identifier = null): LsAssociation
    {
        return $this->getLsDoc()->createAssociation($identifier);
    }

    public function getGroupedAssociations(): array
    {
        $groups = [
//            'Children' => $this->getChildren(),
//            'Parent' => $this->getLsItemParent(),
        ];

//        $topItems = $this->getTopItemOf();
//        foreach ($topItems as $item) {
//            $groups['Parent']->add($item);
//        }
//        if ($groups['Parent']->isEmpty()) {
//            $groups['Parent']->add($this->getLsDoc());
//        }

        $typeList = LsAssociation::allTypes();
        foreach ($typeList as $type) {
            $groups[$type] = new ArrayCollection();
            $assocName = LsAssociation::inverseName($type);
            if (null === $assocName) {
                $assocName = 'Inverse '.$type;
            }
            $groups[$assocName] = new ArrayCollection();
        }

        $associations = $this->getAssociations();
        foreach ($associations as $association) {
            /** @var LsAssociation $association */
            if ($association->getLsDoc()->getId() !== $this->getLsDoc()->getId()) {
                continue;
            }
            /** @psalm-suppress InvalidArgument */
            $groups[$association->getType()]->add($association);
        }

        $associations = $this->getInverseAssociations();
        foreach ($associations as $association) {
            /** @var LsAssociation $association */
            /* Commented out to show relations from other docs
            if ($association->getLsDoc()->getId() !== $this->getLsDoc()->getId()) {
                continue;
            }
            */
            $assocName = LsAssociation::inverseName($association->getType());
            if (null === $assocName) {
                $assocName = 'Inverse '.$association->getType();
            }

            /** @psalm-suppress InvalidArgument */
            $groups[$assocName]->add($association);
        }

        return $groups;
    }

    /**
     * Get a representation of the item.
     */
    public function getDisplayIdentifier(): string
    {
        if (null !== $this->humanCodingScheme) {
            return $this->humanCodingScheme;
        }

        if (null !== $this->abbreviatedStatement) {
            return $this->abbreviatedStatement;
        }

        if ($this->fullStatement) {
            return $this->fullStatement;
        }

        $uri = $this->getUri();
        $uri = preg_replace('#^.*/#', '', $uri);
        $uri = preg_replace('#^local:#', '', $uri);

        return $uri;
    }

    /**
     * Get a short version of the statement.
     */
    public function getShortStatement(): string
    {
        return $this->getAbbreviatedStatement() ?? mb_substr($this->getFullStatement() ?? 'Unknown', 0, 60);
    }

    public function setLsDocUri(?string $lsDocUri): static
    {
        $this->lsDocUri = $lsDocUri;

        return $this;
    }

    public function getLsDocUri(): ?string
    {
        return $this->lsDocUri;
    }

    public function setHumanCodingScheme(?string $humanCodingScheme): static
    {
        $this->humanCodingScheme = $humanCodingScheme;

        return $this;
    }

    public function getHumanCodingScheme(): ?string
    {
        return $this->humanCodingScheme;
    }

    public function setListEnumInSource(?string $listEnumInSource): static
    {
        $this->listEnumInSource = $listEnumInSource;

        return $this;
    }

    public function getListEnumInSource(): ?string
    {
        return $this->listEnumInSource;
    }

    public function setFullStatement(string $fullStatement): static
    {
        $this->fullStatement = $fullStatement;

        return $this;
    }

    public function getFullStatement(): ?string
    {
        return $this->fullStatement;
    }

    public function setAbbreviatedStatement(?string $abbreviatedStatement): static
    {
        $this->abbreviatedStatement = $abbreviatedStatement;

        return $this;
    }

    public function getAbbreviatedStatement(): ?string
    {
        return $this->abbreviatedStatement;
    }

    /**
     * @param string[]|null $conceptKeywords
     */
    public function setConceptKeywordsArray(?array $conceptKeywords): static
    {
        if (null === $conceptKeywords) {
            $conceptKeywords = [];
        }

        $this->conceptKeywords = $conceptKeywords;

        return $this;
    }

    public function getConceptKeywordsArray(): array
    {
        return $this->conceptKeywords ?? [];
    }

    /**
     * @deprecated Migrate to using setConceptKeywordsArray()
     */
    public function setConceptKeywords(?string $conceptKeywords): static
    {
        return $this->setConceptKeywordsString($conceptKeywords);
    }

    /**
     * @deprecated Migrate to using getConceptKeywordsArray()
     */
    public function getConceptKeywords(): ?string
    {
        return $this->getConceptKeywordsString();
    }

    public function setConceptKeywordsString(?string $conceptKeywords): static
    {
        if (null === $conceptKeywords) {
            $conceptKeywords = '';
        }

        $values = preg_split('/ *, */', $conceptKeywords, -1, PREG_SPLIT_NO_EMPTY);

        $this->setConceptKeywordsArray($values);

        return $this;
    }

    public function getConceptKeywordsString(): ?string
    {
        return implode(',', $this->getConceptKeywordsArray());
    }

    /**
     * @deprecated Should use getConcepts() and use the set returned instead, this only gives the first
     */
    public function getConceptKeywordsUri(): ?string
    {
        $concepts = $this->getConcepts();

        if ($concepts->isEmpty()) {
            return null;
        }

        return $concepts->first()->getUri();
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    /**
     * @param string|string[]|null $educationalAlignment
     */
    public function setEducationalAlignment(array|string|null $educationalAlignment): static
    {
        if (null === $educationalAlignment) {
            $this->educationalAlignment = null;

            return $this;
        }

        if (is_string($educationalAlignment)) {
            $this->educationalAlignment = $educationalAlignment;

            return $this;
        }

        $this->educationalAlignment = implode(',', $educationalAlignment);

        return $this;
    }

    public function getEducationalAlignment(): ?string
    {
        return $this->educationalAlignment;
    }

    public function getType(): ?string
    {
        $itemType = $this->itemType;
        if (null !== $itemType) {
            return $itemType->getTitle();
        }

        return null;
    }

    /**
     * Add an item as a child this item.
     *
     * @throws \UnexpectedValueException
     */
    public function addChild(LsItem $child, ?LsDefAssociationGrouping $assocGroup = null, ?int $sequenceNumber = null): static
    {
        $association = new LsAssociation();
        $association->setLsDoc($child->getLsDoc());
        $association->setOrigin($child);
        $association->setType(LsAssociation::CHILD_OF);
        $association->setDestination($this);

        if (null !== $sequenceNumber) {
            $association->setSequenceNumber($sequenceNumber);
        }

        if (null !== $assocGroup) {
            $association->setGroup($assocGroup);
        }

        $child->addAssociation($association);
        $this->addInverseAssociation($association);

        return $this;
    }

    /**
     * @return Collection<array-key, LsItem>
     */
    public function getChildren(): Collection
    {
        /** @var Collection<array-key, LsItem> $children */
        $children = new ArrayCollection();

        $associations = $this->getInverseAssociations();
        foreach ($associations as $association) {
            if (LsAssociation::CHILD_OF === $association->getType()) {
                $child = $association->getOriginLsItem();
                if (null !== $child) {
                    $children->add($child);
                }
            }
        }

        return $children;
    }

    /**
     * @return array<array-key, int>
     */
    public function getChildIds(): array
    {
        $ids = $this->getChildren()->map(
            static fn (LsItem $item): int => $item->getId()
        );

        return $ids->toArray();
    }

    /**
     * Find all children items of this item.
     */
    public function getDescendantIds(): array
    {
        $childIds = [];
        $hasChildren = $this->getChildren();
        foreach ($hasChildren as $child) {
            $id = $child->getId();
            $childIds[$id] = $id;
            $childIds = array_merge($childIds, $child->getDescendantIds());
        }

        return $childIds;
    }

    public function setLsDoc(LsDoc $lsDoc): static
    {
        $this->lsDoc = $lsDoc;
        $this->lsDocUri = $lsDoc->getUri();
        $this->lsDocIdentifier = $lsDoc->getIdentifier();

        return $this;
    }

    public function getLsDoc(): LsDoc
    {
        return $this->lsDoc;
    }

    /**
     * @return Collection<array-key, LsItem>
     */
    public function getLsItemParent(): Collection
    {
        $parents = new ArrayCollection();
        $associations = $this->getAssociations();
        foreach ($associations as $association) {
            /** @var LsAssociation $association */
            if (LsAssociation::CHILD_OF === $association->getType()
                && null !== $association->getDestinationLsItem()
            ) {
                /** @psalm-suppress InvalidArgument */
                $parents->add($association->getDestinationLsItem());
            }
        }

        return $parents;
    }

    public function addAssociation(LsAssociation $association): static
    {
        $this->associations[] = $association;

        return $this;
    }

    public function removeAssociation(LsAssociation $association): static
    {
        $this->associations->removeElement($association);

        return $this;
    }

    /**
     * @return Collection<array-key, LsAssociation>
     */
    public function getAssociations(): Collection
    {
        return $this->associations;
    }

    public function addInverseAssociation(LsAssociation $inverseAssociation): static
    {
        $this->inverseAssociations[] = $inverseAssociation;

        return $this;
    }

    public function removeInverseAssociation(LsAssociation $inverseAssociation): static
    {
        $this->inverseAssociations->removeElement($inverseAssociation);

        return $this;
    }

    /**
     * @return Collection<array-key, LsAssociation>
     */
    public function getInverseAssociations(): Collection
    {
        return $this->inverseAssociations;
    }

    public function getTopItemOf(): Collection
    {
        $topItemOf = new ArrayCollection();

        $associations = $this->getAssociations();
        foreach ($associations as $association) {
            /** @var LsAssociation $association */
            if (LsAssociation::CHILD_OF === $association->getType()
                && null !== $association->getDestinationLsDoc()
            ) {
                /** @psalm-suppress InvalidArgument */
                $topItemOf->add($association->getDestinationLsDoc());
            }
        }

        return $topItemOf;
    }

    public function getParentItem(): ?LsItem
    {
        $first = $this->getLsItemParent()->first();
        if ($first) {
            return $first;
        }

        return null;
    }

    public function getLsDocIdentifier(): ?string
    {
        return $this->lsDocIdentifier;
    }

    public function setLsDocIdentifier(?string $lsDocIdentifier): static
    {
        $this->lsDocIdentifier = $lsDocIdentifier;

        return $this;
    }

    /**
     * @throws \UnexpectedValueException
     */
    public function addParent(LsItem|LsDoc $parent, ?int $sequenceNumber = null, ?LsDefAssociationGrouping $assocGroup = null): LsAssociation
    {
        $association = new LsAssociation();
        $association->setLsDoc($this->getLsDoc());
        $association->setOrigin($this);
        $association->setType(LsAssociation::CHILD_OF);
        $association->setDestination($parent);

        // set sequenceNumber if provided
        if (null !== $sequenceNumber) {
            $association->setSequenceNumber($sequenceNumber);
        }

        // set assocGroup if provided
        if (null !== $assocGroup) {
            $association->setGroup($assocGroup);
        }

        $this->addAssociation($association);
        $parent->addInverseAssociation($association);

        return $association;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Get (an indented) label representing this item.
     */
    public function getLabel(string $indent = "\u{00a0}\u{00a0}\u{00a0}\u{00a0}"): string
    {
        $pfx = '';
        $parent = $this->getLsItemParent();
        while (!$parent->isEmpty()) {
            $pfx .= $indent;
            $parent = $parent->current()->getLsItemParent();
        }
        $statement = $this->getShortStatement();
        $code = $this->getHumanCodingScheme();
        if (null !== $code && '' !== $code) {
            $code .= ' - ';
        }

        return "{$pfx}{$code}{$statement}";
    }

    /**
     * Determine if the LsItem is editable.
     */
    public function canEdit(): bool
    {
        return $this->lsDoc->canEdit();
    }

    public function getItemType(): ?LsDefItemType
    {
        return $this->itemType;
    }

    public function setItemType(?LsDefItemType $itemType): static
    {
        $this->itemType = $itemType;

        return $this;
    }

    public function getItemTypeText(): ?string
    {
        return $this->itemTypeText;
    }

    public function setItemTypeText(?string $itemTypeText): static
    {
        $this->itemTypeText = $itemTypeText;

        return $this;
    }

    /**
     * @return Collection<array-key, LsDefConcept>
     */
    public function getConcepts()
    {
        return $this->concepts;
    }

    /**
     * @psalm-param ?iterable<array-key, LsDefConcept> $concepts
     */
    public function setConcepts(?iterable $concepts): static
    {
        $this->concepts = new ArrayCollection();

        if (null === $concepts) {
            return $this;
        }

        foreach ($concepts as $concept) {
            $this->addConcept($concept);
        }

        return $this;
    }

    public function addConcept(LsDefConcept $concept): static
    {
        $this->concepts[] = $concept;

        return $this;
    }

    public function getAlternativeLabel(): ?string
    {
        return $this->alternativeLabel;
    }

    public function setAlternativeLabel(?string $alternativeLabel): static
    {
        $this->alternativeLabel = $alternativeLabel;

        return $this;
    }

    public function getStatusStart(): ?\DateTimeInterface
    {
        if (null === $this->statusStart) {
            return $this->lsDoc->getStatusStart();
        }

        return $this->statusStart;
    }

    public function setStatusStart(?\DateTimeInterface $statusStart): static
    {
        $this->statusStart = $statusStart;

        return $this;
    }

    public function getStatusEnd(): ?\DateTimeInterface
    {
        if (null === $this->statusEnd) {
            return $this->lsDoc->getStatusEnd();
        }

        return $this->statusEnd;
    }

    public function setStatusEnd(?\DateTimeInterface $statusEnd): static
    {
        $this->statusEnd = $statusEnd;

        return $this;
    }

    public function getLicence(): ?LsDefLicence
    {
        return $this->licence;
    }

    public function setLicence(?LsDefLicence $licence): static
    {
        $this->licence = $licence;

        return $this;
    }

    /**
     * @return Collection<array-key, CfRubricCriterion>
     */
    public function getCriteria(): Collection
    {
        return $this->criteria;
    }

    public function addCriterion(CfRubricCriterion $criterion): static
    {
        $this->criteria[] = $criterion;

        return $this;
    }

    /**
     * @psalm-param ?iterable<array-key, CfRubricCriterion> $criteria
     */
    public function setCriteria(?iterable $criteria): static
    {
        $this->criteria = new ArrayCollection();

        if (null === $criteria) {
            return $this;
        }

        foreach ($criteria as $criterion) {
            $this->addCriterion($criterion);
        }

        return $this;
    }
}
