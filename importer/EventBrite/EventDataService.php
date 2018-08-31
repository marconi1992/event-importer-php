<?php

namespace Importer\EventBrite;

use Importer\Contracts\EventDataService as DataService;
use GuzzleHttp\Client;

class EventDataService implements DataService {

    protected $client;

    public function __construct() {
        $this->client = new Client([
            'base_uri' => 'https://www.eventbriteapi.com/v3/',
        ]);
    }

    public function search($filter, int $page = 1) {
        $response = $this->client->get('events/search', [
            'query' => array_merge(
                $this->makeSearchQuery($filter),
                [
                    'page' => $page,
                ]
            )
        ]);

        $content = $response->getBody()->getContents();

        $content = json_decode($content, true);

        $events = array_map(function($event) {
            return $this->transformEvent($event);
        }, array_get($content, 'events', []));

        return [
            'total' => array_get($content, 'pagination.object_count'),
            'current_page' => $page,
            'size' => array_get($content, 'pagination.page_size'),
            'last_page' => array_get($content, 'pagination.page_count'),
            'items' => $events,
        ];
    }

    private function makeBaseQuery() {
        return [
            'token' => config('services.eventbrite.token'),
        ];
    }

    private function makeSearchQuery($filter) {
        $query = $this->makeBaseQuery();
        $address = $filter['address'] ?? null;
        
        if ($address) {
            $query['location.address'] = $address; 
        }

        return $query;
    }

    private function transformEvent($event) {
        return [
            'external_id' => array_get($event, 'id'),
            'source' => 'eventbrite',
            'name' => array_get($event, 'name.text'),
            'description' => array_get($event, 'description.text'),
            'url' => array_get($event, 'url'),
            'start' => array_get($event, 'start.utc'),
            'end' => array_get($event, 'end.utc'),
            'image_url' => array_get($event, 'logo.original.url'),
            'metadata' => [
                'venue_id' => array_get($event, 'venue_id'),
            ],
        ];
    }
}