<?php

declare(strict_types=1);

namespace Mautic\ReportBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReportControllerTest extends MauticMysqlTestCase
{
    /**
     * Smoke test to ensure the '/s/reports' route loads.
     */
    public function testIndexRouteSuccessfullyLoads(): void
    {
        $this->client->request(Request::METHOD_GET, '/s/reports');
        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * Smoke test to ensure the '/s/reports/new' route loads.
     */
    public function testNewRouteSuccessfullyLoads(): void
    {
        $this->client->request(Request::METHOD_GET, '/s/reports/new');
        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}
