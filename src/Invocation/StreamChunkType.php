<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

enum StreamChunkType: string
{
    case Text = 'text';
    case Step = 'step';
    case SubagentStart = 'subagent_start';
    case SubagentEnd = 'subagent_end';
    case Done = 'done';
    case Error = 'error';
    case Custom = 'custom';
}
