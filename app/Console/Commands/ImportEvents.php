<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MongoDB\Client as MongoClient;
use Importer\EventBrite\EventDataService;
use App\Jobs\ImportVenue;

class ImportEvents extends Command {

    protected $signature = 'import:events {address}';

    protected $description = 'Import events that will happen in a specific address';

    protected $client;

    public function __construct(MongoClient $client)
    {
        parent::__construct();

        $this->client = $client;
    }

    public function handle() {
        $insertedCount = 0;
        $pendingCount = 0;

        /* Mongo storages */
        $eventStorage = $this->client->test->events;
        $venueStorage = $this->client->test->venues;
        $pendingEventStorage = $this->client->test->pendingEvents;
        

        $service = new EventDataService();

        $filter = [
            'address' => $this->argument('address'),
        ];
        
        $page = 1;

        do {
            $pagination = $service->search($filter, $page);
            $lastPage = $pagination['last_page'];
    
            $events = $pagination['items'];
            $ids = array_pluck($events, 'external_id');
            
            $cursor = $eventStorage->find([
                'source' => 'eventbrite',
                'external_id' => [
                    '$in' => $ids,
                ]
            ], [
                'external_id' => true,
            ]);
    
            $importedIds = array_pluck($cursor->toArray(), 'external_id');
    
            $toImportIds = array_diff($ids, $importedIds);
            
            $events = array_filter($events, function($event) use ($toImportIds){
                $id = $event['external_id'];
                return in_array($id, $toImportIds);
            });
    
            $venueIds = array_pluck($events, 'metadata.venue_id');;
    
            $cursor = $venueStorage->find([
                'source' => 'eventbrite',
                'external_id' => [
                    '$in' => $venueIds,
                ]
            ], [
                '_id' => false,
            ]);
            
            $venues = collect($cursor->toArray())->mapWithKeys(function ($venue) {
                return [ $venue['external_id'] => $venue ];
            })->all();
                  
            $toImport = [];
            $pendingEvents = [];
            $toDispatch = [];

            foreach ($events as $event) {
                $venueId = array_get($event, 'metadata.venue_id');
                $venue = $venues[$venueId] ?? null;
    
                if ($venue) {
                    $event['venue'] = $venue;
                    $toImport[] = $event;
                } else {
                    $toDispatch[] = new ImportVenue($venueId);
                    $pendingEvents[] = $event;
                }
            }        
    
            if (!empty($toImport)) {
                $result = $eventStorage->insertMany($toImport);
                $insertedCount += $result->getInsertedCount();
            }
    
            if (!empty($pendingEvents)) {
                $result = $pendingEventStorage->insertMany($pendingEvents);
                $pendingCount += $result->getInsertedCount();
            }

            if (!empty($toDispatch)) {
                foreach ($toDispatch as $job) {
                    dispatch($job);
                }
            }

            $this->line("<info>Imported Events:</info> {$insertedCount}");
            $this->line("<info>Pending Events in Queue with missing Venues:</info> {$pendingCount}");
            $page++;
        } while ($page <= $lastPage);
    }
    
}