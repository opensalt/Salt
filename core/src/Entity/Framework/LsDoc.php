<?php

namespace App\Entity\Framework;

use App\Entity\Framework\Mirror\Framework;
use App\Entity\LockableInterface;
use App\Entity\User\Organization;
use App\Entity\User\User;
use App\Entity\User\UserDocAcl;
use App\Repository\Framework\LsDocRepository;
use App\Util\Compare;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'ls_doc')]
#[ORM\Entity(repositoryClass: LsDocRepository::class)]
#[UniqueEntity('uri')]
#[UniqueEntity('urlName')]
#[UniqueEntity('identifier')]
class LsDoc extends AbstractLsBase implements CaseApiInterface, LockableInterface
{
    final public const string ADOPTION_STATUS_PRIVATE_DRAFT = 'Private Draft';
    final public const string ADOPTION_STATUS_DRAFT = 'Draft';
    final public const string ADOPTION_STATUS_ADOPTED = 'Adopted';
    final public const string ADOPTION_STATUS_DEPRECATED = 'Deprecated';

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'frameworks')]
    #[ORM\JoinColumn(name: 'org_id', referencedColumnName: 'id', nullable: true)]
    #[Assert\Type(Organization::class)]
    protected ?Organization $org = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'frameworks')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[Assert\Type(User::class)]
    protected ?User $user = null;

    #[ORM\Column(name: 'official_uri', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    #[Assert\Url(requireTld: true)]
    private ?string $officialUri = null;

    #[ORM\Column(name: 'creator', type: 'string', length: 300, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 300)]
    private ?string $creator = null;

    #[ORM\Column(name: 'publisher', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $publisher = null;

    #[ORM\Column(name: 'title', type: 'string', length: 120, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $title = null;

    #[ORM\Column(name: 'url_name', type: 'string', length: 255, unique: true, nullable: true)]
    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'The URL Name cannot be a number.', match: false)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9.-]+$/', message: 'The URL Name can only use alpha-numeric characters plus a period (.) or dash (-).')]
    private ?string $urlName = null;

    #[ORM\Column(name: 'version', type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $version = null;

    #[ORM\Column(name: 'description', type: 'string', length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $description = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(name: 'subject', type: 'json', nullable: true)]
    #[Assert\All([new Assert\Type('string')])]
    private ?array $subject = [];

    /**
     * @var Collection<array-key, LsDefSubject>
     */
    #[ORM\ManyToMany(targetEntity: LsDefSubject::class)]
    #[ORM\JoinTable(name: 'ls_doc_subject')]
    #[ORM\JoinColumn(name: 'ls_doc_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'subject_id', referencedColumnName: 'id')]
    #[Assert\All([new Assert\Type(LsDefSubject::class)])]
    private Collection $subjects;

    #[ORM\Column(name: 'language', type: 'string', length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $language = null;

    #[ORM\Column(name: 'adoption_status', type: 'string', length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(callback: 'getStatuses')]
    private ?string $adoptionStatus = null;

    #[ORM\Column(name: 'status_start', type: 'date', nullable: true)]
    private ?\DateTimeInterface $statusStart = null;

    #[ORM\Column(name: 'status_end', type: 'date', nullable: true)]
    private ?\DateTimeInterface $statusEnd = null;

    #[ORM\ManyToOne(targetEntity: LsDefLicence::class)]
    #[ORM\JoinColumn(name: 'licence_id', referencedColumnName: 'id', nullable: true)]
    private ?LsDefLicence $licence = null;

    #[ORM\ManyToOne(targetEntity: FrameworkType::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'frameworktype_id', referencedColumnName: 'id', nullable: true)]
    private ?FrameworkType $frameworkType = null;

    #[ORM\Column(name: 'note', type: 'text', nullable: true)]
    private ?string $note = null;

    /**
     * @var Collection<array-key, LsItem>
     */
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: LsItem::class, fetch: 'EXTRA_LAZY', indexBy: 'id')]
    #[Assert\All([new Assert\Type(LsItem::class)])]
    private Collection $lsItems;

    /**
     * @var Collection<array-key, LsAssociation>
     */
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: LsAssociation::class, fetch: 'EXTRA_LAZY', indexBy: 'id')]
    #[Assert\All([new Assert\Type(LsAssociation::class)])]
    private Collection $docAssociations;

    /**
     * @var Collection<array-key, LsAssociation>
     */
    #[Assert\All([new Assert\Type(LsAssociation::class)])]
    #[ORM\OneToMany(mappedBy: 'originLsDoc', targetEntity: LsAssociation::class, cascade: ['persist'], indexBy: 'id')]
    private Collection $associations;

    /**
     * @var Collection<array-key, LsAssociation>
     */
    #[Assert\All([new Assert\Type(LsAssociation::class)])]
    #[ORM\OneToMany(mappedBy: 'destinationLsDoc', targetEntity: LsAssociation::class, cascade: ['persist'], indexBy: 'id')]
    private Collection $inverseAssociations;

    /**
     * @var Collection<array-key, LsDocAttribute>
     */
    #[Assert\All([new Assert\Type(LsDocAttribute::class)])]
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: LsDocAttribute::class, cascade: ['ALL'], orphanRemoval: true, indexBy: 'attribute')]
    private Collection $attributes;

    /**
     * @var Collection<array-key, UserDocAcl>
     */
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: UserDocAcl::class, fetch: 'EXTRA_LAZY', indexBy: 'user')]
    #[Assert\All([new Assert\Type(UserDocAcl::class)])]
    protected Collection $docAcls;

    /**
     * @var Collection<array-key, ImportLog>
     */
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: ImportLog::class, fetch: 'EXTRA_LAZY', indexBy: 'lsDoc')]
    #[Assert\All([new Assert\Type(ImportLog::class)])]
    protected Collection $importLogs;

    /**
     * @var Collection<array-key, LsDefAssociationGrouping>
     */
    #[ORM\OneToMany(mappedBy: 'lsDoc', targetEntity: LsDefAssociationGrouping::class, fetch: 'EXTRA_LAZY', indexBy: 'id')]
    #[Assert\All([new Assert\Type(LsDefAssociationGrouping::class)])]
    protected Collection $associationGroupings;

    #[Assert\Choice(['organization', 'user'])]
    protected ?string $ownedBy = null;

    #[ORM\OneToOne(inversedBy: 'framework', targetEntity: Framework::class)]
    private ?Framework $mirroredFramework = null;

    public function __construct(UuidInterface|string|null $identifier = null)
    {
        parent::__construct($identifier);

        $this->lsItems = new ArrayCollection();
        $this->docAssociations = new ArrayCollection();
        $this->associations = new ArrayCollection();
        $this->inverseAssociations = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->subjects = new ArrayCollection();
        $this->docAcls = new ArrayCollection();
        $this->importLogs = new ArrayCollection();
        $this->associationGroupings = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getUri();
    }

    /**
     * Get the list of Adoption Statuses.
     */
    public static function getStatuses(): array
    {
        return [
            static::ADOPTION_STATUS_PRIVATE_DRAFT,
            static::ADOPTION_STATUS_DRAFT,
            static::ADOPTION_STATUS_ADOPTED,
            static::ADOPTION_STATUS_DEPRECATED,
        ];
    }

    /**
     * Get the list of Adoption Statuses where editing is allowed.
     */
    public static function getEditableStatuses(): array
    {
        return [
            static::ADOPTION_STATUS_PRIVATE_DRAFT,
            static::ADOPTION_STATUS_DRAFT,
        ];
    }

    public function isDraft(): bool
    {
        if (null === $this->adoptionStatus || '' === $this->adoptionStatus) {
            return true;
        }

        return in_array($this->adoptionStatus, static::getEditableStatuses(), true);
    }

    public function isAdopted(): bool
    {
        return $this->adoptionStatus === static::ADOPTION_STATUS_ADOPTED;
    }

    public function isDeprecated(): bool
    {
        return $this->adoptionStatus === static::ADOPTION_STATUS_DEPRECATED;
    }

    public function setOfficialUri(?string $officialUri): static
    {
        $this->officialUri = $officialUri;

        return $this;
    }

    public function getOfficialUri(): ?string
    {
        return $this->officialUri;
    }

    public function setCreator(?string $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getCreator(): ?string
    {
        return $this->creator;
    }

    public function setPublisher(?string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setTitle(string $title): static
    {
        $this->title = mb_substr($title, 0, 120);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getShortStatement(): string
    {
        return mb_substr($this->title ?? 'Unknown', 0, 60);
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setDescription(?string $description): static
    {
        if (null === $description) {
            $this->description = null;

            return $this;
        }

        $this->description = mb_substr($description, 0, 300);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|string[]|null $subject
     */
    public function setSubject(array|string|null $subject): static
    {
        if (null === $subject) {
            $this->subject = null;

            return $this;
        }

        if (!is_array($subject)) {
            $subject = [$subject];
        }

        if (array_reduce($subject, static fn ($carry, $el): bool => $carry || !\is_string($el), false)) {
            throw new \InvalidArgumentException('setSubject must be passed an array of strings.');
        }

        $this->subject = $subject;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getSubject(): ?array
    {
        return $this->subject;
    }

    public function setAdoptionStatus(?string $adoptionStatus, ?string $default = null): static
    {
        if (null === $adoptionStatus && null === $default) {
            $this->adoptionStatus = null;

            return $this;
        }

        if (null === $adoptionStatus) {
            return $this->setAdoptionStatus($default);
        }

        // Check that adoptionStatus is valid
        foreach (static::getStatuses() as $validStatus) {
            if (strtolower($adoptionStatus) === strtolower($validStatus)) {
                $this->adoptionStatus = $validStatus;

                return $this;
            }
        }

        $this->adoptionStatus = $adoptionStatus;

        return $this;
    }

    public function getAdoptionStatus(): ?string
    {
        return $this->adoptionStatus;
    }

    public function setStatusStart(?\DateTimeInterface $statusStart): static
    {
        $this->statusStart = $statusStart;

        return $this;
    }

    public function getStatusStart(): ?\DateTimeInterface
    {
        return $this->statusStart;
    }

    public function setStatusEnd(?\DateTimeInterface $statusEnd): static
    {
        $this->statusEnd = $statusEnd;

        return $this;
    }

    public function getStatusEnd(): ?\DateTimeInterface
    {
        return $this->statusEnd;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function createChildItem(LsItem $topLsItem, ?LsDefAssociationGrouping $assocGroup = null, ?int $sequenceNumber = null): LsAssociation
    {
        $association = new LsAssociation();
        $association->setLsDoc($this);
        $association->setOriginLsItem($topLsItem);
        $association->setType(LsAssociation::CHILD_OF);
        $association->setDestinationLsDoc($this);
        if (null !== $sequenceNumber) {
            $association->setSequenceNumber($sequenceNumber);
        }

        if (null !== $assocGroup) {
            $association->setGroup($assocGroup);
        }

        $topLsItem->addAssociation($association);
        $this->addInverseAssociation($association);

        return $association;
    }

    public function addTopLsItem(LsItem $topLsItem, ?LsDefAssociationGrouping $assocGroup = null, ?int $sequenceNumber = null): static
    {
        $this->createChildItem($topLsItem, $assocGroup, $sequenceNumber);

        return $this;
    }

    /**
     * @return Collection<array-key, LsItem>
     */
    public function getTopLsItems(): Collection
    {
        $topAssociations = [];

        $associations = $this->getInverseAssociations();
        foreach ($associations as $association) {
            /** @var LsAssociation $association */
            if (null === $association->getLsDoc() || null === $association->getOriginLsItem()) {
                continue;
            }

            if ($association->getLsDoc()->getId() === $this->getId()) {
                if (LsAssociation::CHILD_OF === $association->getType()) {
                    $topAssociations[] = [
                        'sequenceNumber' => $association->getSequenceNumber(),
                        'enum' => $association->getOriginLsItem()->getListEnumInSource(),
                        'hcs' => $association->getOriginLsItem()->getHumanCodingScheme(),
                        'item' => $association->getOriginLsItem(),
                    ];
                }
            }
        }

        Compare::sortArrayByFields($topAssociations, ['sequenceNumber', 'enum', 'hcs']);

        $orderedList = array_map(static fn ($rec): LsItem => $rec['item'], $topAssociations);

        return new ArrayCollection($orderedList);
    }

    public function getTopLsItemIds(): array
    {
        return $this->getTopLsItems()->map(static fn (LsItem $item): ?int => $item->getId())->toArray();
    }

    public function addLsItem(LsItem $lsItem): static
    {
        $this->lsItems[] = $lsItem;

        return $this;
    }

    public function removeLsItem(LsItem $lsItem): void
    {
        $this->lsItems->removeElement($lsItem);
    }

    /**
     * @return Collection<array-key, LsItem>
     */
    public function getLsItems(): Collection
    {
        return $this->lsItems;
    }

    public function addAssociation(LsAssociation $association): static
    {
        $this->associations[] = $association;

        return $this;
    }

    public function removeAssociation(LsAssociation $association): void
    {
        $this->associations->removeElement($association);
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

    public function removeInverseAssociation(LsAssociation $inverseAssociation): void
    {
        $this->inverseAssociations->removeElement($inverseAssociation);
    }

    public function getInverseAssociations(): Collection
    {
        return $this->inverseAssociations;
    }

    public function addDocAssociation(LsAssociation $docAssociation): static
    {
        $this->docAssociations[] = $docAssociation;

        return $this;
    }

    public function removeDocAssociation(LsAssociation $docAssociation): void
    {
        $this->docAssociations->removeElement($docAssociation);
    }

    /**
     * @return Collection<array-key, LsAssociation>
     */
    public function getDocAssociations(): Collection
    {
        return $this->docAssociations;
    }

    /**
     * Add a document attribute.
     */
    public function setAttribute(string $name, ?string $value): static
    {
        // if attribute already exists, update it
        if ($this->attributes->containsKey($name)) {
            /* @noinspection NullPointerExceptionInspection -- containsKey guarantees the key exists */
            $this->attributes->get($name)->setValue($value);
        } else {
            $this->attributes->set($name, new LsDocAttribute($this, $name, $value));
        }

        return $this;
    }

    public function removeAttribute(string $name): static
    {
        // TODO (PW): does this really remove the item? I did add "orphanRemoval=true" to the attributes field above
        $this->attributes->remove($name);

        return $this;
    }

    public function getAttribute(string $name): ?string
    {
        if ($this->attributes->containsKey($name)) {
            /* @noinspection NullPointerExceptionInspection -- containsKey guarantees the key exists */
            return $this->attributes->get($name)->getValue();
        }

        return null;
    }

    public function isMirrored(): bool
    {
        return true === $this->getMirroredFramework()?->isInclude();
    }

    public function getMirroredFramework(): ?Framework
    {
        return $this->mirroredFramework;
    }

    public function setMirroredFramework(?Framework $mirroredFramework): static
    {
        $this->mirroredFramework = $mirroredFramework;

        return $this;
    }

    /**
     * Use attributes fields to save the identifiers, urls, and titles of a list of associated documents on different servers
     * Note that this fn is protected; addExternalDoc and removeExternalDoc are the public functions.
     */
    protected function setExternalDocs(array $externalDocs): static
    {
        // save all ed's passed in
        $i = 0;
        foreach ($externalDocs as $identifier => $ad) {
            $this->setAttribute("externalDoc$i", $identifier.'|'.$ad['autoLoad'].'|'.$ad['url'].'|'.$ad['title']);
            // title may get cut off if it's very long, but that's OK.
            ++$i;
        }

        // remove any remaining, now-extraneous ed's
        do {
            if ($this->attributes->containsKey("externalDoc$i")) {
                $this->removeAttribute("externalDoc$i");
            }
            ++$i;
        } while ($i < 1000);    // we should always break, but include this as a safety valve

        return $this;
    }

    /**
     * Add an associated doc.
     *
     * @param string $identifier
     * @param string $autoLoad   - "true" or "false"
     * @param string $url
     * @param string $title
     */
    public function addExternalDoc($identifier, $autoLoad, $url, $title): bool
    {
        if (empty($identifier) || empty($autoLoad) || empty($url) || empty($title)) {
            return false;
        }

        // get the doc's existing externalDocs; if this new doc isn't already there, add it
        $externalDocs = $this->getExternalDocs();
        $externalDocs[$identifier] = [
            'autoLoad' => $autoLoad,
            'url' => $url,
            'title' => $title,
        ];
        $this->setExternalDocs($externalDocs);

        return true;
    }

    public function setExternalDocAutoLoad($identifier, $autoLoad): void
    {
        $externalDocs = $this->getExternalDocs();
        if (empty($externalDocs[$identifier])) {
            return;
        }
        $externalDocs[$identifier]['autoLoad'] = $autoLoad;
        $this->setExternalDocs($externalDocs);
    }

    /**
     * Remove an associated doc.
     */
    public function removeExternalDoc($identifier): void
    {
        $externalDocs = $this->getExternalDocs();
        if (empty($externalDocs[$identifier])) {
            unset($externalDocs[$identifier]);
            $this->setExternalDocs($externalDocs);
        }
    }

    /**
     * Get the list of associated documents for this document.
     */
    public function getExternalDocs(): array
    {
        $externalDocs = [];

        $attrKeys = $this->attributes->getKeys();
        foreach ($attrKeys as $key) {
            if (str_starts_with($key, 'externalDoc')) {
                $ed = $this->getAttribute($key);

                if (null !== $ed && preg_match("/^(.+?)\|(true|false)\|(.+?)\|(.*)/", $ed, $matches)) {
                    $externalDocs[$matches[1]] = [
                        'autoLoad' => $matches[2],
                        'url' => $matches[3],
                        'title' => $matches[4],
                    ];
                }
            }
        }

        return $externalDocs;
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
     * Determine if the LsDoc is editable.
     */
    public function canEdit(): bool
    {
        return (null === $this->getMirroredFramework())
            && ((null === $this->adoptionStatus)
                || \in_array($this->adoptionStatus, static::getEditableStatuses(), true));
    }

    /**
     * @return Collection<array-key, LsDefSubject>
     */
    public function getSubjects(): Collection
    {
        return $this->subjects;
    }

    /**
     * @psalm-param ?iterable<array-key, LsDefSubject> $subjects
     */
    public function setSubjects(?iterable $subjects): static
    {
        $this->subjects = new ArrayCollection();

        if (null === $subjects) {
            return $this;
        }

        foreach ($subjects as $subject) {
            $this->addSubject($subject);
        }

        return $this;
    }

    public function addSubject(LsDefSubject $subject): static
    {
        $this->subjects[] = $subject;

        return $this;
    }

    /**
     * Get the organization owner for the framework.
     */
    public function getOrg(): ?Organization
    {
        return $this->org;
    }

    /**
     * Set the organization owner for the framework.
     */
    public function setOrg(?Organization $org = null): static
    {
        $this->org = $org;

        return $this;
    }

    /**
     * Get the user owner for the framework.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Set the user owner for the framework.
     */
    public function setUser(?User $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOwner(): User|Organization|null
    {
        /* @noinspection ProperNullCoalescingOperatorUsageInspection */
        return $this->org ?? $this->user;
    }

    /**
     * @return Collection<array-key, UserDocAcl>
     */
    public function getDocAcls(): Collection
    {
        return $this->docAcls;
    }

    /**
     * @return Collection<array-key, ImportLog>
     */
    public function getImportLogs(): Collection
    {
        return $this->importLogs;
    }

    /**
     * Returns 'user' or 'organization' based on which value exists.
     */
    public function getOwnedBy(): ?string
    {
        if (!empty($this->ownedBy)) {
            return $this->ownedBy;
        }

        if ($this->getOrg()) {
            return 'organization';
        }

        if ($this->getUser()) {
            return 'user';
        }

        return null;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setOwnedBy(?string $ownedBy): static
    {
        if (!in_array($ownedBy, [null, 'organization', 'user'], true)) {
            throw new \InvalidArgumentException('Owner must be "organization" or "user" (or empty)');
        }

        $this->ownedBy = $ownedBy;

        return $this;
    }

    /**
     * @return Collection<array-key, LsDefAssociationGrouping>
     */
    public function getAssociationGroupings(): Collection
    {
        return $this->associationGroupings;
    }

    public function addAssociationGrouping(LsDefAssociationGrouping $associationGrouping): static
    {
        $this->associationGroupings[] = $associationGrouping;

        return $this;
    }

    public function getUrlName(): ?string
    {
        return $this->urlName;
    }

    public function setUrlName(?string $urlName = null): static
    {
        $this->urlName = $urlName;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->getUrlName() ?? (string) $this->getId();
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

    public function getFrameworkType(): ?FrameworkType
    {
        return $this->frameworkType;
    }

    public function setFrameworkType(?FrameworkType $frameworkType): static
    {
        $this->frameworkType = $frameworkType;

        return $this;
    }

    public function createItem(UuidInterface|string|null $identifier = null): LsItem
    {
        $item = new LsItem($identifier);
        $item->setLsDoc($this);

        return $item;
    }

    public function createAssociation(UuidInterface|string|null $identifier = null): LsAssociation
    {
        $association = new LsAssociation($identifier);
        $association->setLsDoc($this);

        return $association;
    }
}
