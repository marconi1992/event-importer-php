<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use MongoDB\Client as MongoClient;
use Importer\EventBrite\EventDataService;
use Importer\EventBrite\VenueDataService;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('/eventbrite/events', function (Request $request) {
    $filter = [];
    $page = $request->get('page', 1);

    if ($request->has('address')) {
        $filter['address'] = $request->get('address');
    }

    $service = new EventDataService();
    $events = $service->search($filter, $page);

    return response($events);
});

$router->get('/eventbrite/venues/{id}', function ($id) {
    $service = new VenueDataService();
    $venue = $service->findById($id);

    return response($venue);
});

$router->get('/events', function(Request $request, MongoClient $client) {
    $collection = $client->test->events;
    $filter  = [];

    $page = $request->get('page', 1);
    $size = intval($request->get('size', 10));

    if ($request->has('q')) {
        $filter['$text'] = [ '$search' => $request->get('q')];
    }

    $skip = $size * ($page - 1);

    $cursor = $collection->find($filter, [ 'limit' => $size, 'skip' => $skip ]);

    $count = $collection->count($filter);
    return response([
        'total' => $count,
        'last_page' =>  ceil( $count / $size ),
        'current_page' => intval($page),
        'size' => $size,
        'items' => $cursor->toArray(),
    ]);
});

$router->get('/venues', function(MongoClient $client) {
    $collection = $client->test->venues;
    $venues = $collection->find()->toArray();

    return response($venues);
});

$router->post('/events', function (Request $request, MongoClient $client) {
    $payload = $request->all();
    $validator = Validator::make($payload, [
        'name' => 'required',
    ]);

    if ($validator->fails()) {
        return response($validator->errors()->messages(), 422);
    }

    $collection = $client->test->events;

    $id = $collection->insertOne($payload)->getInsertedId();

    $event = $collection->findOne(['_id'=> new MongoDB\BSON\ObjectId($id)]);

    return response($event, 201);
});

$router->put('/events/{id}', function (Request $request, MongoClient $client, $id) {
    $payload = $request->all();

    $validator = Validator::make($payload, [
        'name' => 'required',
    ]);

    if ($validator->fails()) {
        return response($validator->errors()->messages(), 422);
    }

    $collection = $client->test->events;

    $result = $collection->updateOne([
        '_id'=> new MongoDB\BSON\ObjectId($id)
    ], [
        '$set' => $payload
    ]);

    $failed = !$result->getMatchedCount(); 
    
    if ($failed) {
        return response([
            'message' => "Event: $id not exist"
        ], 404);
    };

    $event = $collection->findOne(['_id'=> new MongoDB\BSON\ObjectId($id)]);

    return response($event);
});
