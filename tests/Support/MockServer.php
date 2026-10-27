<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Support;

final class MockServer
{
    /**
     * @param resource $process
     */
    private function __construct(
        private $process,
        public readonly string $baseUrl,
        public readonly string $requestLog,
        private readonly string $serverLog,
    ) {
    }

    public static function start(): self
    {
        $port = self::freePort();
        $requestLog = self::temporaryFile('agent-engine-requests-');
        $serverLog = self::temporaryFile('agent-engine-server-');

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/mock-server.php'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $serverLog, 'a'],
                2 => ['file', $serverLog, 'a'],
            ],
            $pipes,
            null,
            [...getenv(), 'MOCK_REQUEST_LOG' => $requestLog],
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('The mock server could not be started.');
        }

        self::waitUntilListening($port, $serverLog);

        return new self($process, 'http://127.0.0.1:' . $port, $requestLog, $serverLog);
    }

    /**
     * @return list<MockRequest>
     */
    public function requests(): array
    {
        $contents = trim((string) file_get_contents($this->requestLog));

        if ($contents === '') {
            return [];
        }

        return array_map(
            static fn (string $line): MockRequest => MockRequest::fromArray(
                json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            ),
            explode("\n", $contents),
        );
    }

    public function lastRequest(): ?MockRequest
    {
        $requests = $this->requests();

        return $requests === [] ? null : $requests[array_key_last($requests)];
    }

    public function clearRequests(): void
    {
        file_put_contents($this->requestLog, '');
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);

        @unlink($this->requestLog);
        @unlink($this->serverLog);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        if ($socket === false) {
            throw new \RuntimeException('No free port: ' . $error);
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function waitUntilListening(int $port, string $serverLog): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            usleep(50_000);
        }

        throw new \RuntimeException(
            'The mock server did not start. Log: ' . (string) file_get_contents($serverLog),
        );
    }

    private static function temporaryFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            throw new \RuntimeException('Could not create a temporary file.');
        }

        return $path;
    }
}
