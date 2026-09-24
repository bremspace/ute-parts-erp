<?php

if (! function_exists('canSeeField')) {
    /**
     * Check if the current user can see a field-level sensitive column.
     *
     * Permission pattern: 'lihat.{field}' via spatie dot-notation.
     * Finance role can see: harga_beli, margin, profit, cost_price.
     * Super-admin can see everything.
     *
     * @param  string  $field  Field name to check visibility
     */
    function canSeeField(string $field): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        $permissionName = 'lihat.'.$field;
        if ($user->can($permissionName)) {
            return true;
        }
        if ($user->hasRole('finance') && in_array($field, ['harga_beli', 'margin', 'profit', 'cost_price'])) {
            return true;
        }
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return false;
    }
}
