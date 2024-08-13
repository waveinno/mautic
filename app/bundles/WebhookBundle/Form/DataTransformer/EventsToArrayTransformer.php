<?php

namespace Mautic\WebhookBundle\Form\DataTransformer;

use Doctrine\Common\Collections\Collection;
use Mautic\WebhookBundle\Entity\Event;
use Mautic\WebhookBundle\Entity\Webhook;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<Collection<int, Event>, array<int, string>>
 */
class EventsToArrayTransformer implements DataTransformerInterface
{
    public function __construct(
        private Webhook $webhook
    ) {
    }

    /**
     * Convert from the PersistentCollection of Event entities to a simple array.
     *
     * @param $events Collection<int, Event>
     *
     * @return array<int, string>
     */
    public function transform($events)
    {
        $eventArray = [];
        foreach ($events as $event) {
            $eventArray[] = $event->getEventType();
        }

        return $eventArray;
    }

    /**
     * Convert a simple array into a PersistentCollection of Event entities.
     *
     * @param $submittedArray array<int, string>
     *
     * @return Collection<int, Event>
     */
    public function reverseTransform($submittedArray)
    {
        // Get a list of existing events and types

        $events     = $this->webhook->getEvents();
        $eventTypes = $events->getKeys();

        // Check to see what events have been removed
        $removed = array_diff($eventTypes, $submittedArray);
        foreach ($removed as $type) {
            $this->webhook->removeEvent($events[$type]);
        }

        // Now check to see what events have been added
        $added = array_diff($submittedArray, $eventTypes);
        foreach ($added as $type) {
            // Create a new entity
            $event = new Event();
            $event->setWebhook($this->webhook)->setEventType($type);
            $events[] = $event;
        }

        $this->webhook->setEvents($events);

        return $events;
    }
}
