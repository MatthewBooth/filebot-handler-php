<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = HealthControllerTest::createClient();
        $client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
    }
}
