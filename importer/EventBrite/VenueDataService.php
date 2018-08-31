<?php

namespace Importer\EventBrite;

use Importer\Contracts\VenueDataService as DataService;
use GuzzleHttp\Client;

class VenueDataService implements DataService {

    public function __construct() {
        $this->client = new Client([
            'base_uri' => 'https://www.eventbriteapi.com/v3/',
        ]);
    }

    public function findById($id) {
        $response = $this->client->get("venues/$id", [
            'query' => $this->makeBaseQuery(),
        ]);

        $content = $response->getBody()->getContents();
        
        $content = json_decode($content, true);

        return [
            'external_id' => array_get($content, 'id'),
            'source' => 'eventbrite',
            'name' => array_get($content, 'name'),
            'address' => array_get($content, 'address.localized_address_display'),
        ];
    }

    private function makeBaseQuery() {
        return [
            'token' => config('services.eventbrite.token'),
        ];
    }
}