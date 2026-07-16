<?php

namespace App\Contracts\Integrations;

/**
 * Contrato obrigatório para qualquer provider de integração externa.
 */
interface IntegrationProviderInterface
{
    public function getProviderCode(): string;

    /** Alias semântico para getProviderCode(). */
    public function providerName(): string;

    public function getDisplayName(): string;

    /** @return array<string, string> */
    public function getAvailableResources(): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateConfiguration(array $config): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function testConnection(array $config): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function healthCheck(array $config): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    public function getRemoteCompany(array $config): ?array;

  /**
     * @param  array<string, mixed>  $config
     */
    public function getRemoteEnvironment(array $config): ?string;

    /**
     * @param  array<string, mixed>  $config
     */
    public function getRemoteVersion(array $config): ?string;

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function sync(array $config): array;
}
