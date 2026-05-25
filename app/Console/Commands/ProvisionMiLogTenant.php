<?php

namespace App\Console\Commands;

use App\ApiKey;
use App\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionMiLogTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'milog:provision-tenant
                            {name : The tenant name}
                            {--key-name=Primary : The display name for the API key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a MiLog tenant and issue a tenant-scoped API key.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenant = Tenant::create([
            'name' => $this->argument('name'),
        ]);

        $rawKey = 'milog_'.Str::random(40);

        $apiKey = ApiKey::create([
            'tenant_id' => $tenant->id,
            'name' => $this->option('key-name'),
            'key_prefix' => ApiKey::keyPrefix($rawKey),
            'key_hash' => ApiKey::hashKey($rawKey),
        ]);

        $this->info('Tenant created.');
        $this->line('Tenant ID: '.$tenant->id);
        $this->line('API Key ID: '.$apiKey->id);
        $this->line('API Key Name: '.$apiKey->name);
        $this->line('API Key Prefix: '.$apiKey->key_prefix);
        $this->line('API Key: '.$rawKey);
        $this->warn('Store the API key now. It is not persisted in plaintext.');

        return self::SUCCESS;
    }
}
