<?php

namespace App\Entity\Framework\Mirror;

use App\Entity\Framework\LsDoc;
use App\Repository\Framework\Mirror\FrameworkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Swaggest\JsonDiff\JsonDiff;

#[ORM\Table(name: 'mirror_framework')]
#[ORM\Entity(repositoryClass: FrameworkRepository::class)]
class Framework
{
    final public const string STATUS_NEW = 'new';
    final public const string STATUS_SCHEDULED = 'scheduled';
    final public const string STATUS_PROCESSING = 'processing';
    final public const string STATUS_OK = 'ok';
    final public const string STATUS_ERROR = 'error';
    final public const string STATUS_SUSPENDED = 'suspended';

    final public const string ERROR_GENERAL = 'general';
    final public const string ERROR_ID_CONFLICT = 'identifier_conflict';

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class, inversedBy: 'frameworks')]
    #[ORM\JoinColumn(nullable: false)]
    private Server $server;

    #[ORM\Column(name: 'url', type: 'string', nullable: false)]
    private string $url = '';

    #[ORM\Column(name: 'identifier', type: 'string', nullable: false)]
    private string $identifier;

    #[ORM\Column(name: 'creator', type: 'string', nullable: true)]
    private ?string $creator = null;

    #[ORM\Column(name: 'title', type: 'string', nullable: true)]
    private ?string $title = null;

    #[ORM\OneToOne(mappedBy: 'mirroredFramework', targetEntity: LsDoc::class)]
    private ?LsDoc $framework = null;

    #[ORM\Column(name: 'visible', type: 'boolean', nullable: false, options: ['default' => true])]
    private bool $visible = true;

    #[ORM\Column(name: 'include', type: 'boolean', nullable: false)]
    private bool $include = true;

    #[ORM\Column(name: 'priority', type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(name: 'status', type: 'string', options: ['default' => self::STATUS_NEW])]
    private string $status = self::STATUS_NEW;

    #[ORM\Column(name: 'status_count', type: 'integer', nullable: false, options: ['default' => 0])]
    private int $statusCount = 0;

    #[ORM\Column(name: 'last_check', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastCheck = null;

    #[ORM\Column(name: 'last_success', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSuccess = null;

    #[ORM\Column(name: 'last_failure', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastFailure = null;

    #[ORM\Column(name: 'last_change', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastChange = null;

    #[ORM\Column(name: 'next_check', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextCheck = null;

    #[ORM\Column(name: 'error_type', type: 'string', nullable: true)]
    private ?string $errorType = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', precision: 6)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    /**
     * @var string|resource|null
     */
    #[ORM\Column(name: 'last_content', type: 'blob', nullable: true)]
    private $lastContent;

    /**
     * @var string|resource|null
     */
    #[ORM\Column(name: 'last_success_content', type: 'blob', nullable: true)]
    private $lastSuccessContent;

    /**
     * @var Collection<array-key, Log>
     */
    #[ORM\OneToMany(mappedBy: 'mirror', targetEntity: Log::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $logs;

    public function __construct(Server $server, string $identifier)
    {
        $this->server = $server;
        $this->identifier = $identifier;
        $this->logs = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getServer(): Server
    {
        return $this->server;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function isInclude(): bool
    {
        return $this->include;
    }

    public function setInclude(bool $include): static
    {
        $this->include = $include;

        return $this;
    }

    public function getStatusCount(): int
    {
        return $this->statusCount;
    }

    public function getFramework(): ?LsDoc
    {
        return $this->framework;
    }

    public function setFramework(?LsDoc $framework): static
    {
        $this->framework = $framework;

        return $this;
    }

    public function getLastCheck(): ?\DateTimeInterface
    {
        return $this->lastCheck;
    }

    public function getLastChange(): ?\DateTimeInterface
    {
        return $this->lastChange;
    }

    public function getNextCheck(): ?\DateTimeInterface
    {
        return $this->nextCheck;
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function isIdConflicted(): bool
    {
        return static::STATUS_ERROR === $this->status && static::ERROR_ID_CONFLICT === $this->errorType;
    }

    public function setErrorType(?string $errorType): static
    {
        $this->errorType = $errorType;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getTitle(): string
    {
        return $this->title ?? 'Unknown';
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCreator(): string
    {
        return $this->creator ?? 'Unknown';
    }

    public function setCreator(string $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getLastSuccessContent(): ?string
    {
        if (null === $this->lastSuccessContent) {
            return null;
        }

        $uncompressed = false;

        if (is_string($this->lastSuccessContent)) {
            $uncompressed = bzdecompress($this->lastSuccessContent);
        }

        if (is_resource($this->lastSuccessContent)) {
            $uncompressed = bzdecompress(stream_get_contents($this->lastSuccessContent));
        }

        if (is_int($uncompressed) || false === $uncompressed) {
            return null;
        }

        return $uncompressed;
    }

    public function setLastSuccessContent(?string $lastSuccessContent): static
    {
        if (null === $lastSuccessContent) {
            $this->lastSuccessContent = null;
            $this->lastContent = null;

            return $this;
        }

        $this->lastSuccessContent = bzcompress($lastSuccessContent, 9);
        $this->lastContent = $this->lastSuccessContent;

        return $this;
    }

    public function getLastContent(): ?string
    {
        if (null === $this->lastContent) {
            return null;
        }

        $uncompressed = false;

        if (is_string($this->lastContent)) {
            $uncompressed = bzdecompress($this->lastContent);
        }

        if (is_resource($this->lastContent)) {
            $uncompressed = bzdecompress(stream_get_contents($this->lastContent));
        }

        if (is_int($uncompressed) || false === $uncompressed) {
            return null;
        }

        return $uncompressed;
    }

    public function setLastContent(?string $lastContent): static
    {
        if (null === $lastContent) {
            $this->lastContent = null;

            return $this;
        }

        $this->lastContent = bzcompress($lastContent, 9);

        return $this;
    }

    /**
     * @return Collection<array-key, Log>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(string $status, string $message): Log
    {
        $log = new Log($this, $status, $message);
        $this->logs[] = $log;

        return $log;
    }

    public function clearLogs(): static
    {
        $this->logs->clear();

        return $this;
    }

    public function markToRefresh(): static
    {
        $this->nextCheck = new \DateTimeImmutable();
        $this->priority = 1000;
        $this->status = static::STATUS_SCHEDULED;

        return $this;
    }

    public function markSuccess(bool $changed = false): static
    {
        $this->errorType = null;
        $this->status = static::STATUS_OK;
        ++$this->statusCount;
        if ($changed || null === $this->lastSuccess || (null !== $this->lastFailure && $this->lastFailure > $this->lastSuccess)) {
            $this->statusCount = 1;
        }

        $this->lastSuccess = new \DateTimeImmutable();
        $this->lastCheck = $this->lastSuccess;
        $this->scheduleNextCheck($this->lastSuccess, (int) (86400 * 1.1855 ** min(20, $this->statusCount - 1)));

        if ($changed) {
            $this->lastChange = $this->lastSuccess;
        }

        return $this;
    }

    public function markFailure(?string $errorType = self::ERROR_GENERAL): static
    {
        $this->errorType = $errorType;
        $this->status = static::STATUS_ERROR;
        ++$this->statusCount;
        if (null === $this->lastFailure || (null !== $this->lastSuccess && $this->lastFailure < $this->lastSuccess)) {
            $this->statusCount = 1;
        }

        $this->lastFailure = new \DateTimeImmutable();
        $this->lastCheck = $this->lastFailure;
        $this->scheduleNextCheck($this->lastFailure, (int) (21600 * 1.5 ** min(12, $this->statusCount - 1)));

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function matchesLastSuccessContent(?string $framework): bool
    {
        try {
            $diff = new JsonDiff(
                json5_decode($this->getLastSuccessContent() ?? '{}'),
                json5_decode($framework ?? '{}'),
                JsonDiff::STOP_ON_DIFF + JsonDiff::REARRANGE_ARRAYS
            );
        } catch (\Exception) {
            return false;
        }

        return 0 === $diff->getDiffCnt();
    }

    public function hasLogs(): bool
    {
        return !$this->logs->isEmpty();
    }

    public function getLastFailure(): ?\DateTimeInterface
    {
        return $this->lastFailure;
    }

    private function scheduleNextCheck(\DateTimeInterface $when, int $in = 86400, ?int $variance = null): void
    {
        if (null === $variance) {
            $variance = (int) ($in / 2);
        }

        $ts = $when->getTimestamp() + $in;

        // Max +/- 1 week
        $halfVariance = min(604800, (int) ($variance / 2));

        // Check again (default in 18 to 30 hours for first check)
        $nextCheck = new \DateTimeImmutable('@'.random_int($ts - $halfVariance, $ts + $halfVariance));
        $this->nextCheck = $nextCheck;

        $this->priority = 0;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        $this->visible = $visible;
    }
}
