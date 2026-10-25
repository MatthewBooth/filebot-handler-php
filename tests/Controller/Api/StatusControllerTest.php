<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StatusControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = StatusControllerTest::createClient();
        $client->request('GET', '/api/v1/status');

        self::assertResponseIsSuccessful();
    }
}
