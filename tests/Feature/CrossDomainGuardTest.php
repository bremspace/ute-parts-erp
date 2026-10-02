<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCoa;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Omnichannel\Livewire\OmnichannelCommandCenter;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Livewire\ResellerDashboard;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\PurchaseOrder;
use App\Modules\Wms\Models\PurchaseOrderItem;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\Supplier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gerbang RBAC lintas-departemen (server-side, di dalam komponen Livewire).
 *
 * `Livewire::addPersistentMiddleware(PermissionMiddleware)` di AppServiceProvider
 * hanya menegakkan ulang permission RUTE yang sedang dibuka. Tiga aksi di bawah ini
 * reachable lewat permission `*.view` padahal menulis data milik departemen lain:
 *
 *  1. ResellerDashboard::prosesApproval  — post jurnal GL + Utang komisi (otoritas finance)
 *  2. ReturnPembelianTab::simpanRetur     — buat retur pembelian + potong stok (otoritas staff-gudang)
 *  3. OmnichannelCommandCenter            — tulis kredensial kanal + mapping produk
 *
 * Setiap test asserting MUTASI DI DATABASE, bukan cuma toast: guard yang benar
 * tidak boleh mengubah apa pun.
 */
class CrossDomainGuardTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'XDG-'.Str::random(5),
            'nama' => 'Cabang Cross-Domain',
            'is_active' => true,
        ]);

        session(['cabang_id' => $this->cabang->id]);
    }

    /** User dengan PERSIS permission yang diminta (tanpa role bawaan seeder). */
    private function userWith(array $perms): User
    {
        $u = User::create([
            'name' => 'Uji XDomain',
            'email' => Str::random(8).'@xdomain.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'r-xdomain-'.implode('-', $perms).'-'.Str::random(4)]);
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($p);
        }
        $u->assignRole($role);
        $u->cabangs()->attach($this->cabang->id);

        $this->actingAs($u, 'web');

        return $u;
    }

    private function coa(string $kode, string $nama, string $tipe, string $kelompok, string $saldoNormal): AkunCoa
    {
        return AkunCoa::create([
            'kode' => $kode, 'nama' => $nama, 'tipe' => $tipe,
            'kelompok' => $kelompok, 'saldo_normal' => $saldoNormal, 'is_active' => true,
        ]);
    }

    /** Komisi reseller `pending` yang siap di-approve (butuh COA 510-01/210-03). */
    private function komisiPending(): Komisi
    {
        $this->coa('510-01', 'Beban Komisi Reseller', 'beban', 'beban_komisi', 'debit');
        $this->coa('210-03', 'Utang Komisi', 'kewajiban', 'utang_komisi', 'kredit');

        $pelanggan = Pelanggan::create([
            'nama' => 'Reseller Uji', 'telepon' => '0812000099', 'is_reseller' => true,
        ]);
        $trx = Transaksi::create([
            'no_transaksi' => 'TRX-XDG-'.Str::random(5),
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $pelanggan->id,
            'subtotal' => 150000, 'diskon_persen' => 0, 'diskon_nominal' => 0,
            'total_akhir' => 150000, 'metode_bayar' => 'tunai', 'status' => 'selesai',
        ]);

        return Komisi::create([
            'no_komisi' => 'KMS-XDG-'.Str::random(4),
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $trx->id,
            'jumlah_transaksi' => 150000,
            'nominal_komisi' => 7500,
            'status' => 'pending',
        ]);
    }

    // ===== 1. Reseller: prosesApproval =====
    // Rute /app/reseller hanya `reseller.view` (marketing). Padahal prosesApproval
    // mem-post jurnal 510-01/210-03 + membuat Utang → otoritas `komisi.approve`.

    public function test_reseller_view_tidak_bisa_approve_komisi_tanpa_jurnal_utang(): void
    {
        $this->userWith(['reseller.view']);
        $komisi = $this->komisiPending();

        Livewire::test(ResellerDashboard::class)
            ->set('selectedKomisiIds', [$komisi->id])
            ->call('prosesApproval', 'approve')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'error');

        // Kerugian yang harus dicegah: TIDAK ada baris jurnal, TIDAK ada Utang.
        $this->assertDatabaseCount('jurnal_akuntansi', 0);
        $this->assertDatabaseCount('utang', 0);
        $this->assertSame('pending', $komisi->fresh()->status);
        $this->assertNull($komisi->fresh()->approved_by_id);
    }

    public function test_komisi_approve_boleh_approve_komisi_jurnal_dan_utang_terbuat(): void
    {
        $this->userWith(['komisi.approve']);
        $komisi = $this->komisiPending();

        // Catatan: alert sukses di prosesApproval mem-interpolasi array $approved
        // ("{$approved} komisi disetujui") → warning "Array to string conversion"
        // yang jadi ErrorException (bug lama, di luar scope). Yang dibuktikan test
        // ini adalah MUTASI: jurnal + Utang benar-benar terbentuk.
        Livewire::test(ResellerDashboard::class)
            ->set('selectedKomisiIds', [$komisi->id])
            ->call('prosesApproval', 'approve');

        $this->assertSame('disetujui', $komisi->fresh()->status);
        $this->assertSame(auth()->id(), $komisi->fresh()->approved_by_id);
        $this->assertDatabaseHas('jurnal_akuntansi', ['referensi_tipe' => Komisi::class, 'referensi_id' => $komisi->id]);
        $this->assertDatabaseHas('utang', ['referensi_tipe' => 'komisi', 'referensi_id' => $komisi->id]);
    }

    // ===== 2. WMS: simpanRetur =====
    // Rute /app/wms hanya `wms.view` (kasir). Path API ekuivalen
    // `POST /api/wms/retur-pembelian` + tombol blade sudah butuh `wms.create`.

    public function test_wms_view_tidak_bisa_membuat_retur_pembelian(): void
    {
        $this->userWith(['wms.view']);
        $poItem = $this->poSiapDiretur();

        Livewire::test(ReturnPembelianTab::class)
            ->set('selectedPoId', $poItem->purchase_order_id)
            ->set('alasan', 'Barang cacat pabrik')
            ->set('metodePengembalian', 'utang')
            ->set('itemInputs', [$poItem->id => $this->inputItem($poItem, 1)])
            ->call('simpanRetur')
            ->assertForbidden();

        $this->assertDatabaseCount('return_pembelian', 0);
    }

    public function test_wms_create_boleh_membuat_retur_pembelian(): void
    {
        $this->userWith(['wms.create']);
        $poItem = $this->poSiapDiretur();

        Livewire::test(ReturnPembelianTab::class)
            ->set('selectedPoId', $poItem->purchase_order_id)
            ->set('alasan', 'Barang cacat pabrik')
            ->set('metodePengembalian', 'utang')
            ->set('itemInputs', [$poItem->id => $this->inputItem($poItem, 1)])
            ->call('simpanRetur')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'success');

        $this->assertDatabaseHas('return_pembelian', ['purchase_order_id' => $poItem->purchase_order_id]);
    }

    /** Bentuk `itemInputs` seperti yang dibangun `updatedSelectedPoId()`. */
    private function inputItem(PurchaseOrderItem $poItem, float $qty): array
    {
        return [
            'purchase_order_item_id' => $poItem->id,
            'produk_nama' => $poItem->produk?->nama ?? '-',
            'qty_po' => (float) $poItem->jumlah,
            'harga_beli' => (float) $poItem->harga_beli,
            'jumlah' => $qty,
            'sn_raw' => '',
        ];
    }

    /** PO berstatus `diterima` + stok tersedia, supaya retur akan benar-benar jadi. */
    private function poSiapDiretur(): PurchaseOrderItem
    {
        $this->coa('210-01', 'Utang Usaha', 'kewajiban', 'utang_usaha', 'kredit');
        $this->coa('130-01', 'Persediaan Barang Dagang', 'aset', 'persediaan', 'debit');

        $gudang = Gudang::create([
            'kode' => 'GDG-'.Str::random(4), 'nama' => 'Gudang XDomain',
            'cabang_id' => $this->cabang->id, 'is_default' => true, 'status' => 'aktif',
        ]);
        $produk = Produk::create([
            'kode' => 'PRD-'.Str::random(4), 'nama' => 'LCD XDomain',
            'harga_beli' => 100000, 'harga_jual' => 150000, 'status' => 'aktif',
        ]);
        $supplier = Supplier::create([
            'kode' => 'SUP-'.Str::random(4), 'nama' => 'Supplier XDomain', 'status' => 'aktif',
        ]);

        StokItem::create(['gudang_id' => $gudang->id, 'produk_id' => $produk->id, 'jumlah' => 5]);

        $po = PurchaseOrder::create([
            'no_po' => 'PO-XDG-'.Str::random(4),
            'supplier_id' => $supplier->id,
            'gudang_tujuan_id' => $gudang->id,
            'tanggal' => now(), 'total' => 500000,
            'status' => 'diterima', 'status_pembayaran' => 'belum_lunas',
        ]);

        return PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'produk_id' => $produk->id,
            'jumlah' => 5, 'harga_beli' => 100000, 'subtotal' => 500000,
        ]);
    }

    // ===== 3. Omnichannel =====
    // Rute /app/omnichannel hanya `omnichannel.view`, tapi connectChannel menulis
    // KREDENSIAL kanal. API ekuivalennya butuh `omnichannel.manage`.

    public function test_omnichannel_view_tidak_bisa_menghubungkan_kanal(): void
    {
        $this->userWith(['omnichannel.view']);

        Livewire::test(OmnichannelCommandCenter::class)
            ->set('connectForm.nama', 'Shopee XDomain')
            ->set('connectForm.platform', 'shopee')
            ->call('connectChannel')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'error')
            ->assertSet('showConnectModal', false); // modal tak pernah tertutup = guard_short-circuit

        $this->assertDatabaseMissing('channels', ['nama' => 'Shopee XDomain']);
    }

    public function test_omnichannel_manage_boleh_menghubungkan_kanal(): void
    {
        $this->userWith(['omnichannel.manage']);

        Livewire::test(OmnichannelCommandCenter::class)
            ->set('connectForm.nama', 'Shopee XDomain')
            ->set('connectForm.platform', 'shopee')
            ->call('connectChannel');

        $this->assertDatabaseHas('channels', ['nama' => 'Shopee XDomain']);
    }

    public function test_omnichannel_view_tidak_bisa_menyimpan_mapping_produk(): void
    {
        $this->userWith(['omnichannel.view']);
        $channel = $this->channelUji();
        $produk = Produk::create([
            'kode' => 'PRD-'.Str::random(4), 'nama' => 'Produk Mapping',
            'harga_beli' => 100000, 'harga_jual' => 150000, 'status' => 'aktif',
        ]);

        Livewire::test(OmnichannelCommandCenter::class)
            ->set('mappingChannelId', $channel->id)
            ->call('toggleProdukMapping', $produk->id)
            ->call('saveMapping')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'error');

        $this->assertDatabaseCount('channel_product_mapping', 0);
    }

    public function test_omnichannel_manage_boleh_menyimpan_mapping_produk(): void
    {
        $this->userWith(['omnichannel.manage']);
        $channel = $this->channelUji();
        $produk = Produk::create([
            'kode' => 'PRD-'.Str::random(4), 'nama' => 'Produk Mapping',
            'harga_beli' => 100000, 'harga_jual' => 150000, 'status' => 'aktif',
        ]);

        Livewire::test(OmnichannelCommandCenter::class)
            ->set('mappingChannelId', $channel->id)
            ->call('toggleProdukMapping', $produk->id)
            ->call('saveMapping')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'success');

        $this->assertDatabaseHas('channel_product_mapping', [
            'channel_id' => $channel->id, 'produk_id' => $produk->id,
        ]);
    }

    public function test_omnichannel_view_tidak_bisa_trigger_sync_all(): void
    {
        $this->userWith(['omnichannel.view']);

        Livewire::test(OmnichannelCommandCenter::class)
            ->call('triggerSyncAll')
            ->assertSet('syncMessage', null);
    }

    public function test_omnichannel_manage_boleh_trigger_sync_all(): void
    {
        $this->userWith(['omnichannel.manage']);

        // Tanpa mapping → loop kosong, jadi tidak ada panggilan HTTP ke marketplace.
        $comp = Livewire::test(OmnichannelCommandCenter::class)
            ->call('triggerSyncAll')
            ->assertDispatched('alert', fn ($e, $p) => ($p[0]['type'] ?? '') === 'success');

        $this->assertNotNull($comp->get('syncMessage'));
    }

    private function channelUji(): Channel
    {
        return Channel::create([
            'nama' => 'Kanal XDomain', 'platform' => 'shopee',
            'kredensial' => [], 'status' => 'terhubung', 'is_active' => true,
        ]);
    }
}
