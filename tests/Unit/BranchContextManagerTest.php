<?php

namespace Tests\Unit;

use App\Modules\Crm\Models\Lead;
use App\Modules\Rbac\Models\Cabang;
use App\Services\BranchContextManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchContextManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang1;

    protected Cabang $cabang2;

    protected BranchContextManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang1 = Cabang::create([
            'kode' => 'CBG-01',
            'nama' => 'Cabang Surabaya',
            'is_active' => true,
        ]);

        $this->cabang2 = Cabang::create([
            'kode' => 'CBG-02',
            'nama' => 'Cabang Malang',
            'is_active' => true,
        ]);

        $this->manager = app(BranchContextManager::class);
        $this->manager->clear();
    }

    public function test_can_set_and_get_active_branch(): void
    {
        $this->assertNull($this->manager->getActiveCabangId());

        $this->manager->setActiveCabangId($this->cabang1->id);
        $this->assertEquals($this->cabang1->id, $this->manager->getActiveCabangId());
        $this->assertEquals('Cabang Surabaya', $this->manager->getActiveCabang()?->nama);

        $this->manager->clear();
        $this->assertNull($this->manager->getActiveCabangId());
    }

    public function test_run_in_branch_context_isolates_and_restores(): void
    {
        $this->manager->setActiveCabangId($this->cabang1->id);

        $innerResult = $this->manager->runInBranchContext($this->cabang2->id, function () {
            $this->assertEquals($this->cabang2->id, $this->manager->getActiveCabangId());

            return 'executed_in_c2';
        });

        $this->assertEquals('executed_in_c2', $innerResult);
        // Pastikan kembali ke cabang 1
        $this->assertEquals($this->cabang1->id, $this->manager->getActiveCabangId());
    }

    public function test_belongs_to_cabang_scopes_queries_correctly(): void
    {
        $lead1 = Lead::create([
            'cabang_id' => $this->cabang1->id,
            'nama' => 'Customer Cabang 1',
            'stage' => 'baru',
            'sumber' => 'walkin',
        ]);

        $lead2 = Lead::create([
            'cabang_id' => $this->cabang2->id,
            'nama' => 'Customer Cabang 2',
            'stage' => 'baru',
            'sumber' => 'walkin',
        ]);

        // 1. Saat cabang 1 aktif, query Lead hanya mengembalikan data cabang 1
        $this->manager->setActiveCabangId($this->cabang1->id);
        $resultsC1 = Lead::all();
        $this->assertCount(1, $resultsC1);
        $this->assertEquals('Customer Cabang 1', $resultsC1->first()->nama);

        // 2. Saat cabang 2 aktif, query Lead hanya mengembalikan data cabang 2
        $this->manager->setActiveCabangId($this->cabang2->id);
        $resultsC2 = Lead::all();
        $this->assertCount(1, $resultsC2);
        $this->assertEquals('Customer Cabang 2', $resultsC2->first()->nama);

        // 3. Saat withoutCabangScope dipakai, seluruh cabang muncul (mis. laporan konsolidasi)
        $allLeads = Lead::withoutCabangScope()->get();
        $this->assertCount(2, $allLeads);

        // 4. Saat withoutScope context dipakai
        $this->manager->withoutScope(function () {
            $this->assertCount(2, Lead::all());
        });
    }

    public function test_belongs_to_cabang_auto_fills_cabang_id_on_create(): void
    {
        $this->manager->setActiveCabangId($this->cabang1->id);

        // Create tanpa menyertakan cabang_id
        $lead = Lead::create([
            'nama' => 'Auto Branch Customer',
            'stage' => 'baru',
            'sumber' => 'walkin',
        ]);

        $this->assertEquals($this->cabang1->id, $lead->cabang_id);
    }
}
