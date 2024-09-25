<?php

namespace App\Entity\User;

use App\Entity\Framework\LsDoc;
use App\Repository\User\UserRepository;
use App\Validator\Constraints as CustomAssert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'salt_user')]
#[UniqueEntity('username', message: 'That email address is already being used', groups: ['registration'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface, TwoFactorInterface
{
    final public const USER_ROLES = [
        'ROLE_EDITOR',
        'ROLE_ADMIN',
        'ROLE_SUPER_EDITOR',
        'ROLE_SUPER_USER',
    ];

    final public const ACTIVE = 0;
    final public const SUSPENDED = 1;
    final public const PENDING = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: 'integer')]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'users')]
    #[ORM\JoinColumn(name: 'org_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotBlank]
    protected Organization $org;

    #[ORM\Column(name: 'username', type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank(groups: ['registration', 'Default'])]
    #[Assert\Email(groups: ['registration'])]
    protected string $username;

    #[CustomAssert\PasswordField(groups: ["registration"])]
    #[Assert\NotBlank(groups: ['registration'])]
    #[Assert\Length(min: 8, max: 4096, minMessage: 'Password must be at least {{ limit }} characters long', maxMessage: 'Password cannot be longer than {{ limit }} characters', groups: ['registration'])]
    private ?string $plainPassword = null;

    #[ORM\Column(name: 'password', type: 'string', nullable: true)]
    protected ?string $password = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(name: 'roles', type: 'json', nullable: true)]
    protected ?array $roles = [];

    #[ORM\Column(name: 'status', type: 'integer', nullable: false, options: ['default' => User::PENDING])]
    protected int $status = self::ACTIVE;

    #[ORM\Column(name: 'github_token', type: 'string', length: 40, nullable: true)]
    protected ?string $githubToken = null;

    #[ORM\Column(nullable: true)]
    protected ?string $totpSecret = null;

    #[ORM\Column(name: 'totp_enabled', nullable: true)]
    protected ?bool $isTotpEnabled = false;

    /**
     * @var Collection<array-key, LsDoc>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LsDoc::class, fetch: 'EXTRA_LAZY', indexBy: 'id')]
    protected Collection $frameworks;

    /**
     * @var Collection<array-key, UserDocAcl>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserDocAcl::class, fetch: 'EXTRA_LAZY', indexBy: 'lsDoc')]
    protected Collection $docAcls;

    public function __construct(?string $username = null)
    {
        if (!empty($username)) {
            $this->username = $username;
        }

        $this->frameworks = new ArrayCollection();
    }

    public static function getUserRoles(): array
    {
        return static::USER_ROLES;
    }

    /**
     * Returns the internal id of the user.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @deprecated As of Symfony 5.3 getUserIdentifier() should be used instead
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $password): void
    {
        $this->plainPassword = $password;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * This should be the encoded password. On authentication, a plain-text
     * password will be salted, encoded, and then compared to this value.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    /**
     * Returns the roles granted to the user.
     *
     * @return string[] The user roles
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }

    /**
     * @param iterable<array-key, string> $roles The user roles
     */
    public function setRoles(iterable $roles): void
    {
        $this->roles = [];
        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    public function getGithubToken(): ?string
    {
        return $this->githubToken;
    }

    public function setGithubToken(?string $token): void
    {
        $this->githubToken = $token;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @deprecated This function has been deprecated and can be removed with Symfony 6.0
     */
    public function getSalt(): ?string
    {
        return null;
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        return [
            $this->id,
            $this->username,
        ];
    }

    public function __unserialize(array $data): void
    {
        [$this->id, $this->username] = $data;
    }

    /**
     * The equality comparison should neither be done by referential equality
     * nor by comparing identities (i.e. getId() === getId()).
     *
     * However, you do not need to compare every attribute, but only those that
     * are relevant for assessing whether re-authentication is required.
     *
     * Also implementation should consider that $user instance may implement
     * the extended user interface `AdvancedUserInterface`.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!($user instanceof self)) {
            return false;
        }

        if ($user->getUserIdentifier() !== $this->getUserIdentifier()) {
            return false;
        }

        if ($user->getId() !== $this->getId()) {
            return false;
        }

        return true;
    }

    /**
     * Add a role to a user.
     */
    public function addRole(string $role): void
    {
        if ('ROLE_USER' === $role) {
            return;
        }

        if (!in_array($role, static::USER_ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('The role "%s" is not valid', $role));
        }

        if (!in_array($role, $this->roles ?? [], true)) {
            if (null === $this->roles) {
                $this->roles = [];
            }
            $this->roles[] = $role;
        }
    }

    public function removeRole(string $role): void
    {
        if (($key = array_search($role, $this->roles ?? [], true)) !== false) {
            unset($this->roles[$key]);
        }
    }

    public function getOrg(): Organization
    {
        return $this->org;
    }

    public function setOrg(Organization $org): void
    {
        $this->org = $org;
    }

    /**
     * Get the frameworks owned by the user.
     *
     * @return Collection<array-key, LsDoc>
     */
    public function getFrameworks(): Collection
    {
        return $this->frameworks;
    }

    /**
     * @return Collection<array-key, UserDocAcl>
     */
    public function getDocAcls(): Collection
    {
        return $this->docAcls;
    }

    /**
     * Checks whether the user's account has expired.
     *
     * Internally, if this method returns false, the authentication system
     * will throw an AccountExpiredException and prevent login.
     *
     * @return bool true if the user's account is non expired, false otherwise
     *
     * @see AccountExpiredException
     */
    public function isAccountNonExpired(): bool
    {
        // Accounts do not currently expire
        return true;
    }

    /**
     * Checks whether the user is suspended.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a LockedException and prevent login.
     *
     * @return bool true if the user is not suspended, false otherwise
     *
     * @see LockedException
     */
    public function isAccountNonLocked(): bool
    {
        return !($this->isSuspended() || $this->isPending());
    }

    /**
     * Checks whether the user's credentials (password) has expired.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a CredentialsExpiredException and prevent login.
     *
     * @return bool true if the user's credentials are non expired, false otherwise
     *
     * @see CredentialsExpiredException
     */
    public function isCredentialsNonExpired(): bool
    {
        // Currently credentials do not expire
        return true;
    }

    /**
     * Checks whether the user is enabled.
     *
     * Internally, if this method returns false, the authentication system
     * will throw a DisabledException and prevent login.
     *
     * @return bool true if the user is enabled, false otherwise
     *
     * @see DisabledException
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return bool true if the user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === static::SUSPENDED;
    }

    public function suspendUser(): void
    {
        $this->status = static::SUSPENDED;
    }

    public function activateUser(): void
    {
        $this->status = static::ACTIVE;
    }

    /**
     * @return bool true if the user is pending for approval
     */
    public function isPending(): bool
    {
        return static::PENDING === $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getStatus(): string
    {
        $statusArray = ['Active', 'Suspended', 'Pending'];

        return $statusArray[$this->status];
    }

    public function setTotpSecret(?string $totpSecret): self
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function setIsTotpEnabled(bool $isTotpEnabled): void
    {
        $this->isTotpEnabled = $isTotpEnabled;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret && true === $this->isTotpEnabled;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }
}
