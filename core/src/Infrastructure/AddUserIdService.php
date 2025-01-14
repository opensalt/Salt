<?php

namespace App\Infrastructure;

use App\Entity\User\User;
use Ecotone\Messaging\Attribute\Interceptor\Before;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

readonly class AddUserIdService
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    #[Before(precedence: 0, pointcut: AddUserId::class, changeHeaders: true)]
    public function addUserId(): array
    {
        $token = $this->tokenStorage->getToken();
        $userIdentifier = $token->getUserIdentifier();
        $user = $token->getUser();

        $userId = null;
        if ($user instanceof User) {
            $userId = $user->getId();
        }

        return [
            'userId' => $userId,
            'executorId' => $userIdentifier,
        ];
    }
}
