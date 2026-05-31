<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class ApiValidationRules
{
    public static function authLogin(): array
    {
        return [
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public static function authRefresh(): array
    {
        return [
            'refreshToken' => ['required', 'string', 'max:1024'],
        ];
    }

    public static function clientStore(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'vat_number'   => ['required', 'string', 'max:20', 'unique:clients,vat_number'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'city'         => ['nullable', 'string', 'max:100'],
            'postal_code'  => ['nullable', 'string', 'max:10'],
            'country'      => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public static function clientUpdate(int $id): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'vat_number'   => ['required', 'string', 'max:20', Rule::unique('clients', 'vat_number')->ignore($id)],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string'],
            'city'         => ['nullable', 'string', 'max:100'],
            'postal_code'  => ['nullable', 'string', 'max:10'],
            'country'      => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public static function projectStore(): array
    {
        return [
            'client_id'   => ['required', 'integer', 'exists:clients,id'],
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status'      => ['nullable', Rule::in(['active', 'on_hold', 'completed', 'archived'])],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes'       => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public static function projectUpdate(): array
    {
        return self::projectStore();
    }

    public static function referentStore(): array
    {
        return [
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255', 'unique:referents,email'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'fiscal_code' => ['nullable', 'string', 'max:20'],
            'notes'       => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public static function referentUpdate(int $id): array
    {
        return [
            'first_name'  => ['required', 'string', 'max:100'],
            'last_name'   => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255', Rule::unique('referents', 'email')->ignore($id)],
            'phone'       => ['nullable', 'string', 'max:30'],
            'fiscal_code' => ['nullable', 'string', 'max:20'],
            'notes'       => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ];
    }

    public static function deadlineStore(): array
    {
        return [
            'client_id'      => ['required', 'integer', 'exists:clients,id'],
            'project_id'     => ['nullable', 'integer', 'exists:projects,id'],
            'due_date'       => ['required', 'date'],
            'item_type'      => ['required', 'string', 'max:120'],
            'description'    => ['required', 'string', 'max:255'],
            'linked_to'      => ['nullable', 'string', 'max:255'],
            'avada_version'  => ['nullable', 'string', 'max:30'],
            'php_version'    => ['nullable', 'string', 'max:30'],
            'mysql_version'  => ['nullable', 'string', 'max:30'],
            'wp_version'     => ['nullable', 'string', 'max:30'],
            'test_email'     => ['nullable', 'string', 'max:60'],
            'line_ref'       => ['nullable', 'string', 'max:60'],
            'notes'          => ['nullable', 'string', 'max:500'],
            'amount'         => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['nullable', 'boolean'],
        ];
    }

    public static function deadlineUpdate(): array
    {
        return self::deadlineStore();
    }

    public static function userStore(): array
    {
        return [
            'username'        => ['required', 'string', 'max:100', 'unique:users,username'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8', 'max:255'],
            'role'            => ['required', Rule::in(['admin', 'collaborator', 'referent'])],
            'collaborator_id' => ['nullable', 'integer', 'exists:collaborators,id'],
            'referent_id'     => ['nullable', 'integer', 'exists:referents,id'],
        ];
    }

    public static function userChangePassword(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public static function clientAddReferents(): array
    {
        return [
            'user_ids'   => ['nullable', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public static function projectResolveTargetTariff(): array
    {
        return [
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'collaborator_id' => ['nullable', 'integer', 'exists:collaborators,id'],
        ];
    }

    public static function hoursCollaboratorStore(bool $isAdmin): array
    {
        return [
            'project_id'      => ['required', 'integer', 'exists:projects,id'],
            'tariff_id'       => ['required', 'integer', 'exists:tariffs,id'],
            'work_date'       => ['required', 'date'],
            'hours'           => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'collaborator_id' => $isAdmin
                ? ['required', 'integer', 'exists:collaborators,id']
                : ['nullable', 'integer'],
        ];
    }

    public static function hoursCollaboratorBulk(bool $isAdmin): array
    {
        return [
            'rows'                   => ['required', 'array', 'min:1'],
            'rows.*.project_id'      => ['required', 'integer', 'exists:projects,id'],
            'rows.*.tariff_id'       => ['required', 'integer', 'exists:tariffs,id'],
            'rows.*.work_date'       => ['required', 'date'],
            'rows.*.hours'           => ['required', 'numeric', 'min:0.25', 'max:24'],
            'rows.*.description'     => ['nullable', 'string', 'max:2000'],
            'rows.*.collaborator_id' => $isAdmin
                ? ['required', 'integer', 'exists:collaborators,id']
                : ['nullable', 'integer'],
        ];
    }

    public static function hoursMyStore(): array
    {
        return [
            'client_id'   => ['required', 'integer', 'exists:clients,id'],
            'project_id'  => ['nullable', 'integer', 'exists:projects,id'],
            'tariff_id'   => ['required', 'integer', 'exists:tariffs,id'],
            'work_date'   => ['required', 'date'],
            'hours'       => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function hoursMyBulk(): array
    {
        return [
            'rows'                 => ['required', 'array', 'min:1'],
            'rows.*.client_id'     => ['required', 'integer', 'exists:clients,id'],
            'rows.*.project_id'    => ['nullable', 'integer', 'exists:projects,id'],
            'rows.*.tariff_id'     => ['required', 'integer', 'exists:tariffs,id'],
            'rows.*.work_date'     => ['required', 'date'],
            'rows.*.hours'         => ['required', 'numeric', 'min:0.25', 'max:24'],
            'rows.*.description'   => ['nullable', 'string', 'max:2000'],
        ];
    }

    public static function invoicesIndexFilters(): array
    {
        return [
            'year'  => ['nullable', 'integer', 'min:2000', 'required_with:month'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public static function invoicesSimulate(): array
    {
        return [
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.hourly_rate'      => ['required', 'numeric', 'min:0'],
            'items.*.hours'            => ['required', 'numeric', 'min:0.01'],
            'items.*.tax_inclusive'    => ['nullable', 'boolean'],
            'stamp_duty'               => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public static function invoicesStore(): array
    {
        return [
            'invoice_number'             => ['required', 'string', 'max:100'],
            'client_id'                  => ['required', 'integer', 'exists:clients,id'],
            'invoice_date'               => ['required', 'date'],
            'stamp_duty'                 => ['nullable', 'numeric', 'min:0'],
            'subtotal'                   => ['required', 'numeric', 'min:0'],
            'tax_amount'                 => ['required', 'numeric', 'min:0'],
            'total'                      => ['required', 'numeric', 'min:0'],
            'notes'                      => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.description'        => ['nullable', 'string', 'max:2000'],
            'items.*.tariff_id'          => ['required', 'integer', 'exists:tariffs,id'],
            'items.*.hours'              => ['required', 'numeric', 'min:0.01'],
            'items.*.hourly_rate'        => ['required', 'numeric', 'min:0'],
            'items.*.tax_inclusive'      => ['nullable', 'boolean'],
            'items.*.line_total'         => ['required', 'numeric', 'min:0'],
            'items.*.work_hour_ids'      => ['nullable', 'array'],
            'items.*.work_hour_ids.*'    => ['integer', 'exists:my_work_hours,id'],
        ];
    }

    public static function invoicesUpdateStatus(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'sent', 'paid', 'cancelled'])],
        ];
    }

    public static function collabInvoicesIndexFilters(): array
    {
        return [
            'year'            => ['nullable', 'integer', 'min:2000', 'required_with:month'],
            'month'           => ['nullable', 'integer', 'between:1,12'],
            'collaborator_id' => ['nullable', 'integer', 'exists:collaborators,id'],
        ];
    }

    public static function collabInvoicesStore(): array
    {
        return [
            'collaborator_id'            => ['required', 'integer', 'exists:collaborators,id'],
            'invoice_number'             => ['required', 'string', 'max:100'],
            'invoice_date'               => ['required', 'date'],
            'subtotal'                   => ['required', 'numeric', 'min:0'],
            'tax_amount'                 => ['required', 'numeric', 'min:0'],
            'total'                      => ['required', 'numeric', 'min:0'],
            'notes'                      => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.description'        => ['nullable', 'string', 'max:2000'],
            'items.*.tariff_id'          => ['required', 'integer', 'exists:tariffs,id'],
            'items.*.hours'              => ['required', 'numeric', 'min:0.01'],
            'items.*.hourly_rate'        => ['required', 'numeric', 'min:0'],
            'items.*.tax_inclusive'      => ['nullable', 'boolean'],
            'items.*.line_total'         => ['required', 'numeric', 'min:0'],
            'items.*.collab_hour_ids'    => ['nullable', 'array'],
            'items.*.collab_hour_ids.*'  => ['integer', 'exists:collaborator_hours,id'],
        ];
    }

    public static function collabInvoicesUpdateStatus(): array
    {
        return [
            'status' => ['required', Rule::in(['draft', 'sent', 'paid', 'cancelled'])],
        ];
    }

    public static function collabInvoicesMarkPaid(): array
    {
        return [
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
