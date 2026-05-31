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
}
