<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Hr\Livewire\PayrollPage;
use App\Modules\Hr\Models\PayrollPeriode;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Livewire\GrnTab;
use App\Modules\Wms\Livewire\OpnameTab;
use App\Modules\Wms\Livewire\PoTab;
use App\Modules\Wms\Livewire\ProdukTab;
use App\Modules\Wms\Livewire\ReturnPembelianTab;
use App\Modules\Wms\Livewire\StokTab;
use App\Modules\Wms\Livewire\TransferTab;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

/**
 * ============================================================================
 *  REGRESI PERMANEN — replay snapshot Livewire lintas-user harus ditolak.
 * ============================================================================
 *
 * Latar belakang (bug nyata, sudah dieksploitasi dalamieu test):
 * Route `->middleware('permission:kelola-payroll')` hanya melindungi render
 * halaman. Snapshot Livewire adalah BEARER TOKEN: `memo`-nya berisi
 * {id, name, path, method} TANPA user_id/session_id, dan checksum-nya hanya
 * HMAC dari APP_KEY atas isi snapshot. Jadi snapshot sah milik sesi lain bisa
 * di-replay user lain — `flushSession()` + `actingAs($kasir)` lalu POST
 * snapshot milik `finance` berhasil membuat baris `payroll_periode`.
 *
 * Fix: `Livewire::addPersistentMiddleware(PermissionMiddleware::class)` di
 * `AppServiceProvider::boot()` supaya middleware permission rute dijalankan
 * lagi di POST update. Step 3 di bawah mengunci fix itu — hapus registrasinya
 * dan test ini harus kembali merah.
 *
 * Kenapa TIDAK pakai `Livewire::test()`: harness itu memasang komponen di rute
 * sintetis tanpa middleware, jadi persistent middleware tidak pernah ikut —
 * test berbasis `Livewire::test()` tidak bisa membuktikan apa pun soal ini.
 * Semua serangan memakai HTTP test client sungguhan.
 *
 * Method yang dipilih:
 *  - `PayrollPage::buatPeriode()` — mutator termurah: butuh CUMA property
 *    `$periode` valid (format YYYY-MM), tanpa karyawan/slip/COA/jurnal. Efeknya
 *    terverifikasi langsung: baris baru di tabel `payroll_periode`.
 *
 * Catatan versi: repo ini memakai livewire/livewire **v4.4.5**. Path update
 * endpoint diambil dari `HandleRequests::getUpdateUri()` karena v4 memakai
 * prefix hash (`livewire-cd2f5ca2/update`), bukan `/livewire/update`.
 */
class LivewireSnapshotReplayGuardTest extends TestCase
{
    use RefreshDatabase;

    private Cabang $cabang;

    private User $finance;

    private User $kasir;

    private string $updateUri;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cabang = Cabang::create([
            'kode' => 'UTPRF', 'nama' => 'Cabang Referee', 'is_active' => true,
        ]);

        $this->finance = $this->userWithRole('finance', 'kelola-payroll');
        $this->kasir = $this->userWithRole('kasir');

        session(['cabang_id' => $this->cabang->id]);

        $this->updateUri = app(HandleRequests::class)->getUpdateUri();
    }

    private function userWithRole(string $role, ?string $mustHave = null): User
    {
        $user = User::create([
            'name' => 'Referee '.ucfirst($role),
            'email' => Str::slug($role, '-').'-'.Str::random(6).'@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->assertTrue(
            $user->fresh()->can($mustHave),
            "user role {$role} harus punya {$mustHave}"
        );

        return $user;
    }

    /**
     * Ambil SEMUA snapshot Livewire yang tertanam di HTML halaman.
     * Livewire v4 menulis JSON snapshot mentah ke atribut `wire:snapshot="..."`.
     *
     * @return array<int, array{memoName:string, memoPath:string, snapshot:string}>
     */
    private function snapshotsIn(string $html): array
    {
        $out = [];

        // Atribut di-escape HTML (kutip & jadi &quot;), decode sebelum regex.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_SUBSTITUTE);

        preg_match_all('/wire:snapshot="(.*?)"\s/s', $decoded, $m);

        foreach ($m[1] as $raw) {
            $s = json_decode($raw, associative: true);

            if (! is_array($s) || ! isset($s['memo'])) {
                continue;
            }

            $out[] = [
                'memoName' => $s['memo']['name'] ?? '?',
                'memoPath' => $s['memo']['path'] ?? '?',
                'snapshot' => json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        return $out;
    }

    private function dump(string $label, array $data): void
    {
        fwrite(STDERR, "\n=== REFREE: {$label} ===\n".json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function firstSnapshot(string $html, string $class): ?array
    {
        $needle = $this->shortName($class);

        foreach ($this->snapshotsIn($html) as $s) {
            if ($this->shortName($s['memoName']) === $needle) {
                return $s;
            }
        }

        return null;
    }

    /** Livewire v4 menyimpan memo.name sebagai FQCN lower-case, StudlyCase jadi kebab. */
    private function shortName(string $class): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($class));
    }

    /** Kirim POST ke endpoint update Livewire seperti yang dikirim browser. */
    private function postUpdate(string $snapshot, string $method, array $updates = []): TestResponse
    {
        return $this->postJson($this->updateUri, [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => []]],
            ]],
        ], [
            'X-Livewire' => 'true',
            'X-CSRF-TOKEN' => csrf_token(),
        ]);
    }

    // =====================================================================
    // STEP 1 — apakah low-priv user bisa sampai ke halaman payroll sama sekali?
    // =====================================================================

    public function test_step1_kasir_ditolak_di_halaman_payroll(): void
    {
        $this->assertFalse(
            $this->kasir->can('kelola-payroll'),
            'PREKONDIISI: kasir memang TIDAK boleh kelola-payroll'
        );

        $this->actingAs($this->kasir, 'web');
        session(['cabang_id' => $this->cabang->id]);
        $res = $this->get('/app/hr/payroll');

        $this->dump('STEP1 GET /app/hr/payroll sebagai kasir', [
            'status' => $res->getStatusCode(),
            'kasir_can_kelola_payroll' => $this->kasir->can('kelola-payroll'),
        ]);

        $this->assertSame(403, $res->getStatusCode(), 'halaman payroll harus 403 untuk kasir');

        // Sebaliknya finance HARUS boleh (bukti setup benar).
        $this->actingAs($this->finance, 'web');
        session(['cabang_id' => $this->cabang->id]);
        $ok = $this->get('/app/hr/payroll');

        $this->dump('STEP1 GET /app/hr/payroll sebagai finance', [
            'status' => $ok->getStatusCode(),
            'finance_can_kelola_payroll' => $this->finance->can('kelola-payroll'),
            'semua_nama_komponen' => array_values(array_unique(array_column(
                $this->snapshotsIn($ok->getContent()),
                'memoName'
            ))),
            'jumlah_attr_wire_snapshot' => substr_count(
                html_entity_decode($ok->getContent(), ENT_QUOTES | ENT_SUBSTITUTE),
                'wire:snapshot='
            ),
            'cuplikan_html' => substr($ok->getContent(), 0, 1200),
        ]);

        $this->assertSame(200, $ok->getStatusCode());
    }

    // =====================================================================
    // STEP 2 — adakah JALAN APAPUN kasir untuk mendapat snapshot PayrollPage?
    // =====================================================================

    public function test_step2_tidak_ada_jalan_kasir_ke_snapshot_payroll(): void
    {
        $this->actingAs($this->kasir, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $urls = [
            '/app/dashboard', '/app/pos', '/app/wms', '/app/wms/cycle-count',
            '/app/servis', '/app/crm', '/app/crm/leads', '/app/reseller',
            '/app/akunting', '/app/akunting/aset', '/app/laporan-pajak',
            '/app/omnichannel', '/app/pengaturan', '/app/audit-log',
            '/app/laporan', '/app/laporan/nomor-seri',
            '/app/hr/payroll', '/app/hr/absensi', '/app/hr/saya', '/app/hr/komisi-skema',
            '/app/approvals',
        ];

        $temuan = [];

        foreach ($urls as $url) {
            $res = $this->get($url);
            $html = $res->isSuccessful() ? $res->getContent() : '';
            $snap = $this->snapshotsIn($html);

            $temuan[$url] = [
                'status' => $res->getStatusCode(),
                'component_names' => array_values(array_unique(array_column($snap, 'memoName'))),
                'punya_PayrollPage' => (bool) $this->firstSnapshot($html, PayrollPage::class),
                'html_mentions_payroll_sliver' => str_contains($html, 'payroll-periode'),
            ];
        }

        $this->dump('STEP2 komposisi komponen Livewire per halaman yang bisa dibuka kasir', $temuan);

        $totalPayrollSnapshot = 0;

        foreach ($temuan as $row) {
            $totalPayrollSnapshot += (int) $row['punya_PayrollPage'];
        }

        $this->assertSame(
            0,
            $totalPayrollSnapshot,
            'DITEMUKAN: kasir bisa Reaching PayrollPage dari halaman lain'
        );

        // Verifikasi langsung di seluruh view repo: PayrollPage tidak pernah di-mount manual.
        $grep = [];
        foreach (glob(base_path('resources/views').'/**/*.blade.php') ?: [] as $f) {
            if (str_contains((string) file_get_contents($f), 'PayrollPage')) {
                $grep[] = $f;
            }
        }
        $this->dump('STEP2 blade yang menyebut PayrollPage', $grep);
        $this->assertSame([], $grep, 'PayrollPage seharusnya tidak di-mount manual di blade');
    }

    // =====================================================================
    // STEP 3 — UJI PEMUTUS: snapshot finance bisa di-replay oleh kasir?
    // =====================================================================

    public function test_step3_snapshot_lintas_user_tidak_terikat_user(): void
    {
        // --- 3a. finance render halaman, ambil snapshot NYATA dari HTML ---
        $this->actingAs($this->finance, 'web');
        session(['cabang_id' => $this->cabang->id]);
        $res = $this->get('/app/hr/payroll');
        $this->assertSame(200, $res->getStatusCode());

        $snap = $this->firstSnapshot($res->getContent(), PayrollPage::class);
        $this->assertNotNull($snap, 'snapshot PayrollPage harus ada di HTML finance');

        $decoded = json_decode($snap['snapshot'], associative: true);

        $this->dump('STEP3 snapshot yang dicuri dari sesi finance', [
            'memo' => $decoded['memo'],
            'data' => $decoded['data'],
            'checksum_panjang' => strlen($decoded['checksum'] ?? ''),
            'memo_menguat_user_id' => str_contains(json_encode($decoded['memo']), '"user'),
            'data_menguat_user_id' => str_contains(json_encode($decoded['data']), (string) $this->finance->id),
            'memo_path' => $decoded['memo']['path'] ?? null,
            'memo_method' => $decoded['memo']['method'] ?? null,
        ]);

        // Bukti: checksum = HMAC(app_key) atas isi snapshot saja.
        $recalc = Checksum::generate(
            collect($decoded)->except('checksum')->all()
        );
        $this->dump('STEP3 verifikasi checksum HMAC', [
            'checksum_di_snapshot' => $decoded['checksum'],
            'checksum_hitung_ulang' => $recalc,
            'cocok' => hash_equals($decoded['checksum'], $recalc),
            'input_yang_dihash' => ['memo', 'data'],
        ]);
        $this->assertTrue(hash_equals($decoded['checksum'], $recalc), 'checksum harus bisa dihitung ulang tanpa user/session');

        // --- 3b. kasir, SESI BEDA SEPENUHNYA, replay snapshot finance ---
        $this->flushSession();               // buang sesi finance
        $this->app['auth']->forgetGuards();   // guardForget
        $this->actingAs($this->kasir, 'web'); // login user low-priv
        session(['cabang_id' => $this->cabang->id]);

        $this->assertTrue($this->kasir->can('pos.view-own'));
        $this->assertFalse($this->kasir->can('kelola-payroll'));

        $this->assertFalse(
            PayrollPeriode::where('periode', '2026-01')->exists(),
            'PREKONDIISI: periode 2026-01 belum ada'
        );

        $attack = $this->postUpdate($snap['snapshot'], 'buatPeriode', ['periode' => '2026-01']);

        $body = $attack->getContent();
        $this->dump('STEP3 RESPONSI SERANGAN', [
            'url' => $this->updateUri,
            'method_http' => 'POST',
            'status' => $attack->getStatusCode(),
            'body_500_char' => substr($body, 0, 500),
            'baris_payroll_periode_2026_01_ada' => PayrollPeriode::where('periode', '2026-01')->exists(),
        ]);

        $effect = PayrollPeriode::where('periode', '2026-01')->exists();

        $this->assertSame(
            403,
            $attack->getStatusCode(),
            'REGRESI: replay snapshot lintas-user harus ditolak 403 oleh PermissionMiddleware persistent'
        );

        $this->assertFalse(
            $effect,
            'REGRESI: kasir berhasil membuat PayrollPeriode lewat replay snapshot — middleware persistent tidak aktif'
        );

        // Penjaga tambahan: jangan sampai session-scoped punya efek samping ke
        // user yang MELETAKKAN halaman — finance tetap bisa memakai komponennya.
        $this->actingAs($this->finance, 'web');
        session(['cabang_id' => $this->cabang->id]);
        $this->get('/app/hr/payroll')->assertOk();
    }

    // =====================================================================
    // STEP 4 — varian lintas-komponen: apa lagi yang ter-mount di halaman kasir?
    // =====================================================================

    public function test_step4_komponen_termount_di_halaman_yang_bisa_diakses_kasir(): void
    {
        $this->actingAs($this->kasir, 'web');
        session(['cabang_id' => $this->cabang->id]);

        $urls = ['/app/pos', '/app/wms', '/app/dashboard'];
        $report = [];

        foreach ($urls as $url) {
            $res = $this->get($url);
            $report[$url] = [
                'status' => $res->getStatusCode(),
                'component_names' => array_values(array_unique(array_column(
                    $this->snapshotsIn($res->isSuccessful() ? $res->getContent() : ''),
                    'memoName'
                ))),
            ];
        }

        $this->dump('STEP4 komponen Livewire ter-mount di halaman kasir', $report);

        // Layout backoffice tidak boleh mount widget Livewire global.
        $layout = (string) file_get_contents(base_path('resources/views/layouts/backoffice.blade.php'));
        preg_match_all('/@livewire\s*\(\s*([^,\)\s]+)/', $layout, $m);
        $this->dump('STEP4 @livewire di layouts/backoffice', $m[1]);
        $this->assertSame([], $m[1], 'layout backoffice tidak seharusnya mount komponen Livewire global');

        // Mutator pada komponen yang TERMOUNT di /app/wms (kasir punya wms.view):
        // apakah ada gate server-side di dalam komponennya?
        $mountable = [
            StokTab::class,
            ProdukTab::class,
            TransferTab::class,
            OpnameTab::class,
            PoTab::class,
            GrnTab::class,
            ReturnPembelianTab::class,
        ];

        $gate = [];

        foreach ($mountable as $class) {
            $src = (string) file_get_contents((new \ReflectionClass($class))->getFileName());
            preg_match_all('/public function (\w+)\s*\(/', $src, $fn);
            $mutators = array_values(array_filter($fn[1], fn ($m) => ! in_array($m, [
                'mount', 'render', 'loadSlips', 'updatedPeriode', 'queryString', 'getListenersProperty', 'getQueryStringProperty', 'updated', 'updating', 'boot', 'booted',
            ], true)));

            $gate[$class] = [
                'ada_gate_server_side_di_kelas' => (bool) preg_match(
                    '/abort_unless|abort_if|authorize\(|->can\(|hasPermissionTo|hasRole\(|GATE:|\$this->authorize/',
                    $src
                ),
                'jumlah_method_public' => count($fn[1]),
                'contoh_mutator' => array_slice($mutators, 0, 8),
            ];
        }

        $this->dump('STEP4 gate server-side per komponen WMS', $gate);

        $this->assertTrue(true, 'laporan Steps 1-4 sudah dicetak ke STDERR');
    }
}
