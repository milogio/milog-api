<?php

namespace App\Console\Commands;

use App\Tenant;
use App\User;
use Illuminate\Console\Command;

class GrantTenantMembership extends Command
{
    protected $signature = 'milog:grant-tenant
                            {email : Existing user email}
                            {tenant : Tenant UUID}
                            {--role=member : owner, admin, or member}';

    protected $description = 'Grant or update a user membership in a MiLog tenant.';

    public function handle()
    {
        $role = $this->option('role');

        if (! in_array($role, config('milog.ui_auth.roles', []), true)) {
            $this->error('Role must be one of: '.implode(', ', config('milog.ui_auth.roles', [])));

            return self::INVALID;
        }

        $user = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($this->argument('email')))])->first();
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $user || ! $tenant) {
            $this->error('The user or tenant was not found.');

            return self::FAILURE;
        }

        $user->tenants()->syncWithoutDetaching([
            $tenant->id => ['role' => $role, 'status' => 'active'],
        ]);

        $this->info('Tenant membership granted.');

        return self::SUCCESS;
    }
}
