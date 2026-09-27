<?php

declare(strict_types=1);

namespace Integrator;

use Integrator\NeoFeeder\NeoFeederClient;
use Integrator\Siakad\SiakadClient;
use Integrator\Support\Auth;
use Integrator\Support\Config;
use Integrator\Support\Crypto;
use Integrator\Support\Database;
use Integrator\Support\Http;
use Integrator\Support\Settings;
use Integrator\Support\View;
use Integrator\Sync\FieldMapper;
use Integrator\Sync\MappingRepository;
use Integrator\Sync\ReferenceResolver;
use Integrator\Sync\Registry;
use Integrator\Sync\SyncLogRepository;
use Integrator\Sync\SyncRunner;

/**
 * Dependency holder for the integrator application.
 *
 * Deliberately hand-rolled (no framework, no container library): the whole tool is a
 * bridge between two systems, and keeping it dependency-free means it can be dropped
 * on any PHP 8.2+ server next to the Neo Feeder installation.
 */
final class App
{
    private ?Database $database = null;

    private ?Settings $settings = null;

    private ?Crypto $crypto = null;

    private ?View $view = null;

    private ?Auth $auth = null;

    private ?SiakadClient $siakad = null;

    private ?NeoFeederClient $feeder = null;

    private ?SyncRunner $runner = null;

    private ?Registry $registry = null;

    private ?FieldMapper $mapper = null;

    public function __construct(private readonly string $root)
    {
    }

    public function root(string $path = ''): string
    {
        return Config::root($path);
    }

    public function database(): Database
    {
        return $this->database ??= new Database($this->root('storage/integrator.sqlite'));
    }

    public function crypto(): Crypto
    {
        return $this->crypto ??= new Crypto(Config::appKey());
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->database(), $this->crypto());
    }

    public function view(): View
    {
        return $this->view ??= new View($this->root('templates'), ['app' => $this]);
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this->database());
    }

    /**
     * HTTP client shared by both upstreams, honouring the per-upstream SSL switch.
     */
    public function http(bool $verifySsl): Http
    {
        return new Http(timeout: 30, retries: 2, verifySsl: $verifySsl);
    }

    public function siakad(): SiakadClient
    {
        return $this->siakad ??= new SiakadClient(
            http: $this->http($this->settings()->bool('siakad_verify_ssl', true)),
            baseUrl: (string) $this->settings()->get('siakad_base_url'),
            apiKey: (string) $this->settings()->get('siakad_api_key'),
            verifySsl: $this->settings()->bool('siakad_verify_ssl', true)
        );
    }

    public function feeder(): NeoFeederClient
    {
        return $this->feeder ??= new NeoFeederClient(
            http: $this->http($this->settings()->bool('feeder_verify_ssl', false)),
            baseUrl: (string) $this->settings()->get('feeder_base_url'),
            username: (string) $this->settings()->get('feeder_username'),
            password: (string) $this->settings()->get('feeder_password'),
            sandbox: $this->settings()->bool('feeder_sandbox', true),
            cachePath: $this->root('storage/cache/feeder-token.json'),
            verifySsl: $this->settings()->bool('feeder_verify_ssl', false)
        );
    }

    /**
     * Rebuild the upstream clients, e.g. right after the settings were saved.
     */
    public function refreshClients(): void
    {
        $this->siakad = null;
        $this->feeder = null;
        $this->runner = null;
    }

    public function mapper(): FieldMapper
    {
        return $this->mapper ??= FieldMapper::load($this->root('config/feeder_mapping.php'));
    }

    public function registry(): Registry
    {
        return $this->registry ??= new Registry($this->mapper());
    }

    public function mappings(): MappingRepository
    {
        return new MappingRepository($this->database());
    }

    public function references(): ReferenceResolver
    {
        return new ReferenceResolver($this->database());
    }

    public function logRepository(): SyncLogRepository
    {
        return new SyncLogRepository($this->database());
    }

    public function runner(): SyncRunner
    {
        return $this->runner ??= new SyncRunner(
            siakad: $this->siakad(),
            feeder: $this->feeder(),
            db: $this->database(),
            registry: $this->registry(),
            mappings: $this->mappings(),
            references: $this->references(),
            logRepository: $this->logRepository()
        );
    }

    /**
     * Ensure storage directories exist and the schema is up to date.
     */
    public function boot(): void
    {
        Config::ensureEnvironment();

        foreach (['storage', 'storage/cache', 'storage/logs'] as $directory) {
            $path = $this->root($directory);

            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }

        $this->database()->migrate();
    }
}
