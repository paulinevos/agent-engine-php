<?php

declare(strict_types=1);

use AgentEngine\Invocation\InvokeRequest;

require __DIR__ . '/bootstrap.php';

$message = $argv[1] ?? 'Hello, agent!';

$result = example_client()->invocations->invoke(
    example_workspace(),
    InvokeRequest::message($message),
);

echo $result->text(), PHP_EOL;
fwrite(STDERR, 'execution: ' . $result->executionId->value . PHP_EOL);
fwrite(STDERR, 'session: ' . ($result->sessionId?->value ?? '(none)') . PHP_EOL);
