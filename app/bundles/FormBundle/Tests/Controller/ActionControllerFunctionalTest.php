<?php

namespace Mautic\FormBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class ActionControllerFunctionalTest extends MauticMysqlTestCase
{
    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testNewActionWithJapanese(): void
    {
        // Create new form
        $form = new Form();
        $form->setName('Test Form');
        $form->setAlias('testform');
        $this->em->persist($form);
        $this->em->flush();

        // Fetch the form
        $this->client->request(Request::METHOD_GET, '/s/forms/action/new',
            [
                'formId' => $form->getId(),
                'type'   => 'form.email',
            ],
            [],
            $this->createAjaxHeaders(),
        );
        $this->assertTrue($this->client->getResponse()->isOk());
        $content     = $this->client->getResponse()->getContent();
        $content     = json_decode($content)->newContent;
        $crawler     = new Crawler($content, $this->client->getInternalRequest()->getUri());
        $formCrawler = $crawler->filter('form');
        $this->assertCount(1, $formCrawler);
        $form = $formCrawler->form();

        // Save new Send Form Results action
        $form->setValues([
            'formaction[properties][subject]' => 'Test Japanese',
            'formaction[properties][message]' => '<p style="font-family: メイリオ">Test</p>',
        ]);
        $this->client->submit($form, [], $this->createAjaxHeaders());
        $this->assertTrue($this->client->getResponse()->isOk());
        $content  = $this->client->getResponse()->getContent();
        $content  = json_decode($content)->actionHtml;
        $crawler  = new Crawler($content);
        $editPage = $crawler->filter('.btn-edit')->attr('href');

        // Check the content was not changed
        $this->client->request(Request::METHOD_GET, $editPage, [], [], $this->createAjaxHeaders());
        $this->assertStringContainsString('&lt;p style=&quot;font-family: メイリオ&quot;&gt;Test&lt;/p&gt;', json_decode($this->client->getResponse()->getContent())->newContent);
    }
}
