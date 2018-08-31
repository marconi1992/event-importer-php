<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Importer\EventBrite\VenueDataService;
use MongoDB\Client as MongoClient;
use App\Events\VenueCreated;
use App\Jobs\ImportPendingEvents;

class ImportVenue implements ShouldQueue
{
    use Queueable;

    protected $venueId;

    protected $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($venueId)
    {
        $this->venueId = $venueId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(MongoClient $client)
    {
        $this->client = $client;
        if ($this->isUnique()) {
            $service = new VenueDataService();
            
            $venue = $service->findById($this->venueId);
    
            if ($venue) {
                $collection = $client->test->venues;
    
                $collection->insertOne($venue);
            }
            dispatch(new ImportPendingEvents($venue));
        }
    }

    private function isUnique() {
        $queueStorage = $this->client->test->venues;

        $unique = $queueStorage->findOne([
            'external_id' => $this->uniqueable()
        ]);

        return empty($unique);
    }

    private function uniqueable() {
        return $this->venueId;
    }
}
