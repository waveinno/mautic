<?php

namespace Mautic\EmailBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class Copy
{
    /**
     * MD5 hash of the content.
     *
     * @var string
     */
    private $id;

    /**
     * @var \DateTimeInterface
     */
    private $dateCreated;

    /**
     * @var string|null
     */
    private $body;

    private ?string $bodyText = null;

    /**
     * @var string|null
     */
    private $subject;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('email_copies')
            ->setCustomRepositoryClass(CopyRepository::class);

        $builder->createField('id', 'string')
            ->makePrimaryKey()
            ->length(32)
            ->build();

        $builder->createField('dateCreated', 'datetime')
            ->columnName('date_created')
            ->build();

        $builder->addNullableField('body', 'text');
        $builder->addNullableField('bodyText', 'text', 'body_text');

        $builder->addNullableField('subject', 'text');
    }

    /**
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getDateCreated()
    {
        return $this->dateCreated;
    }

    /**
     * @param \DateTime $dateCreated
     *
     * @return Copy
     */
    public function setDateCreated($dateCreated)
    {
        $this->dateCreated = $dateCreated;

        return $this;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return Copy
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param mixed $subject
     *
     * @return Copy
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBodyText(): ?string
    {
        return $this->bodyText;
    }

    public function setBodyText(?string $bodyText): self
    {
        $this->bodyText = $bodyText;

        return $this;
    }
}
