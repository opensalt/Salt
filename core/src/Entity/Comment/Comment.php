<?php

namespace App\Entity\Comment;

use App\Entity\Framework\LsDoc;
use App\Entity\Framework\LsItem;
use App\Entity\User\User;
use App\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'salt_comment')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Comment $parent = null;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: LsDoc::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LsDoc $document = null;

    #[ORM\ManyToOne(targetEntity: LsItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LsItem $item = null;

    /**
     * @var Collection<array-key, CommentUpvote>
     */
    #[ORM\OneToMany(mappedBy: 'comment', targetEntity: CommentUpvote::class)]
    private Collection $upvotes;

    #[ORM\Column(type: 'datetime', precision: 6)]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', precision: 6)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fileUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $fileMimeType = null;

    private bool $createdByCurrentUser = false;

    private bool $userHasUpvoted = false;

    public function __construct()
    {
        $this->upvotes = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setParent(?Comment $parent = null): ?Comment
    {
        $this->parent = $parent;

        return $this;
    }

    public function getParent(): ?Comment
    {
        return $this->parent;
    }

    public function getParentId(): ?int
    {
        if (null !== $this->parent) {
            return $this->parent->getId();
        }

        return null;
    }

    public function setContent(string $content): Comment
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setUser(User $user): Comment
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setItem(LsItem $item): Comment
    {
        $this->item = $item;

        return $this;
    }

    public function getItem(): ?LsItem
    {
        return $this->item;
    }

    public function setDocument(LsDoc $document): Comment
    {
        $this->document = $document;

        return $this;
    }

    public function getDocument(): ?LsDoc
    {
        return $this->document;
    }

    public function getFullname(): string
    {
        return preg_replace('/@.*/', '', $this->getUser()->getUserIdentifier());
    }

    public function setCreatedByCurrentUser(bool $createdByCurrentUser): Comment
    {
        $this->createdByCurrentUser = $createdByCurrentUser;

        return $this;
    }

    public function updateStatusForUser(User $user): Comment
    {
        if ($this->getUser()->getId() === $user->getId()) {
            $this->setCreatedByCurrentUser(true);
        }

        foreach ($this->getUpvotes() as $upvote) {
            if ($upvote->getUser()->getId() === $user->getId()) {
                $this->setUserHasUpvoted(true);
                break;
            }
        }

        return $this;
    }

    public function isCreatedByCurrentUser(): bool
    {
        return $this->createdByCurrentUser;
    }

    public function addUpvote(CommentUpvote $upvote): Comment
    {
        $this->upvotes[] = $upvote;

        return $this;
    }

    public function removeUpvote(CommentUpvote $upvote): Comment
    {
        $this->upvotes->removeElement($upvote);

        return $this;
    }

    public function getUpvotes(): Collection
    {
        return $this->upvotes;
    }

    public function getUpvoteCount(): int
    {
        return $this->upvotes->count();
    }

    public function setUserHasUpvoted(bool $userHasUpvoted): Comment
    {
        $this->userHasUpvoted = $userHasUpvoted;

        return $this;
    }

    public function hasUserUpvoted(): bool
    {
        return $this->userHasUpvoted;
    }

    public function setCreatedAt(\DateTime $createdAt): Comment
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): Comment
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setFileUrl(?string $fileUrl): Comment
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileMimeType(?string $fileMimeType): Comment
    {
        $this->fileMimeType = $fileMimeType;

        return $this;
    }

    public function getFileMimeType(): ?string
    {
        return $this->fileMimeType;
    }
}
