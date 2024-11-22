<?php

namespace App\Entity\Framework\Mirror;

use App\Repository\Framework\Mirror\ServerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Table(name: 'mirror_server')]
#[ORM\Entity(repositoryClass: ServerRepository::class)]
class Server
{
    final public const string TYPE_CASE_1_0 = 'CASE/1.0';
    final public const string TYPE_DIRECT = 'Direct URI/0.0';

    final public const string STATUS_ACTIVE = 'active';
    final public const string STATUS_SUSPENDED = 'suspended';

    final public const string URL_CASE_1_0_LIST = '/ims/case/v1p0/CFDocuments';
    final public const string URL_CASE_1_0_PACKAGE = '/ims/case/v1p0/CFPackages';

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'url', type: 'string', nullable: false)]
    private string $url;

    #[ORM\Column(name: 'api_type', type: 'string', nullable: false)]
    private string $serverType = self::TYPE_CASE_1_0;

    #[ORM\Column(name: 'check_server', type: 'boolean', nullable: false)]
    private bool $checkServer = true;

    #[ORM\Column(name: 'add_found', type: 'boolean', nullable: false)]
    private bool $addFoundFrameworks;

    #[ORM\ManyToOne(targetEntity: OAuthCredential::class)]
    private ?OAuthCredential $credentials;

    #[ORM\Column(name: 'priority', type: 'integer', options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(name: 'next_check', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextCheck = null;

    #[ORM\Column(name: 'last_check', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastCheck = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', precision: 6)]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTimeInterface $updatedAt;

    /**
     * @var Collection<array-key, Framework>
     */
    #[ORM\OneToMany(mappedBy: 'server', targetEntity: Framework::class)]
    private Collection $frameworks;

    #[ORM\Column(length: 255)]
    private string $status = self::STATUS_ACTIVE;

    public function __construct(string $hostname, bool $addFoundFrameworks, ?OAuthCredential $credentials = null)
    {
        $this->url = $hostname;
        $this->addFoundFrameworks = $addFoundFrameworks;
        $this->credentials = $credentials;
        $this->frameworks = new ArrayCollection();
    }

    public static function makeSingleFrameworkServer(string $hostname, ?OAuthCredential $credentials = null): self
    {
        $server = new self($hostname, false, $credentials);
        $server->setCheckServer(false);

        return $server;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getServerType(): string
    {
        return $this->serverType;
    }

    public function setServerType(string $serverType): void
    {
        $this->serverType = $serverType;
    }

    public function isAddFoundFrameworks(): bool
    {
        return $this->addFoundFrameworks;
    }

    public function setAddFoundFrameworks(bool $addFoundFrameworks): void
    {
        $this->addFoundFrameworks = $addFoundFrameworks;
    }

    public function getCredentials(): ?OAuthCredential
    {
        return $this->credentials;
    }

    public function setCredentials(?OAuthCredential $credentials): void
    {
        $this->credentials = $credentials;
    }

    public function getNextCheck(): ?\DateTimeInterface
    {
        return $this->nextCheck;
    }

    public function setNextCheck(?\DateTimeInterface $nextCheck): void
    {
        $this->nextCheck = $nextCheck;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return Collection<array-key, Framework>
     */
    public function getFrameworks(): Collection
    {
        return $this->frameworks;
    }

    public function isCheckServer(): bool
    {
        return $this->checkServer;
    }

    public function setCheckServer(bool $checkServer): void
    {
        $this->checkServer = $checkServer;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getLastCheck(): ?\DateTimeInterface
    {
        return $this->lastCheck;
    }

    public function setLastCheck(\DateTimeInterface $lastCheck): void
    {
        $this->lastCheck = $lastCheck;
    }

    public function scheduleNextCheck(?\DateTimeInterface $when = null, int $in = 604800, ?int $variance = null): void
    {
        if (null === $variance) {
            $variance = (int) ($in / 2);
        }

        if (null === $when) {
            $when = new \DateTimeImmutable();
        }

        $ts = $when->getTimestamp() + $in;

        // Max +/- 1 week
        $halfVariance = min(604800, (int) ($variance / 2));

        // Check again (default in 18 to 30 hours for first check)
        $nextCheck = new \DateTimeImmutable('@'.random_int($ts - $halfVariance, $ts + $halfVariance));
        $this->nextCheck = $nextCheck;

        $this->priority = 0;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
