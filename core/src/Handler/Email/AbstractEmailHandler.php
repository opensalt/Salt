<?php

namespace App\Handler\Email;

use App\Command\Email\AbstractSendEmailCommand;
use App\Entity\ChangeEntry;
use App\Entity\NotificationOnlyChangeEntry;
use App\Event\CommandEvent;
use App\Event\NotificationEvent;
use App\Handler\BaseValidatedHandler;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractEmailHandler extends BaseValidatedHandler
{
    public function __construct(
        ValidatorInterface $validator,
        private readonly FeatureManager $featureManager,
        private readonly MailerInterface $mailer,
        private readonly ?string $mailFromEmail = null,
    ) {
        parent::__construct($validator);
    }

    public function handle(CommandEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        /** @var AbstractSendEmailCommand $command */
        $command = $event->getCommand();

        // Only send emails if the feature is enabled
        if (!$this->featureManager->isEnabled('email_feature')) {
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

        $email = (new Email())
            ->from($this->mailFromEmail)
            ->to($command->getRecipient());
        $this->configureMessage($email, $command);

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->setEmailNotSentNotification($command, 'Error: '.$e::class.': '.$e->getMessage());

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

    abstract protected function configureMessage(Email $email, AbstractSendEmailCommand $command): void;
}
