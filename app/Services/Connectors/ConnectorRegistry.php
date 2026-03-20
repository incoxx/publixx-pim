<?php

declare(strict_types=1);

namespace App\Services\Connectors;

class ConnectorRegistry
{
    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    /**
     * Registriert einen Connector.
     */
    public function register(ConnectorInterface $connector): void
    {
        $this->connectors[$connector->getType()] = $connector;
    }

    /**
     * Gibt einen Connector anhand des Typs zurück.
     */
    public function get(string $type): ?ConnectorInterface
    {
        return $this->connectors[$type] ?? null;

    }

    /**
     * Gibt alle registrierten Connectoren zurück.
     *
     * @return array<string, ConnectorInterface>
     */
    public function all(): array
    {
        return $this->connectors;
    }

    /**
     * Gibt nur die konfigurierten Connectoren zurück.
     *
     * @return array<string, ConnectorInterface>
     */
    public function configured(): array
    {
        return array_filter($this->connectors, fn (ConnectorInterface $c) => $c->isConfigured());
    }

    /**
     * Gibt Connector-Infos als Array für die API zurück.
     */
    public function toArray(): array
    {
        return array_map(fn (ConnectorInterface $c) => [
            'type'         => $c->getType(),
            'name'         => $c->getName(),
            'description'  => $c->getDescription(),
            'capabilities' => $c->getCapabilities(),
            'configured'   => $c->isConfigured(),
        ], $this->connectors);
    }
}
