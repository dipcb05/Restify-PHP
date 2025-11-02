# API Endpoints

Drop PHP files in this directory to define HTTP endpoints. Restify automatically discovers them at runtime.

Each file receives an \ helper:

`php
<?php

use Restify\Http\Request;

->get(static fn () => ['message' => 'Hello world']);
->post(static fn (Request ) => ->body);
`

You can also return a configuration array with GET, POST, PATH, and FALLBACK keys if you prefer declarative routing.
