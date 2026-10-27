<?php

declare(strict_types=1);

use AgentEngine\Invocation\InvokeRequest;

require __DIR__ . '/bootstrap.php';

$message = $argv[1] ?? 'Draft a short release note.';

$stream = example_client()->invocations->stream(
    example_workspace(),
    InvokeRequest::message($message),
);

foreach ($stream->textDeltas() as $delta) {
    echo $delta;
}

echo PHP_EOL;
