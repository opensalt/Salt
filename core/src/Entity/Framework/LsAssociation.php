<?php

namespace App\Entity\Framework;

use App\Repository\Framework\LsAssociationRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'ls_association')]
#[ORM\Index(columns: ['destination_node_identifier'], name: 'dest_id_idx')]
#[ORM\Index(columns: ['origin_node_identifier'], name: 'orig_id_idx')]
#[ORM\Entity(repositoryClass: LsAssociationRepository::class)]
class LsAssociation extends AbstractLsBase implements CaseApiInterface
{
    use AccessAdditionalFieldTrait;

    final public const CHILD_OF = 'Is Child Of';

    final public const EXACT_MATCH_OF = 'Exact Match Of';
    final public const RELATED_TO = 'Is Related To';
    final public const PART_OF = 'Is Part Of';
    final public const REPLACED_BY = 'Replaced By';
    final public const PRECEDES = 'Precedes';
    final public const SKILL_LEVEL = 'Has Skill Level';
    final public const IS_PEER_OF = 'Is Peer Of';

    final public const EXEMPLAR = 'Exemplar';

    final public const INVERSE_CHILD_OF = 'Is Parent Of';

    final public const INVERSE_EXACT_MATCH_OF = 'Matched From';
    final public const INVERSE_RELATED_TO = 'Related From';
    final public const INVERSE_PART_OF = 'Has Part';
    final public const INVERSE_REPLACED_BY = 'Replaces';
    final public const INVERSE_PRECEDES = 'Has Predecessor';
    final public const INVERSE_SKILL_LEVEL = 'Skill Level For';
    final public const INVERSE_IS_PEER_OF = 'Peer Of';

    final public const INVERSE_EXEMPLAR = 'Exemplar For';

    #[ORM\Column(name: 'ls_doc_identifier', type: 'string', length: 300, nullable: false)]
    #[Assert\Length(max: 300)]
    private ?string $lsDocIdentifier = null;

    #[ORM\Column(name: 'ls_doc_uri', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $lsDocUri = null;

    #[ORM\ManyToOne(targetEntity: LsDoc::class, inversedBy: 'docAssociations')]
    private ?LsDoc $lsDoc = null;

    #[ORM\ManyToOne(targetEntity: LsDefAssociationGrouping::class, fetch: 'EAGER')]
    #[ORM\JoinColumn(name: 'assoc_group_id', referencedColumnName: 'id')]
    private ?LsDefAssociationGrouping $group = null;

    #[ORM\Column(name: 'origin_node_identifier', type: 'string', length: 300, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private ?string $originNodeIdentifier = null;

    #[ORM\Column(name: 'origin_node_uri', type: 'string', length: 300, nullable: true)]
    private ?string $originNodeUri = null;

    #[ORM\ManyToOne(targetEntity: LsDoc::class, inversedBy: 'associations')]
    #[ORM\JoinColumn(name: 'origin_lsdoc_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?LsDoc $originLsDoc = null;

    #[ORM\ManyToOne(targetEntity: LsItem::class, cascade: ['persist'], inversedBy: 'associations')]
    #[ORM\JoinColumn(name: 'origin_lsitem_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?LsItem $originLsItem = null;

    #[ORM\Column(name: 'destination_node_identifier', type: 'string', length: 300, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private ?string $destinationNodeIdentifier = null;

    #[ORM\Column(name: 'destination_node_uri', type: 'string', length: 300, nullable: true)]
    private ?string $destinationNodeUri = null;

    #[ORM\ManyToOne(targetEntity: LsDoc::class, inversedBy: 'inverseAssociations')]
    #[ORM\JoinColumn(name: 'destination_lsdoc_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?LsDoc $destinationLsDoc = null;

    #[ORM\ManyToOne(targetEntity: LsItem::class, cascade: ['persist'], inversedBy: 'inverseAssociations')]
    #[ORM\JoinColumn(name: 'destination_lsitem_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?LsItem $destinationLsItem = null;

    #[ORM\Column(name: 'type', type: 'string', length: 50, nullable: false)]
    private ?string $type = null;

    #[ORM\Column(name: 'seq', type: 'bigint', nullable: true)]
    private ?string $sequenceNumber = null;

    #[ORM\Column(name: 'subtype', type: 'string', nullable: true)]
    private ?string $subtype = null;

    #[ORM\Column(name: 'annotation', type: 'text', length: 65534, nullable: true)]
    private ?string $annotation = null;

    public function __construct(UuidInterface|string|null $identifier = null)
    {
        parent::__construct($identifier);
    }

    public function __toString(): string
    {
        return $this->getUri();
    }

    /**
     * Get all types indexed by camel case.
     */
    public static function allTypesForImportFromCSV(): array
    {
        return [
            'isPartOf' => static::PART_OF,
            'exemplar' => static::EXEMPLAR,
            'isPeerOf' => static::IS_PEER_OF,
            'precedes' => static::PRECEDES,
            'isRelatedTo' => static::RELATED_TO,
            'replacedBy' => static::REPLACED_BY,
            'hasSkillLevel' => static::SKILL_LEVEL,
        ];
    }

    /**
     * Get an array of all association types available.
     */
    public static function allTypes(): array
    {
        return [
            static::RELATED_TO,
            static::EXACT_MATCH_OF,
            static::PART_OF,
            static::REPLACED_BY,
            static::PRECEDES,
            static::SKILL_LEVEL,
            static::IS_PEER_OF,
            static::EXEMPLAR,

            static::CHILD_OF,
        ];
    }

    /**
     * Get an array of association types that should show in the choice list.
     */
    public static function typeChoiceList(): array
    {
        return [
            static::RELATED_TO,
            static::EXACT_MATCH_OF,
            static::PART_OF,
            static::REPLACED_BY,
            static::PRECEDES,
            static::SKILL_LEVEL,
            static::IS_PEER_OF,
        ];
    }

    public static function inverseName(string $name): ?string
    {
        static $inverses = [];
        if (!count($inverses)) {
            $inverses = [
                static::CHILD_OF => static::INVERSE_CHILD_OF,
                static::EXACT_MATCH_OF => static::INVERSE_EXACT_MATCH_OF,
                static::RELATED_TO => static::INVERSE_RELATED_TO,
                static::PART_OF => static::INVERSE_PART_OF,
                static::REPLACED_BY => static::INVERSE_REPLACED_BY,
                static::PRECEDES => static::INVERSE_PRECEDES,
                static::IS_PEER_OF => static::INVERSE_IS_PEER_OF,
                static::SKILL_LEVEL => static::INVERSE_SKILL_LEVEL,
                static::EXEMPLAR => static::INVERSE_EXEMPLAR,
                static::INVERSE_CHILD_OF => static::CHILD_OF,
                static::INVERSE_EXACT_MATCH_OF => static::EXACT_MATCH_OF,
                static::INVERSE_RELATED_TO => static::RELATED_TO,
                static::INVERSE_PART_OF => static::PART_OF,
                static::INVERSE_REPLACED_BY => static::REPLACED_BY,
                static::INVERSE_PRECEDES => static::PRECEDES,
                static::INVERSE_IS_PEER_OF => static::IS_PEER_OF,
                static::INVERSE_SKILL_LEVEL => static::SKILL_LEVEL,
                static::INVERSE_EXEMPLAR => static::EXEMPLAR,
            ];
        }

        if (array_key_exists($name, $inverses)) {
            return $inverses[$name];
        }

        return null;
    }

    /**
     * Set the Origination of the association.
     *
     * @throws \UnexpectedValueException
     */
    public function setOrigin(IdentifiableInterface|string $origin, ?string $identifier = null): static
    {
        if (is_string($origin)) {
            $this->setOriginNodeUri($origin);
            $this->setOriginNodeIdentifier($identifier ?? $origin);

            return $this;
        }

        if ($origin instanceof LsDoc) {
            $this->setOriginLsDoc($origin);
        } elseif ($origin instanceof LsItem) {
            $this->setOriginLsItem($origin);
        }
        $this->setOriginNodeUri($origin->getUri());
        $this->setOriginNodeIdentifier($identifier ?? $origin->getIdentifier());

        return $this;
    }

    /**
     * Get the Origination of the association.
     */
    public function getOrigin(): string|LsItem|LsDoc|null
    {
        if ($this->getOriginLsDoc()) {
            return $this->getOriginLsDoc();
        }

        if ($this->getOriginLsItem()) {
            return $this->getOriginLsItem();
        }

        if ($this->getOriginNodeUri()) {
            return $this->getOriginNodeUri();
        }

        return null;
    }

    public function setOriginNodeUri(string $originNodeUri): static
    {
        $this->originNodeUri = $originNodeUri;

        return $this;
    }

    public function getOriginNodeUri(): ?string
    {
        return $this->originNodeUri;
    }

    /**
     * Set the Destination of the association.
     *
     * @throws \UnexpectedValueException
     */
    public function setDestination(IdentifiableInterface|string $destination, ?string $identifier = null): static
    {
        if (is_string($destination)) {
            $this->setDestinationNodeUri($destination);
            $this->setDestinationNodeIdentifier($identifier ?? $destination);

            return $this;
        }

        if ($destination instanceof LsDoc) {
            $this->setDestinationLsDoc($destination);
        } elseif ($destination instanceof LsItem) {
            $this->setDestinationLsItem($destination);
        }
        $this->setDestinationNodeUri($destination->getUri());
        $this->setDestinationNodeIdentifier($identifier ?? $destination->getIdentifier());

        return $this;
    }

    /**
     * Get the Destination of the association.
     */
    public function getDestination(): string|LsItem|LsDoc|null
    {
        if ($this->getDestinationLsDoc()) {
            return $this->getDestinationLsDoc();
        }

        if ($this->getDestinationLsItem()) {
            return $this->getDestinationLsItem();
        }

        if ($this->getDestinationNodeUri()) {
            return $this->getDestinationNodeUri();
        }

        return null;
    }

    public function setDestinationNodeUri(string $destinationNodeUri): static
    {
        $this->destinationNodeUri = $destinationNodeUri;

        return $this;
    }

    public function getDestinationNodeUri(): ?string
    {
        return $this->destinationNodeUri;
    }

    /**
     * Get HumanCodingScheme from DestinationNodeUri.
     */
    public function getHumanCodingSchemeFromDestinationNodeUri(): ?string
    {
        return $this->splitDestinationDataUri()['value'];
    }

    /**
     * Get an array with the information from a data URI.
     */
    public function splitDestinationDataUri(): array
    {
        if (!str_starts_with($this->destinationNodeUri ?? '', 'data:text/x-')) {
            // Not a known data URI format, return the entire uri as the value
            return ['value' => $this->destinationNodeUri];
        }

        $uri = substr($this->destinationNodeUri, 12);
        [$dataString, $encodedValue] = array_pad(explode(',', $uri, 2), 2, null);

        [$textType, $metadataString] = array_pad(explode(';', $dataString, 2), 2, null);

        $metadata = ['textType' => $textType];
        foreach (explode(';', $metadataString) as $param) {
            [$name, $value] = array_pad(explode('=', $param, 2), 2, null);
            if (null !== $name && '' !== $name) {
                $metadata[$name] = $value ?? true;
            }
        }

        if ($metadata['base64'] ?? false) {
            $metadata['value'] = base64_decode($encodedValue);
        } else {
            $metadata['value'] = rawurldecode($encodedValue);
        }

        return $metadata;
    }

    public function setType(?string $type): static
    {
        if (in_array($type, self::allTypes(), true)) {
            $this->type = $type;

            return $this;
        }

        $newType = $this->coerceType($type);
        if (null !== $newType) {
            $this->type = $newType;

            return $this;
        }

        throw new \InvalidArgumentException('Invalid association type passed: '.$type);
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Return the normalized type (like "isChildOf" instead of "Is Child Of").
     */
    public function getNormalizedType(?string $type = null): string
    {
        return lcfirst(str_replace(' ', '', $type ?? $this->type ?? ''));
    }

    /**
     * Coerce a type string into the correct format for use with setType().
     */
    public function coerceType(?string $type): ?string
    {
        $allTypes = self::allTypes();
        $testNewType = preg_replace('/ +/', '', strtolower($type));
        foreach ($allTypes as $allowedType) {
            $testAllowedType = preg_replace('/ +/', '', strtolower($allowedType));
            if ($testNewType === $testAllowedType) {
                return $allowedType;
            }
        }

        return null;
    }

    public function setOriginLsDoc(?LsDoc $originLsDoc = null): static
    {
        $this->originLsDoc = $originLsDoc;

        if (null !== $originLsDoc) {
            $this->setOriginNodeUri($originLsDoc->getUri());
            $this->setOriginNodeIdentifier($originLsDoc->getIdentifier());
        }

        return $this;
    }

    public function getOriginLsDoc(): ?LsDoc
    {
        return $this->originLsDoc;
    }

    public function setOriginLsItem(?LsItem $originLsItem = null): static
    {
        $this->originLsItem = $originLsItem;

        if (null !== $originLsItem) {
            $this->setOriginNodeUri($originLsItem->getUri());
            $this->setOriginNodeIdentifier($originLsItem->getIdentifier());
        }

        return $this;
    }

    public function getOriginLsItem(): ?LsItem
    {
        return $this->originLsItem;
    }

    public function setDestinationLsDoc(?LsDoc $destinationLsDoc = null): static
    {
        $this->destinationLsDoc = $destinationLsDoc;
        if (null !== $destinationLsDoc) {
            $this->setDestinationNodeUri($destinationLsDoc->getUri());
            $this->setDestinationNodeIdentifier($destinationLsDoc->getIdentifier());
        }

        return $this;
    }

    public function getDestinationLsDoc(): ?LsDoc
    {
        return $this->destinationLsDoc;
    }

    public function setDestinationLsItem(?LsItem $destinationLsItem = null): static
    {
        $this->destinationLsItem = $destinationLsItem;
        if (null !== $destinationLsItem) {
            $this->setDestinationNodeUri($destinationLsItem->getUri());
            $this->setDestinationNodeIdentifier($destinationLsItem->getIdentifier());
        }

        return $this;
    }

    public function getDestinationLsItem(): ?LsItem
    {
        return $this->destinationLsItem;
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

    public function setLsDoc(LsDoc $lsDoc): static
    {
        $this->lsDoc = $lsDoc;
        $this->setLsDocUri($lsDoc->getUri());
        $this->setLsDocIdentifier($lsDoc->getIdentifier());

        return $this;
    }

    public function getLsDoc(): ?LsDoc
    {
        return $this->lsDoc;
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

    public function getOriginNodeIdentifier(): ?string
    {
        return $this->originNodeIdentifier;
    }

    public function setOriginNodeIdentifier(?string $originNodeIdentifier): static
    {
        $this->originNodeIdentifier = $originNodeIdentifier;

        return $this;
    }

    public function getDestinationNodeIdentifier(): ?string
    {
        return $this->destinationNodeIdentifier;
    }

    public function setDestinationNodeIdentifier(?string $destinationNodeIdentifier): static
    {
        $this->destinationNodeIdentifier = $destinationNodeIdentifier;

        return $this;
    }

    /**
     * Return true if the association is editable.
     */
    public function canEdit(): bool
    {
        return !(LsDoc::ADOPTION_STATUS_DEPRECATED === $this->lsDoc->getAdoptionStatus());
    }

    public function getSequenceNumber(): ?int
    {
        return (int) $this->sequenceNumber;
    }

    public function setSequenceNumber(string|int|null $sequenceNumber): static
    {
        if (null === $sequenceNumber) {
            $this->sequenceNumber = null;

            return $this;
        }

        $this->sequenceNumber = (string) $sequenceNumber;

        return $this;
    }

    public function getGroup(): ?LsDefAssociationGrouping
    {
        return $this->group;
    }

    public function setGroup(?LsDefAssociationGrouping $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function setSubtype(?string $subtype): static
    {
        $this->subtype = $subtype;

        return $this;
    }

    public function getAnnotation(): ?string
    {
        return $this->annotation;
    }

    public function setAnnotation(?string $annotation): static
    {
        $this->annotation = $annotation;

        return $this;
    }
}
