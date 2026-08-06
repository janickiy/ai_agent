<?php

declare(strict_types=1);

namespace App\DTO\Settings;

use App\DTO\DataTransferObject;
use InvalidArgumentException;

final readonly class AISettingsData extends DataTransferObject
{
    /** @var list<string> */
    private const SECRET_FIELDS = [
        'auth_key',
        'client_id',
        'client_secret',
        'api_key',
        'iam_token',
    ];

    /** @var list<string> */
    private const PROVIDERS = ['gigachat', 'yandexgpt', 'openai', 'gemini'];

    /**
     * @param  array<string, array<string, mixed>>  $providerSettings
     * @param  array<string, array<string, mixed>>  $providerCredentials
     * @param  array<string, bool>  $clearCredentials
     */
    public function __construct(
        public string $provider,
        public array $providerSettings,
        public array $providerCredentials,
        public array $clearCredentials,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $provider = trim((string) ($data['provider'] ?? ''));
        if ($provider === '') {
            throw new InvalidArgumentException('AI provider is required.');
        }

        $providerSettings = $data['provider_settings'] ?? null;
        if (! is_array($providerSettings)) {
            throw new InvalidArgumentException('AI provider settings must be an array.');
        }

        foreach (self::PROVIDERS as $providerCode) {
            if (! isset($providerSettings[$providerCode]) || ! is_array($providerSettings[$providerCode])) {
                throw new InvalidArgumentException("Settings for AI provider {$providerCode} are required.");
            }
        }

        $providerCredentials = $data['provider_credentials'] ?? [];
        if (! is_array($providerCredentials)) {
            throw new InvalidArgumentException('AI provider credentials must be an array.');
        }
        foreach (self::PROVIDERS as $providerCode) {
            if (isset($providerCredentials[$providerCode]) && ! is_array($providerCredentials[$providerCode])) {
                throw new InvalidArgumentException("Credentials for AI provider {$providerCode} must be an array.");
            }
        }

        return new self(
            provider: $provider,
            providerSettings: $providerSettings,
            providerCredentials: $providerCredentials,
            clearCredentials: array_map(
                static fn (mixed $clear): bool => (bool) $clear,
                (array) ($data['clear_credentials'] ?? []),
            ),
        );
    }

    /**
     * Returns only settings that may be stored in the public AI settings row.
     * Credentials and credential-clear flags are intentionally excluded.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $publicSettings = [];

        foreach ($this->providerSettings as $provider => $settings) {
            $publicSettings[$provider] = array_diff_key(
                $settings,
                array_flip(self::SECRET_FIELDS),
            );
        }

        return ['provider' => $this->provider] + $publicSettings;
    }
}
