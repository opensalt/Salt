<?php

namespace App\Handler\Email;

use App\Command\Email\AbstractSendEmailCommand;
use App\Entity\ChangeEntry;
use App\Entity\NotificationOnlyChangeEntry;
use App\Event\CommandEvent;
use App\Event\NotificationEvent;
use App\Handler\BaseValidatedHandler;
use Qandidate\Toggle\ContextFactory;
use Qandidate\Toggle\ToggleManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractEmailHandler extends BaseValidatedHandler implements EventSubscriberInterface
{
    /**
     * @var ToggleManager
     */
    private $manager;

    /**
     * @var ContextFactory
     */
    private $context;

    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    /**
     * @var string
     */
    private $mailFromEmail;

    public function __construct(ValidatorInterface $validator, ToggleManager $manager, ContextFactory $contextFactory, \Swift_Mailer $mailer, string $mailFromEmail = null)
    {
        parent::__construct($validator);
        $this->manager = $manager;
        $this->context = $contextFactory->createContext();
        $this->mailer = $mailer;
        $this->mailFromEmail = $mailFromEmail;
    }

    public static function getSubscribedEvents()
    {
        $event = str_replace('Handler', 'Command', static::class);
        return [$event => 'handle'];
    }

    public function handle(CommandEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        /** @var AbstractSendEmailCommand $command */
        $command = $event->getCommand();

        // Only send emails if the feature is enabled
        if (!$this->manager->active('email_feature', $this->context)) {
            $this->setEmailNotSentNotification($command, 'disabled');
            return;
        }

        // Allow messages to have their own rules to keep an email from being sent
        if (!$this->canSendEmail()) {
            $this->setEmailNotSentNotification($command, 'rejected');
            return;
        }

        // Do not send an email if there is no from address
        if (empty($this->mailFromEmail)) {
            $this->setEmailNotSentNotification($command, 'no from address');
            return;
        }

        if (empty($command->getRecipient())) {
            $this->setEmailNotSentNotification($command, 'no to address');
            return;
        }

        $email = (new \Swift_Message())
            ->setFrom($this->mailFromEmail)
            ->setTo($command->getRecipient());
        $this->configureMessage($email);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->setEmailNotSentNotification($command, 'Error: '.get_class($e).': '.$e->getMessage());

            throw $e;
        }

        $this->setEmailSentNotification($command);
    }

    protected function setEmailSentNotification(AbstractSendEmailCommand $command): void
    {
        $notification = $this->getSentNotificationEvent();
        $command->setNotificationEvent($notification);

        $changeEntry = $this->getSentChangeEntry();
        $command->setChangeEntry($changeEntry);
    }

    protected function setEmailNotSentNotification(AbstractSendEmailCommand $command, string $reason): void
    {
        $notification = $this->getNotSentNotificationEvent($reason);
        $command->setNotificationEvent($notification);

        $changeEntry = $this->getNotSentChangeEntry($reason);
        $command->setChangeEntry($changeEntry);
    }

    protected function canSendEmail(): bool
    {
        return true;
    }

    protected function getNotSentNotificationEvent(string $reason): NotificationEvent
    {
        return new NotificationEvent('M00', "Email not sent: {$reason}", null);
    }

    protected function getNotSentChangeEntry(string $reason): ChangeEntry
    {
        return new NotificationOnlyChangeEntry(null, null, "Email not sent: {$reason}");
    }

    protected function getSentNotificationEvent(): NotificationEvent
    {
        return new NotificationEvent('M01', 'Email sent', null);
    }

    protected function getSentChangeEntry(): ChangeEntry
    {
        return new NotificationOnlyChangeEntry(null, null, 'Email sent');
    }

    abstract protected function configureMessage(\Swift_Message $email): void;
}
