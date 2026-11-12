<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Organizations\ProvisionOrganizationAction;
use App\Actions\Organizations\ProvisionOutcome;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The only way to create an organization and its first owner without the database (public sign-up is off).
 */
final class CreateOrganizationCommand extends Command
{
    protected $signature = 'organizations:create
        {name : Display name, e.g. "Accra Campus Creatives"}
        {slug : URL slug, e.g. accra-campus-creatives}
        {--owner= : Email address to invite as the first owner}
        {--force : Required in production}';

    protected $description = 'Create an organization (idempotent) and email its first owner an invitation';

    public function handle(ProvisionOrganizationAction $provision): int
    {
        if ($this->laravel->isProduction() && !$this->option('force')) {
            $this->error('Refusing to run in production without --force.');

            return self::FAILURE;
        }

        $owner = $this->option('owner');

        if (!is_string($owner) || $owner === '') {
            $this->error('The --owner email is required.');

            return self::FAILURE;
        }

        try {
            $result = $provision->execute((string) $this->argument('name'), (string) $this->argument('slug'), $owner);
        } catch (ValidationException $e) {
            foreach ($e->validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $organization = $result['organization'];

        Log::channel('audit')->info('organizations.create_command', [
            'organization_id' => $organization->id,
            'created' => $result['created'],
            'outcome' => $result['outcome']->value,
        ]);

        $this->info(
            $result['created']
                ? "Created organization \"{$organization->name}\" ({$organization->slug})."
                : "Organization {$organization->slug} already exists; left unchanged.",
        );

        return match ($result['outcome']) {
            ProvisionOutcome::Invited => $this->done("Owner invitation emailed to {$owner}."),
            ProvisionOutcome::AlreadyOwner => $this->done("{$owner} is already an owner."),
            ProvisionOutcome::InvitationPending => $this->done(
                "An owner invitation to {$owner} is still pending; not sent again.",
            ),
            ProvisionOutcome::MemberWithOtherRole => $this->failWith(
                "{$owner} is already a member with another role. An owner can change it on the members page.",
            ),
        };
    }

    private function done(string $message): int
    {
        $this->info($message);

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
