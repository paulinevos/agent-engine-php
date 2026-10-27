<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

final readonly class GitInfo
{
    public function __construct(
        public ?string $commitSha,
        public ?string $branch,
        public bool $dirty,
    ) {
    }

    public static function forDirectory(string $directory): self
    {
        $status = self::run(['git', '-C', $directory, 'status', '--porcelain']);

        return new self(
            commitSha: self::run(['git', '-C', $directory, 'rev-parse', 'HEAD']),
            branch: self::run(['git', '-C', $directory, 'rev-parse', '--abbrev-ref', 'HEAD']),
            dirty: $status !== null,
        );
    }

    /**
     * @param list<string> $command
     */
    private static function run(array $command): ?string
    {
        $process = @proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return null;
        }

        $trimmed = trim((string) $output);

        return $trimmed === '' ? null : $trimmed;
    }
}
