<?php

declare(strict_types=1);

namespace Mautic\CampaignBundle\Tests\Controller;

use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Entity\LeadEventLogRepository;
use Mautic\CampaignBundle\Tests\Functional\Fixtures\FixtureHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class AjaxControllerFunctionalTest extends MauticMysqlTestCase
{
    public function testCancelScheduledCampaignEventAction(): void
    {
        $fixtureHelper = new FixtureHelper($this->em);
        $contact       = $fixtureHelper->createContact('some@contact.email');
        $campaign      = $fixtureHelper->createCampaign('Scheduled event test');
        $fixtureHelper->addContactToCampaign($contact, $campaign);
        $fixtureHelper->createCampaignWithScheduledEvent($campaign);
        $this->em->flush();

        $commandResult = $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()]);

        Assert::assertStringContainsString('1 total event was scheduled', $commandResult->getDisplay());

        $payload = [
            'action'    => 'campaign:cancelScheduledCampaignEvent',
            'eventId'   => $campaign->getEvents()[0]->getId(),
            'contactId' => $contact->getId(),
        ];

        $this->client->request(Request::METHOD_POST, '/s/ajax', $payload, [], $this->createAjaxHeaders());

        // Ensure we'll fetch fresh data from the database and not from entity manager.
        $this->em->detach($contact);
        $this->em->detach($campaign);

        /** @var LeadEventLogRepository $leadEventLogRepository */
        $leadEventLogRepository = $this->em->getRepository(LeadEventLog::class);

        /** @var LeadEventLog $log */
        $log = $leadEventLogRepository->findOneBy(['lead' => $contact, 'campaign' => $campaign]);

        Assert::assertTrue($this->client->getResponse()->isOk());
        Assert::assertSame('{"success":1}', $this->client->getResponse()->getContent());
        Assert::assertFalse($log->getIsScheduled());
    }
}
