<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use AgentEngine\AgentEngineClient;
use AgentEngine\Http\Psr18Transport;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;
use AgentEngine\SymfonyAi\Client\AccessTokenClientFactory;
use AgentEngine\SymfonyAi\Client\ServiceAccountClientFactory;
use AgentEngine\SymfonyAi\Client\TokenProviderClientFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class ClientRegistrar
{
    private const FACTORY_ID = 'agent_engine.client_factory';
    private const TRANSPORT_ID = 'agent_engine.transport';

    /**
     * @param array<string, mixed> $values
     */
    public function register(array $values, ContainerBuilder $builder): void
    {
        $config = new ConfigValues($values);
        $auth = new ConfigValues($config->array('auth'));

        $this->registerFactory($config, $auth, $builder);

        $builder->register('agent_engine.client', AgentEngineClient::class)
            ->setFactory([new Reference(self::FACTORY_ID), 'create']);

        $builder->setAlias(AgentEngineClient::class, 'agent_engine.client');
    }

    private function registerFactory(ConfigValues $config, ConfigValues $auth, ContainerBuilder $builder): void
    {
        if ($auth->optionalString('client_id') !== null) {
            $this->serviceAccount($config, $auth, $builder);

            return;
        }

        if ($auth->optionalString('access_token') !== null) {
            $this->accessToken($config, $auth, $builder);

            return;
        }

        $this->tokenProvider($config, $auth, $builder);
    }

    private function serviceAccount(ConfigValues $config, ConfigValues $auth, ContainerBuilder $builder): void
    {
        $builder->register(self::FACTORY_ID, ServiceAccountClientFactory::class)
            ->setArguments([
                BaseUrl::fromString($config->string('base_url')),
                ProjectId::fromString($config->string('project_id')),
                ClientCredentials::fromStrings($auth->string('client_id'), $auth->string('client_secret')),
                $this->transport($config, $builder),
                $this->contractVersion($config),
                $this->reference($config, 'cache'),
            ]);
    }

    private function accessToken(ConfigValues $config, ConfigValues $auth, ContainerBuilder $builder): void
    {
        $builder->register(self::FACTORY_ID, AccessTokenClientFactory::class)
            ->setArguments([
                BaseUrl::fromString($config->string('base_url')),
                ProjectId::fromString($config->string('project_id')),
                AccessToken::fromParts(
                    $auth->string('access_token'),
                    $this->accessTokenExpiry($auth),
                ),
                $this->transport($config, $builder),
                $this->contractVersion($config),
            ]);
    }

    private function tokenProvider(ConfigValues $config, ConfigValues $auth, ContainerBuilder $builder): void
    {
        $builder->register(self::FACTORY_ID, TokenProviderClientFactory::class)
            ->setArguments([
                BaseUrl::fromString($config->string('base_url')),
                ProjectId::fromString($config->string('project_id')),
                new Reference($auth->string('token_provider')),
                $this->transport($config, $builder),
                $this->contractVersion($config),
            ]);
    }

    private function accessTokenExpiry(ConfigValues $auth): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($auth->string('access_token_expires_at'));
        } catch (\Exception $exception) {
            throw new \InvalidArgumentException('Configuration value "access_token_expires_at" must be a valid date-time.', 0, $exception);
        }
    }

    private function transport(ConfigValues $config, ContainerBuilder $builder): ?Reference
    {
        $transport = $config->optionalString('transport');

        if ($transport !== null) {
            return new Reference($transport);
        }

        $psr18Client = $config->optionalString('psr18_client');

        if ($psr18Client === null) {
            return null;
        }

        $builder->register(self::TRANSPORT_ID, Psr18Transport::class)
            ->setArguments([new Reference($psr18Client)]);

        return new Reference(self::TRANSPORT_ID);
    }

    private function contractVersion(ConfigValues $config): ?ContractVersion
    {
        $version = $config->optionalString('contract_version');

        return $version === null ? null : ContractVersion::fromString($version);
    }

    private function reference(ConfigValues $config, string $key): ?Reference
    {
        $id = $config->optionalString($key);

        return $id === null ? null : new Reference($id);
    }
}
