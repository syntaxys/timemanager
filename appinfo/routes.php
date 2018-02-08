<?php

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#clients', 'url' => '/clients', 'verb' => 'GET'],
		['name' => 'page#addClient', 'url' => '/clients', 'verb' => 'POST'],
		['name' => 'page#deleteClient', 'url' => '/clients/delete', 'verb' => 'POST'],
		['name' => 'page#projects', 'url' => '/projects', 'verb' => 'GET'],
		['name' => 'page#addProject', 'url' => '/projects', 'verb' => 'POST'],
		['name' => 'page#deleteProject', 'url' => '/projects/delete', 'verb' => 'POST'],
		['name' => 'page#tasks', 'url' => '/tasks', 'verb' => 'GET'],
		['name' => 'page#addTask', 'url' => '/tasks', 'verb' => 'POST'],
		['name' => 'page#deleteTask', 'url' => '/tasks/delete', 'verb' => 'POST'],
		['name' => 'page#times', 'url' => '/times', 'verb' => 'GET'],
		['name' => 'page#addTime', 'url' => '/times', 'verb' => 'POST'],
		['name' => 'page#deleteTime', 'url' => '/times/delete', 'verb' => 'POST'],
		['name' => 't_api#get', 'url' => '/api/items', 'verb' => 'GET'],
		['name' => 't_api#post', 'url' => '/api/items', 'verb' => 'POST'],
		['name' => 't_api#updateObjects', 'url' => '/api/updateObjects', 'verb' => 'POST']
	]
];