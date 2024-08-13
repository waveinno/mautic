<?php

declare(strict_types=1);

namespace Mautic\EmailBundle\Tests\Entity;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Mautic\CoreBundle\Test\Doctrine\RepositoryConfiguratorTrait;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\EmailRepository;
use PHPUnit\Framework\TestCase;

class EmailRepositoryTest extends TestCase
{
    use RepositoryConfiguratorTrait;

    private EmailRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = $this->configureRepository(Email::class);
        $this->connection->method('createQueryBuilder')->willReturnCallback(fn () => new QueryBuilder($this->connection));
    }

    /**
     * @dataProvider dataGetEmailPendingQueryForCount
     *
     * @param int[] $variantIds
     * @param int[] $excludedListIds
     */
    public function testGetEmailPendingQueryForCount(?array $variantIds, bool $countWithMaxMin, array $excludedListIds, string $expectedQuery): void
    {
        $this->mockExcludedListIds($excludedListIds);

        $emailId         = 5;
        $listIds         = [22, 33];
        $countOnly       = true;
        $limit           = null;
        $minContactId    = null;
        $maxContactId    = null;

        $query = $this->repo->getEmailPendingQuery(
            $emailId,
            $variantIds,
            $listIds,
            $countOnly,
            $limit,
            $minContactId,
            $maxContactId,
            $countWithMaxMin
        );

        $this->assertEquals($this->replaceQueryPrefix($expectedQuery), $query->getSql());
        $this->assertEquals(['false' => false], $query->getParameters());
    }

    /**
     * @return iterable<mixed[]>
     */
    public function dataGetEmailPendingQueryForCount(): iterable
    {
        yield [null, false, [], "SELECT count(*) as count FROM {prefix}leads l WHERE (l.id IN (SELECT ll.lead_id FROM {prefix}lead_lists_leads ll WHERE (ll.leadlist_id IN (22, 33)) AND (ll.manually_removed = :false))) AND (l.id NOT IN (SELECT dnc.lead_id FROM {prefix}lead_donotcontact dnc WHERE dnc.channel = 'email')) AND (l.id NOT IN (SELECT stat.lead_id FROM {prefix}email_stats stat WHERE (stat.lead_id IS NOT NULL) AND (stat.email_id = 5))) AND (l.id NOT IN (SELECT mq.lead_id FROM {prefix}message_queue mq WHERE (mq.status <> 'sent') AND (mq.channel = 'email') AND (mq.channel_id = 5))) AND (l.id NOT IN (SELECT lc.lead_id FROM {prefix}lead_categories lc INNER JOIN {prefix}emails e ON e.category_id = lc.category_id WHERE (e.id = 5) AND (lc.manually_removed = 1))) AND ((l.email IS NOT NULL) AND (l.email <> ''))"];
        yield [[6], false, [16], "SELECT count(*) as count FROM {prefix}leads l WHERE (l.id IN (SELECT ll.lead_id FROM {prefix}lead_lists_leads ll WHERE (ll.leadlist_id IN (22, 33)) AND (ll.manually_removed = :false))) AND (l.id NOT IN (SELECT dnc.lead_id FROM {prefix}lead_donotcontact dnc WHERE dnc.channel = 'email')) AND (l.id NOT IN (SELECT stat.lead_id FROM {prefix}email_stats stat WHERE (stat.lead_id IS NOT NULL) AND (stat.email_id IN (6, 5)))) AND (l.id NOT IN (SELECT mq.lead_id FROM {prefix}message_queue mq WHERE (mq.status <> 'sent') AND (mq.channel = 'email') AND (mq.channel_id IN (6, 5)))) AND (l.id NOT IN (SELECT lc.lead_id FROM {prefix}lead_categories lc INNER JOIN {prefix}emails e ON e.category_id = lc.category_id WHERE (e.id = 5) AND (lc.manually_removed = 1))) AND ((l.email IS NOT NULL) AND (l.email <> ''))"];
        yield [null, true, [9, 7], "SELECT count(*) as count, MIN(l.id) as min_id, MAX(l.id) as max_id FROM {prefix}leads l WHERE (l.id IN (SELECT ll.lead_id FROM {prefix}lead_lists_leads ll WHERE (ll.leadlist_id IN (22, 33)) AND (ll.manually_removed = :false))) AND (l.id NOT IN (SELECT dnc.lead_id FROM {prefix}lead_donotcontact dnc WHERE dnc.channel = 'email')) AND (l.id NOT IN (SELECT stat.lead_id FROM {prefix}email_stats stat WHERE (stat.lead_id IS NOT NULL) AND (stat.email_id = 5))) AND (l.id NOT IN (SELECT mq.lead_id FROM {prefix}message_queue mq WHERE (mq.status <> 'sent') AND (mq.channel = 'email') AND (mq.channel_id = 5))) AND (l.id NOT IN (SELECT lc.lead_id FROM {prefix}lead_categories lc INNER JOIN {prefix}emails e ON e.category_id = lc.category_id WHERE (e.id = 5) AND (lc.manually_removed = 1))) AND ((l.email IS NOT NULL) AND (l.email <> ''))"];
    }

    /**
     * @dataProvider dataGetEmailPendingQueryForMaxMinIdCountWithMaxMinIdsDefined
     *
     * @param int[] $excludedListIds
     */
    public function testGetEmailPendingQueryForMaxMinIdCountWithMaxMinIdsDefined(array $excludedListIds, string $expectedQuery): void
    {
        $this->mockExcludedListIds($excludedListIds);

        $emailId         = 5;
        $variantIds      = null;
        $listIds         = [22, 33];
        $countOnly       = true;
        $limit           = null;
        $minContactId    = 10;
        $maxContactId    = 1000;
        $countWithMaxMin = true;

        $query = $this->repo->getEmailPendingQuery(
            $emailId,
            $variantIds,
            $listIds,
            $countOnly,
            $limit,
            $minContactId,
            $maxContactId,
            $countWithMaxMin
        );

        $expectedParams = [
            'false'        => false,
            'minContactId' => 10,
            'maxContactId' => 1000,
        ];

        $this->assertEquals($this->replaceQueryPrefix($expectedQuery), $query->getSql());
        $this->assertEquals($expectedParams, $query->getParameters());
    }

    /**
     * @return iterable<mixed[]>
     */
    public function dataGetEmailPendingQueryForMaxMinIdCountWithMaxMinIdsDefined(): iterable
    {
        yield [[], "SELECT count(*) as count, MIN(l.id) as min_id, MAX(l.id) as max_id FROM {prefix}leads l WHERE (l.id IN (SELECT ll.lead_id FROM {prefix}lead_lists_leads ll WHERE (ll.leadlist_id IN (22, 33)) AND (ll.manually_removed = :false))) AND (l.id NOT IN (SELECT dnc.lead_id FROM {prefix}lead_donotcontact dnc WHERE dnc.channel = 'email')) AND (l.id NOT IN (SELECT stat.lead_id FROM {prefix}email_stats stat WHERE (stat.lead_id IS NOT NULL) AND (stat.email_id = 5))) AND (l.id NOT IN (SELECT mq.lead_id FROM {prefix}message_queue mq WHERE (mq.status <> 'sent') AND (mq.channel = 'email') AND (mq.channel_id = 5))) AND (l.id NOT IN (SELECT lc.lead_id FROM {prefix}lead_categories lc INNER JOIN {prefix}emails e ON e.category_id = lc.category_id WHERE (e.id = 5) AND (lc.manually_removed = 1))) AND (l.id >= :minContactId) AND (l.id <= :maxContactId) AND ((l.email IS NOT NULL) AND (l.email <> ''))"];
        yield [[96, 98, 103], "SELECT count(*) as count, MIN(l.id) as min_id, MAX(l.id) as max_id FROM {prefix}leads l WHERE (l.id IN (SELECT ll.lead_id FROM {prefix}lead_lists_leads ll WHERE (ll.leadlist_id IN (22, 33)) AND (ll.manually_removed = :false))) AND (l.id NOT IN (SELECT dnc.lead_id FROM {prefix}lead_donotcontact dnc WHERE dnc.channel = 'email')) AND (l.id NOT IN (SELECT stat.lead_id FROM {prefix}email_stats stat WHERE (stat.lead_id IS NOT NULL) AND (stat.email_id = 5))) AND (l.id NOT IN (SELECT mq.lead_id FROM {prefix}message_queue mq WHERE (mq.status <> 'sent') AND (mq.channel = 'email') AND (mq.channel_id = 5))) AND (l.id NOT IN (SELECT lc.lead_id FROM {prefix}lead_categories lc INNER JOIN {prefix}emails e ON e.category_id = lc.category_id WHERE (e.id = 5) AND (lc.manually_removed = 1))) AND (l.id >= :minContactId) AND (l.id <= :maxContactId) AND ((l.email IS NOT NULL) AND (l.email <> ''))"];
    }

    /**
     * @param int[] $excludedListIds
     */
    private function mockExcludedListIds(array $excludedListIds): void
    {
        $resultMock = $this->createMock(Result::class);
        $resultMock->method('fetchAllAssociative')
            ->willReturn(array_map(fn (int $id) => [$id], $excludedListIds));
        $this->connection->method('executeQuery')
            ->willReturn($resultMock);
    }

    private function replaceQueryPrefix(string $query): string
    {
        return str_replace('{prefix}', MAUTIC_TABLE_PREFIX, $query);
    }
}
