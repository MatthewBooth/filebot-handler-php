<?php

namespace App\Tests\Controller;

use App\Message\FilebotJobMessage;
use App\Service\FilebotJobPendingStatusReaderInterface;
use App\Tests\Support\ListableInMemoryTransport;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DashboardControllerIntegrationTest extends WebTestCase
{
    public function testDashboardShowsLiveQueuedAndProcessingJobs(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $transport = new ListableInMemoryTransport();
        self::getContainer()->set('messenger.transport.async', $transport);
        self::getContainer()->set(FilebotJobPendingStatusReaderInterface::class, $transport);
        $transport->enqueue('100-0', new FilebotJobMessage('/media/Arrival/Arrival.mkv', 'movie'));
        $transport->enqueue('101-0', new FilebotJobMessage('/media/Planet Earth III', 'tv', action: 'move'));
        $transport->markPending('101-0', 'worker-1', 900, 2);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#queued-count', '1');
        self::assertSelectorTextContains('#processing-count', '1');
        self::assertSelectorTextContains('#total-count', '2');
        self::assertSelectorExists('tr[data-job-id="100-0"]');
        self::assertSelectorTextContains('tr[data-job-id="100-0"]', '/media/Arrival/Arrival.mkv');
        self::assertSelectorTextContains('tr[data-job-id="100-0"]', 'Movie');
        self::assertSelectorTextContains('#worker-count', '1');
        self::assertSelectorTextContains('section[aria-labelledby="workers-title"]', 'worker-1');
        self::assertSelectorTextContains('section[aria-labelledby="workers-title"]', '/media/Planet Earth III');
        self::assertSelectorTextContains('tr[data-job-id="101-0"]', 'Move');
        self::assertSelectorExists('a[href="/api/jobs/100-0"]');
        self::assertSelectorExists('form[action="/api/jobs"][data-controller="job-form"]');
        self::assertSelectorExists('input[name="path"][required]');
        self::assertSelectorExists('tr[data-job-id="100-0"] button[data-action="job-list#delete"][data-url="/api/jobs/100-0"]');
        self::assertSelectorNotExists('tr[data-job-id="101-0"] button[data-action="job-list#delete"]');
        self::assertSelectorNotExists('.sample-job');
    }

    public function testDashboardShowsEmptyQueueState(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $transport = new ListableInMemoryTransport();
        self::getContainer()->set('messenger.transport.async', $transport);
        self::getContainer()->set(FilebotJobPendingStatusReaderInterface::class, $transport);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#queued-count', '0');
        self::assertSelectorTextContains('#processing-count', '0');
        self::assertSelectorTextContains('#total-count', '0');
        self::assertSelectorTextContains('tbody', 'There are no queued or processing jobs.');
    }

    public function testDashboardShowsQueueUnavailableStateWhenTransportCannotBeListed(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->set('messenger.transport.async', new InMemoryTransport());
        self::getContainer()->set(FilebotJobPendingStatusReaderInterface::class, new ListableInMemoryTransport());

        $client->request('GET', '/');

        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('.alert-danger', 'The job queue is unavailable right now.');
        self::assertSelectorTextContains('#queued-count', '—');
        self::assertSelectorTextContains('#processing-count', '—');
        self::assertSelectorTextContains('#total-count', '—');
    }
}
