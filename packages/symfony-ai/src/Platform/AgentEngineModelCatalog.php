<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\ModelNotFoundException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;

final readonly class AgentEngineModelCatalog implements ModelCatalogInterface
{
    /**
     * @param array<string, list<Capability>> $capabilities
     */
    private function __construct(private array $capabilities)
    {
    }

    public static function for(WorkspaceMap $workspaces): self
    {
        $capabilities = [];

        foreach ($workspaces->modelNames() as $name) {
            $capabilities[$name] = [
                Capability::INPUT_TEXT,
                Capability::OUTPUT_TEXT,
                Capability::OUTPUT_STREAMING,
            ];
        }

        return new self($capabilities);
    }

    public function getModel(string $modelName): Model
    {
        if (!isset($this->capabilities[$modelName])) {
            throw new ModelNotFoundException(sprintf('Model "%s" is not configured for the Agent Engine provider.', $modelName));
        }

        return new Model($modelName, $this->capabilities[$modelName]);
    }

    /**
     * @return array<string, array{class: string, capabilities: list<Capability>}>
     */
    public function getModels(): array
    {
        return array_map(
            static fn (array $capabilities): array => [
                'class' => Model::class,
                'capabilities' => $capabilities,
            ],
            $this->capabilities,
        );
    }
}
