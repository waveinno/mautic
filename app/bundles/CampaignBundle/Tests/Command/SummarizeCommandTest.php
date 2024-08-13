<?php

namespace Mautic\CampaignBundle\Tests\Command;

use Mautic\CampaignBundle\Command\SummarizeCommand;
use Mautic\CampaignBundle\Entity\Summary;
use Mautic\CampaignBundle\Entity\SummaryRepository;
use Mautic\CampaignBundle\Tests\Campaign\AbstractCampaignTest;
use PHPUnit\Framework\Assert;

final class SummarizeCommandTest extends AbstractCampaignTest
{
    /**
     * @throws \Exception
     */
    public function testBackwardSummarizationWhenThereAreNoCampaignEventLogs(): void
    {
        $commandResult = $this->testSymfonyCommand(
            SummarizeCommand::NAME,
            [
                '--env'       => 'test',
                '--max-hours' => 768,
            ]
        );

        /** @var SummaryRepository $summaryRepo */
        $summaryRepo = $this->em->getRepository(Summary::class);
        Assert::assertCount(0, $summaryRepo->findAll());
        Assert::assertStringContainsString(
            'There are no records in the campaign lead event log table. Nothing to summarize.',
            $commandResult->getDisplay()
        );
    }

    /**
     * @throws \Exception
     */
    public function testBackwardSummarizationWhenThereAreLogs(): void
    {
        $relativeDate = date('Y-m-d', strtotime('-1 month'));

        $campaign = $this->saveSomeCampaignLeadEventLogs();

        $this->testSymfonyCommand(
            SummarizeCommand::NAME,
            [
                '--env'       => 'test',
                '--max-hours' => 768,
            ]
        );

        /** @var SummaryRepository $summaryRepo */
        $summaryRepo = $this->em->getRepository(Summary::class);

        /** @var Summary[] $summaries */
        $summaries = $summaryRepo->findAll();

        Assert::assertCount(3, $summaries);

        Assert::assertSame($relativeDate.'T17:00:00+00:00', $summaries[0]->getDateTriggered()->format(DATE_ATOM));
        Assert::assertSame(1, $summaries[0]->getTriggeredCount());
        Assert::assertSame($campaign->getId(), $summaries[0]->getCampaign()->getId());
        Assert::assertSame('Event B', $summaries[0]->getEvent()->getName());

        Assert::assertSame($relativeDate.'T16:00:00+00:00', $summaries[1]->getDateTriggered()->format(DATE_ATOM));
        Assert::assertSame(2, $summaries[1]->getTriggeredCount());
        Assert::assertSame($campaign->getId(), $summaries[1]->getCampaign()->getId());
        Assert::assertSame('Event A', $summaries[1]->getEvent()->getName());

        Assert::assertSame($relativeDate.'T16:00:00+00:00', $summaries[2]->getDateTriggered()->format(DATE_ATOM));
        Assert::assertSame(1, $summaries[2]->getTriggeredCount());
        Assert::assertSame($campaign->getId(), $summaries[2]->getCampaign()->getId());
        Assert::assertSame('Event B', $summaries[2]->getEvent()->getName());
    }
}
