<?php

namespace App\Service\User;

use App\Entity\User\Organization;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $encoder,
    ) {
    }

    /**
     * Creates a user.
     *
     * @return string The user's password
     *
     * @throws \Exception if it was not possible to gather sufficient entropy
     */
    public function addNewUser(string $username, Organization $org, ?string $plainPassword = null, ?string $role = null, ?int $status = null): string
    {
        if (null === $plainPassword || ('' === trim($plainPassword))) {
            // if there is no password, make something ugly up
            $plainPassword = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
        }

        if (null === $role) {
            $role = 'ROLE_USER';
        }
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_'.preg_replace('/[^A-Z]/', '_', strtoupper($role));
        }

        if (!in_array($role, array_merge(User::USER_ROLES, ['ROLE_USER']), true)) {
            throw new \InvalidArgumentException("Role {$role} is not a valid role.");
        }

        $user = new User($username);
        $user->setOrg($org);
        $password = $this->encoder->hashPassword($user, $plainPassword);
        $user->setPassword($password);
        $user->addRole($role);
        if (User::ACTIVE === $status) {
            $user->activateUser();
        }

        $this->em->persist($user);

        return $plainPassword;
    }

    /**
     * Sets the password for a user.
     *
     * @return string The user's password
     *
     * @throws \Exception if it was not possible to gather sufficient entropy
     */
    public function setUserPassword(string $username, ?string $plainPassword = null): string
    {
        if (null === $plainPassword || ('' === trim($plainPassword))) {
            // if there is no password, make something ugly up
            $plainPassword = rtrim(strtr(base64_encode(random_bytes(15)), '+/', '-_'), '=');
        }

        $user = $this->loadUserByIdentifier($username);
        if (null === $user) {
            throw new \InvalidArgumentException(sprintf('The user "%s" does not exist.', $username));
        }
        $password = $this->encoder->hashPassword($user, $plainPassword);
        $user->setPassword($password);

        return $plainPassword;
    }

    /**
     * Add a role to a user.
     *
     * @throws \InvalidArgumentException
     */
    public function addRoleToUser(string $username, string $role): void
    {
        $user = $this->loadUserByIdentifier($username);
        if (null === $user) {
            throw new \InvalidArgumentException(sprintf('The user "%s" does not exist.', $username));
        }

        $user->addRole($role);
    }

    /**
     * Remove a role from a user.
     *
     * @throws \InvalidArgumentException
     */
    public function removeRoleFromUser(string $username, string $role): void
    {
        $user = $this->loadUserByIdentifier($username);
        if (null === $user) {
            throw new \InvalidArgumentException(sprintf('The user "%s" does not exist.', $username));
        }

        $user->removeRole($role);
    }

    public function loadUserByIdentifier(string $username): ?User
    {
        return $this->em->getRepository(User::class)->loadUserByIdentifier($username);
    }
}
