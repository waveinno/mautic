<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Field;

use Doctrine\DBAL\Exception\DriverException;
use Mautic\CoreBundle\Doctrine\Helper\ColumnSchemaHelper;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Field\Dispatcher\FieldColumnDispatcher;
use Mautic\LeadBundle\Field\Exception\AbortColumnCreateException;
use Mautic\LeadBundle\Field\Exception\AbortColumnUpdateException;
use Mautic\LeadBundle\Field\Exception\CustomFieldLimitException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CustomFieldColumn
{
    public function __construct(
        private ColumnSchemaHelper $columnSchemaHelper,
        private SchemaDefinition $schemaDefinition,
        private LoggerInterface $logger,
        private LeadFieldSaver $leadFieldSaver,
        private CustomFieldIndex $customFieldIndex,
        private FieldColumnDispatcher $fieldColumnDispatcher,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * @throws AbortColumnCreateException
     * @throws AbortColumnUpdateException
     * @throws CustomFieldLimitException
     * @throws \Doctrine\DBAL\Exception
     * @throws DriverException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws SchemaException
     */
    public function createLeadColumn(LeadField $leadField): void
    {
        $leadsSchema = $this->columnSchemaHelper->setName($leadField->getCustomFieldObject());

        // We have to check if the LeadField entity is new and the column already exists .
        // In such case we must throw an exception to warn users that the column already exists.
        try {
            $columnExists = $leadsSchema->checkColumnExists($leadField->getAlias(), $leadField->isNew());

            if ($columnExists && $this->customFieldIndex->isUpdatePending($leadField)) {
                $this->fieldColumnDispatcher->dispatchPreUpdateColumnEvent($leadField);
                $this->processUpdateLeadColumn($leadField);
            }

            if ($columnExists) {
                return;
            }
        } catch (SchemaException) {
            // We use slightly different error message if the column already exists in this case.
            throw new SchemaException($this->translator->trans('mautic.lead.field.column.already.exists', ['%field%' => $leadField->getName()], 'validators'));
        }

        try {
            $this->fieldColumnDispatcher->dispatchPreAddColumnEvent($leadField);
        } catch (AbortColumnCreateException $e) {
            // Save the field metadata and throw the exception again to stop column creation.
            // As the column should be created by a background job.
            $this->leadFieldSaver->saveLeadFieldEntityWithoutColumnCreated($leadField);

            throw $e;
        }

        $this->processCreateLeadColumn($leadField);
    }

    /**
     * Create the field as its own column in the leads table.
     *
     * @throws CustomFieldLimitException
     * @throws DriverException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws SchemaException
     */
    public function processCreateLeadColumn(LeadField $leadField, bool $saveLeadField = true): void
    {
        $leadsSchema = $this->columnSchemaHelper->setName($leadField->getCustomFieldObject());

        // Check if column do not exist. This method could be called from plugins too.
        if ($leadsSchema->checkColumnExists($leadField->getAlias())) {
            return;
        }

        $schemaDefinition = $this->schemaDefinition->getSchemaDefinitionNonStatic(
            $leadField->getAlias(),
            $leadField->getType(),
            (bool) $leadField->getIsUniqueIdentifier(),
            (int) $leadField->getCharLengthLimit()
        );

        $leadsSchema->addColumn($schemaDefinition);

        try {
            $leadsSchema->executeChanges();
        } catch (DriverException $e) {
            $this->logger->warning($e->getMessage());

            if (1118 === $e->getCode() /* ER_TOO_BIG_ROWSIZE */) {
                throw new CustomFieldLimitException('mautic.lead.field.max_column_error');
            }

            throw $e;
        }

        if ($saveLeadField) {
            // $leadField is a new entity (this is not executed for update), it was successfully added to the lead table > save it
            $this->leadFieldSaver->saveLeadFieldEntity($leadField, true);
        }

        if ($leadField->isIsIndex() || $leadField->getIsUniqueIdentifier()) {
            $this->customFieldIndex->addIndexOnColumn($leadField);
        }
    }

    /**
     * Updates the field column in the leads table.
     *
     * @throws DriverException
     * @throws \Doctrine\DBAL\Schema\SchemaException
     * @throws SchemaException
     */
    public function processUpdateLeadColumn(LeadField $leadField): void
    {
        $hasIndex = $this->customFieldIndex->hasIndex($leadField);

        if ($leadField->isIsIndex() && !$hasIndex) {
            $this->customFieldIndex->addIndexOnColumn($leadField);
        } elseif (!$leadField->isIsIndex() && $hasIndex) {
            $this->customFieldIndex->dropIndexOnColumn($leadField);
        }

        $this->customFieldIndex->updateUniqueIdentifierIndex($leadField);
    }
}
