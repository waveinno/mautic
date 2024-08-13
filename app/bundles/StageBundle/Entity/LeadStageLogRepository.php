<?php

namespace Mautic\StageBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<LeadStageLog>
 */
class LeadStageLogRepository extends CommonRepository
{
    /**
     * Updates lead ID (e.g. after a lead merge).
     */
    public function updateLead($fromLeadId, $toLeadId): void
    {
        // First check to ensure the $toLead doesn't already exist
        $results = $this->_em->getConnection()->createQueryBuilder()
            ->select('pl.stage_id')
            ->from(MAUTIC_TABLE_PREFIX.'stage_lead_action_log', 'pl')
            ->where('pl.lead_id = '.$toLeadId)
            ->executeQuery()
            ->fetchAllAssociative();

        $actions = [];
        foreach ($results as $r) {
            $actions[] = $r['stage_id'];
        }

        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'stage_lead_action_log')
            ->set('lead_id', (int) $toLeadId)
            ->where('lead_id = '.(int) $fromLeadId);

        if (!empty($actions)) {
            $q->andWhere(
                $q->expr()->notIn('stage_id', $actions)
            )->executeStatement();

            // Delete remaining leads as the new lead already belongs
            $this->_em->getConnection()->createQueryBuilder()
                ->delete(MAUTIC_TABLE_PREFIX.'stage_lead_action_log')
                ->where('lead_id = '.(int) $fromLeadId)
                ->executeStatement();
        } else {
            $q->executeStatement();
        }
    }
}
