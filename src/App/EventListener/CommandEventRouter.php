<?php

namespace App\EventListener;

use App\Command\CommandInterface;
use App\Entity\ChangeEntry;
use App\Event\CommandEvent;
use App\Event\NotificationEvent;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use JMS\DiExtraBundle\Annotation as DI;
use Psr\Log\LoggerInterface;
use Salt\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class CommandEventRouter
 *
 * @DI\Service()
 */
class CommandEventRouter
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * AddDocumentHandler constructor.
     *
     * @DI\InjectParams({
     *     "registry" = @DI\Inject("doctrine"),
     *     "tokenStorage" = @DI\Inject("security.token_storage"),
     *     "logger" = @DI\Inject("logger"),
     * })
     */
    public function __construct(ManagerRegistry $registry, TokenStorageInterface $tokenStorage, LoggerInterface $logger)
    {
        $this->em = $registry->getManager();
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @DI\Observe(App\Event\CommandEvent::class)
     *
     * @throws \Exception
     */
    public function routeCommand(CommandEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->em->getConnection()->beginTransaction();

        /** @var CommandInterface $command */
        $command = $event->getCommand();

        $this->sendCommand($event, $dispatcher);

        $notification = $command->getNotificationEvent();
        $this->addChangeEntry($command, $notification);

        $this->em->flush();
        $this->em->getConnection()->commit();

        $this->sendNotification($dispatcher, $notification, $command);
    }

    /**
     * @throws \Exception
     */
    protected function sendCommand(CommandEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $command = $event->getCommand();

        $this->logger->info('Routing command', ['command' => \get_class($command)]);

        try {
            $dispatcher->dispatch(\get_class($command), $event);

            if ($command->getValidationErrors()) {
                $errorString = (string) $command->getValidationErrors();
                $this->logger->info('Error in command', ['command' => \get_class($command), 'errors' => $errorString]);
            }
        } catch (\Exception $e) {
            $this->logger->info('Exception in command', ['command' => \get_class($command), 'exception' => $e]);

            throw $e;
        }
    }

    protected function addChangeEntry(CommandInterface $command, ?NotificationEvent $notification): void
    {
        $changeEntry = $command->getChangeEntry();
        if (null === $changeEntry) {
            $user = $this->getCurrentUser();

            if (null !== $notification) {
                $changeEntry = new ChangeEntry($notification->getDoc(), $user, $notification->getMessage(), $notification->getChanged());
            } else {
                $changeEntry = new ChangeEntry(null, $user, \get_class($command) . ' occurred with no data');
            }
        }

        // We only store the last change in the table, older entries are in the audit table
        $change = $this->em->getRepository(ChangeEntry::class)->findOneBy(['doc' => $changeEntry->getDoc()]);
        if (null === $change) {
            $change = $changeEntry;
            $this->em->persist($change);
        } else {
            $change->updateTo($changeEntry);
        }
    }

    protected function sendNotification(EventDispatcherInterface $dispatcher, ?NotificationEvent $notification, CommandInterface $command): void
    {
        if (null === $notification) {
            $notification = new NotificationEvent('Command ' . \get_class($command) . ' handled', null);
        }
        if (null === $notification->getUsername()) {
            $notification->setUsername($this->getCurrentUsername());
        }

        $notification->resolveChanged();
        $dispatcher->dispatch(NotificationEvent::class, $notification);
    }

    protected function getCurrentUser(): ?User
    {
        $user = null;

        $token = $this->tokenStorage->getToken();
        if ($token) {
            $user = $token->getUser();
            if (!$user instanceof User) {
                $user = null;
            }
        }

        return $user;
    }

    protected function getCurrentUsername(): string
    {
        $user = $this->getCurrentUser();
        if (null === $user) {
            return 'Unknown User';
        }

        return $user->getUsername();
    }
}
