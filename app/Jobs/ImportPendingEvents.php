<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Importer\EventBrite\VenueDataService;
use MongoDB\Client as MongoClient;
use App\Events\VenueCreated;

class ImportPendingEvents implements ShouldQueue
{
    use Queueable;

    protected $venue;

    public function __construct($venue)
    {
        $this->venue = $venue;
    }

    public function handle(MongoClient $client)
    {
        $venue = $this->venue;

        $pendingEventStorage = $client->test->pendingEvents;
        $eventStorage = $client->test->events;

        $cursor = $pendingEventStorage->find([
            'source' => 'eventbrite',
            'metadata.venue_id' => $venue['external_id'],
        ], [
            '_id' => false,
        ]);
        
        $pendingEvents = $cursor->toArray();
        
        $pendingEvents = array_map(function ($event) use ($venue) {
            $event['venue'] = $venue;
            return $event;
        }, $pendingEvents);

        if (!empty($pendingEvents)) {
            $result = $eventStorage->insertMany($pendingEvents);
            $pendingEventStorage->deleteMany([
                'source' => 'eventbrite',
                'metadata.venue_id' => $venue['external_id'],
            ]);
        }
    }
}
