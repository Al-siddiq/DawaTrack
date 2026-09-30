<?php

declare(strict_types=1);

namespace App\Modules\TenantShop\Application;

use App\Exceptions\ApiException;
use App\Modules\Audit\Application\AuditService;
use App\Modules\TenantShop\Infrastructure\ShopContext;
use App\Support\UtcTimestamp;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class ShopSettingsService
{
    private const EDITABLE_FIELDS = [
        'name',
        'contact_phone',
        'contact_email',
        'address_line_1',
        'address_line_2',
        'city_town',
        'state_region',
        'timezone_name',
    ];

    public function __construct(
        private readonly BaseConnection $database,
        private readonly AuditService $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function get(ShopContext $context): array
    {
        return $this->database->table('shops')
            ->select('public_id,name,contact_phone,contact_email,address_line_1,address_line_2,city_town,state_region,country_code,timezone_name,currency_code,status')
            ->where(['id' => $context->shopId, 'tenant_id' => $context->tenantId])
            ->get()
            ->getRowArray() ?? [];
    }

    /** @param array<string, mixed> $input */
    public function update(ShopContext $context, array $input): array
    {
        $context->requireOwner();
        $current = $this->get($context);
        if ($current === []) {
            throw new ApiException('RESOURCE_NOT_FOUND', 'The Shop was not found.', 404);
        }
        $normalized = $this->normalizeAndValidate($input);
        $changes = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if (($current[$field] ?? null) !== $normalized[$field]) {
                $changes[$field] = ['before' => $current[$field] ?? null, 'after' => $normalized[$field]];
            }
        }
        if ($changes === []) {
            throw new ApiException('SHOP_SETTINGS_UNCHANGED', 'No Shop setting changed.', 409);
        }

        $update = [];
        foreach ($changes as $field => $change) {
            $update[$field] = $change['after'];
        }
        $update['updated_at'] = UtcTimestamp::now();

        $this->database->transException(true)->transBegin();
        try {
            $this->database->table('shops')->where([
                'id' => $context->shopId,
                'tenant_id' => $context->tenantId,
            ])->update($update);
            $this->audit->record([
                'tenant_id' => $context->tenantId,
                'shop_id' => $context->shopId,
                'actor_type' => 'USER',
                'actor_user_id' => $context->userId,
                'actor_membership_id' => $context->membershipId,
                'action_code' => 'shop.profile_updated',
                'category' => 'TENANT',
                'resource_type' => 'shop',
                'resource_public_id' => $current['public_id'],
                'change_metadata' => ['changes' => $changes],
            ]);
            $this->database->transCommit();
        } catch (Throwable $exception) {
            $this->database->transRollback();
            throw $exception;
        }

        return $this->get($context);
    }

    /** @return array<string, string|null> */
    private function normalizeAndValidate(array $input): array
    {
        $data = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            $data[$field] = trim((string) ($input[$field] ?? ''));
        }
        $submittedPhone = $data['contact_phone'];
        $data['contact_email'] = strtolower($data['contact_email']);
        $data['contact_phone'] = $this->normalizePhone($data['contact_phone']);
        foreach (['contact_phone', 'contact_email', 'address_line_2'] as $optional) {
            $data[$optional] = $data[$optional] === '' ? null : $data[$optional];
        }

        $errors = [];
        foreach (['name', 'address_line_1', 'city_town', 'state_region', 'timezone_name'] as $required) {
            if ($data[$required] === '') {
                $errors[$required] = ['This field is required.'];
            }
        }
        foreach (['name' => 160, 'contact_phone' => 32, 'contact_email' => 254, 'address_line_1' => 180, 'address_line_2' => 180, 'city_town' => 100, 'state_region' => 100, 'timezone_name' => 64] as $field => $maximum) {
            if ($data[$field] !== null && mb_strlen((string) $data[$field]) > $maximum) {
                $errors[$field] = ["Use no more than {$maximum} characters."];
            }
        }
        if ($data['contact_email'] !== null && ! filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = ['Enter a valid email address.'];
        }
        if ($submittedPhone !== '' && preg_match('/^\+?[0-9().\s-]+$/', $submittedPhone) !== 1) {
            $errors['contact_phone'] = ['Use only digits and common phone separators.'];
        } elseif ($data['contact_phone'] !== null && preg_match('/^\+?[0-9]{7,15}$/', $data['contact_phone']) !== 1) {
            $errors['contact_phone'] = ['Enter 7 to 15 digits, optionally beginning with +.'];
        }
        if (! in_array($data['timezone_name'], timezone_identifiers_list(), true)) {
            $errors['timezone_name'] = ['Choose a valid IANA timezone.'];
        }
        if ($errors !== []) {
            throw new ApiException('VALIDATION_FAILED', 'Correct the highlighted Shop settings.', 422, $errors);
        }

        return $data;
    }

    private function normalizePhone(string $phone): string
    {
        if ($phone === '') {
            return '';
        }
        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return ($hasPlus ? '+' : '') . $digits;
    }
}
