<?php

namespace App\Modules\Crm\Services;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use Illuminate\Support\Facades\Validator;

/**
 * [T-36] Single source of truth untuk pembuatan pelanggan (CRM-06, POS quick-add, Servis).
 * Aturan validasi + default tier terendah + is_reseller=false dipusatkan di sini agar
 * semua entry point berperilaku identik.
 */
class PelangganService
{
    public function rules(): array
    {
        return [
            'nama' => 'required|string|max:255',
            'telepon' => 'required|string|max:20|unique:pelanggan,telepon',
            'email' => 'nullable|email|unique:pelanggan,email',
            'alamat' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'is_reseller' => 'sometimes|boolean',
            'tier_membership_id' => 'sometimes|nullable|exists:tier_memberships,id',
        ];
    }

    public function create(array $data): Pelanggan
    {
        $validated = Validator::make($data, $this->rules())->validate();

        $tierTerendah = TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan')
            ->first();

        $pelanggan = Pelanggan::create([
            'nama' => $validated['nama'],
            'telepon' => $validated['telepon'],
            'email' => $validated['email'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? null,
            'tier_membership_id' => $validated['tier_membership_id'] ?? $tierTerendah?->id,
            'is_reseller' => (bool) ($validated['is_reseller'] ?? false),
            'total_belanja_12bulan' => 0,
            'poin_loyalty' => 0,
        ]);

        return $pelanggan->load('tierMembership');
    }
}