<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CliOutput
{
    public static function error(
        OutputInterface $output,
        string $message,
        bool $json,
        int $exitCode,
    ): int {
        if ($json) {
            $output->writeln((string) json_encode(['error' => $message], JSON_UNESCAPED_SLASHES));

            return $exitCode;
        }

        self::errorOutput($output)->writeln($message);

        return $exitCode;
    }

    public static function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
