<?php

namespace App\Tests\Controller;

use App\Message\FilebotJobMessage;
use App\Service\FilebotJobHistoryStoreInterface;
use App\Service\FilebotJobPendingStatusReaderInterface;
use App\Tests\Support\InMemoryFilebotJobHistoryStore;
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
        self::getContainer()->set(FilebotJobHistoryStoreInterface::class, new InMemoryFilebotJobHistoryStore());
        $transport->enqueue('100-0', new FilebotJobMessage('/media/Arrival/Arrival.mkv', 'movie'));
        $transport->enqueue('101-0', new FilebotJobMessage('/media/Planet Earth III', 'tv', action: 'move'));
        $transport->markPending('101-0', 'worker-1', 900, 2);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#queued-count', '1');
        self::assertSelectorTextContains('#processing-count', '1');
        self::assertSelectorTextContains('#total-count', '2');
        self::assertSelectorExists('tr[data-job-id="100-0"]');
        self::assertSelectorTextNotContains('thead', 'ID');
        self::assertSelectorTextNotContains('tr[data-job-id="100-0"]', '100-0');
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
        self::getContainer()->set(FilebotJobHistoryStoreInterface::class, new InMemoryFilebotJobHistoryStore());

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

    public function testDashboardPaginatesHistoryTenJobsAtATimeAndKeepsSelectedPageOnRefresh(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $transport = new ListableInMemoryTransport();
        self::getContainer()->set('messenger.transport.async', $transport);
        self::getContainer()->set(FilebotJobPendingStatusReaderInterface::class, $transport);
        $history = new InMemoryFilebotJobHistoryStore();
        self::getContainer()->set(FilebotJobHistoryStoreInterface::class, $history);

        for ($index = 1; $index <= 12; ++$index) {
            $job = new FilebotJobMessage('/media/History '.$index, 'movie');
            $history->recordQueued($job);
            $history->markCompleted($job);
        }

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertCount(10, $client->getCrawler()->filter('section[aria-labelledby="job-history-title"] tbody tr'));
        self::assertSelectorTextContains('section[aria-labelledby="job-history-title"]', '12 completed');
        self::assertSelectorExists('a[href="/?page=2"]');
        self::assertSelectorExists('[data-dashboard-refresh-url-value="/dashboard/live?page=1"]');

        $client->request('GET', '/?page=2');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $client->getCrawler()->filter('section[aria-labelledby="job-history-title"] tbody tr'));
        self::assertSelectorTextContains('section[aria-labelledby="job-history-title"]', 'History 12');
        self::assertSelectorExists('[data-dashboard-refresh-url-value="/dashboard/live?page=2"]');
    }
}
