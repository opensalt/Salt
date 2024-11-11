<?php

namespace App\EventListener;

use App\Event\NotificationEvent;
use App\Service\LoggerTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Data structure.
 *
 * "doc": {
 *     doc_id: {
 *         "notification": [
 *             KEY: {
 *                 "at": <milliseconds since epoch>,
 *                 "msgId": "message id here",
 *                 "msg": "message here",
 *                 "by": "username here",
 *                 "changes": [
 *                     "{item,doc,assoc}-{a,u,d,l,ul}": [
 *                         object-id: "object identifier", ...
 *                     ],
 *                 ],
 *             }
 *         ]
 *     }
 * }
 */
class NotificationToMercureListener implements EventSubscriberInterface
{
    use LoggerTrait;

    public function __construct(
        private HubInterface $publisher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [NotificationEvent::class => 'handleNotification'];
    }

    public function handleNotification(NotificationEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->notice('In handler for Mercure');

        $doc = $event->getDoc();
        if (null === $doc) {
            // Only send document changes to Mercure
            return;
        }
        $docId = $doc->getId();

        $notification = [
            'msgId' => $event->getMessageId(),
            'msg' => $event->getMessage(),
            'by' => $event->getUsername(),
            'changes' => $event->getChanged(),
            'at' => (int) (new \DateTime())->format('Uv'),
        ];

        $this->addDocChangeToMercure($notification, $docId);
    }

    protected function addDocChangeToMercure(array $notification, int $docId): void
    {
        $this->info('Sending to Mercure', [
            'msg' => $notification['msg'],
            'user' => $notification['by'],
            'changes' => $notification['changes'],
        ]);

        $update = new Update(
            "doc-updates/{$docId}",
            json_encode($notification, JSON_THROW_ON_ERROR),
            false
        );

        $this->publisher->publish($update);
    }
}
