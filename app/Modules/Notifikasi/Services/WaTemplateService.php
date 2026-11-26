<?php

namespace App\Modules\Notifikasi\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use Illuminate\Support\Facades\Log;

/**
 * Template pesan WhatsApp terpusat, global untuk seluruh toko/cabang.
 *
 * Satu event = satu "session" notifikasi (mis. order menunggu bayar, servis
 * selesai, SLA teknisi). Template referensi disimpan di kode; pengelola toko
 * dapat mengubahnya lewat Pengaturan → Gateway Integrasi → Template WA tanpa
 * deploy. Hierarki aktif: master → grup → event.
 */
class WaTemplateService
{
    public const CONFIG_KEY = 'wa_template_config';

    public function __construct(
        private readonly KonfigurasiService $konfigurasi,
    ) {}

    /** @return array<string, array{label: string, deskripsi: string}> */
    public function groups(): array
    {
        return [
            'transaksi' => [
                'label' => 'Transaksi & Pembayaran',
                'deskripsi' => 'Order online, konfirmasi bayar, pengiriman, dan tagihan QRIS kasir.',
            ],
            'servis_pelanggan' => [
                'label' => 'Servis — Pelanggan',
                'deskripsi' => 'Update tiket servis, estimasi, persetujuan, progres, dan komplain ke pelanggan.',
            ],
            'servis_internal' => [
                'label' => 'Servis — Internal',
                'deskripsi' => 'Tugas teknisi, peringatan SLA, eskalasi komplain, dan notifikasi cabang.',
            ],
            'crm' => [
                'label' => 'CRM & Broadcast',
                'deskripsi' => 'Kampanye broadcast WA. Pesan kampanye tetap diisi dari halaman CRM.',
            ],
        ];
    }

    /**
     * Registry template referensi.
     *
     * @return array<string, array{label: string, group: string, audience: string, aktif: bool, variabel: array<int, string>, opsional?: array<int, string>, default: string}>
     */
    public function events(): array
    {
        return [
            'order_menunggu_pembayaran' => [
                'label' => 'Order online menunggu pembayaran',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'total', 'url_pesanan', 'nama_toko'],
                'default' => "Halo {nama},\n\nPesanan {no_transaksi} telah kami terima dengan total Rp {total}.\n\nSilakan selesaikan pembayaran agar pesanan segera kami proses.\nCek pesanan: {url_pesanan}\n\nTerima kasih,\n{nama_toko}",
            ],
            'pembayaran_duitku_lunas' => [
                'label' => 'Pembayaran online berhasil (Duitku)',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'total', 'metode', 'nama_toko'],
                'opsional' => ['metode'],
                'default' => "Terima kasih {nama}!\n\nPembayaran pesanan {no_transaksi} sebesar Rp {total} telah kami terima. Pesanan Anda segera kami kemas.\n\n{nama_toko}",
            ],
            'pembayaran_kasir_diterima' => [
                'label' => 'Pembayaran dikonfirmasi kasir',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'total', 'metode', 'nama_toko'],
                'opsional' => ['metode'],
                'default' => "Halo {nama},\n\nPembayaran pesanan {no_transaksi} sebesar Rp {total} telah diverifikasi kasir. Pesanan Anda segera kami siapkan.\n\n{nama_toko}",
            ],
            'pembayaran_gagal' => [
                'label' => 'Pembayaran gagal',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => false,
                'variabel' => ['nama', 'no_transaksi', 'alasan', 'url_pesanan', 'nama_toko'],
                'opsional' => ['alasan'],
                'default' => "Halo {nama},\n\nPembayaran pesanan {no_transaksi} belum berhasil{alasan}. Anda dapat mencoba kembali melalui halaman pesanan.\nCek pesanan: {url_pesanan}\n\n{nama_toko}",
            ],
            'pembayaran_kadaluarsa' => [
                'label' => 'Pembayaran kadaluarsa',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => false,
                'variabel' => ['nama', 'no_transaksi', 'url_pesanan', 'nama_toko'],
                'default' => "Halo {nama},\n\nPesanan {no_transaksi} belum dibayar dan telah kadaluarsa. Silakan buat pesanan baru bila masih ingin membeli.\nCek pesanan: {url_pesanan}\n\n{nama_toko}",
            ],
            'pesanan_siap_diambil' => [
                'label' => 'Pesanan siap diambil di toko',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'nama_cabang', 'nama_toko'],
                'default' => "Halo {nama},\n\nPesanan {no_transaksi} sudah siap diambil di {nama_cabang}. Silakan datang ke konter dengan menyebutkan nomor pesanan.\n\nTerima kasih,\n{nama_toko}",
            ],
            'pesanan_dikirim' => [
                'label' => 'Pesanan dikirim ke pelanggan',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'kurir', 'no_resi', 'nama_toko'],
                'default' => "Halo {nama},\n\nPesanan {no_transaksi} telah dikirim via {kurir} dengan nomor resi {no_resi}.\n\nTerima kasih telah berbelanja di {nama_toko}.",
            ],
            'pesanan_selesai_diambil' => [
                'label' => 'Pesanan selesai diambil',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_transaksi', 'nama_cabang', 'nama_toko'],
                'default' => "Halo {nama},\n\nPesanan {no_transaksi} telah selesai diambil di {nama_cabang}. Terima kasih telah mempercayai {nama_toko}.",
            ],
            'tagihan_qris' => [
                'label' => 'Tagihan pembayaran QRIS kasir',
                'group' => 'transaksi',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'invoice', 'total', 'qr_url', 'nama_toko'],
                'opsional' => ['qr_url'],
                'default' => "*Tagihan Pembayaran QRIS - {nama_toko}*\n\nHalo *{nama}*,\n\nBerikut rincian tagihan belanja Anda:\n• No. Transaksi: *{invoice}*\n• Total Tagihan: *Rp {total}*\n\nSilakan scan kode QRIS yang tampil di layar kasir atau buka tautan berikut:\n{qr_url}\nPastikan nominal pembayaran sesuai (*Rp {total}*). Simpan bukti transaksi setelah berhasil.\n\nTerima kasih telah berbelanja di *{nama_toko}*!",
            ],
            'servis_unit_diterima' => [
                'label' => 'Unit servis diterima',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'nama_cabang', 'url_tracking', 'nama_toko'],
                'opsional' => ['nama_cabang', 'url_tracking'],
                'default' => "Halo {nama},\n\nUnit {jenis_hp} ({no_tiket}) telah kami terima di {nama_cabang}.\nPantau perbaikan Anda di: {url_tracking}\n\n{nama_toko}",
            ],
            'servis_status' => [
                'label' => 'Status servis diperbarui',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'status', 'nama_cabang', 'url_tracking', 'nama_toko'],
                'opsional' => ['nama_cabang', 'url_tracking'],
                'default' => "Halo {nama},\n\nStatus servis {no_tiket} ({jenis_hp}) kini: {status}.\n\nPantau progres perbaikan Anda di {url_tracking}\n\n{nama_toko}",
            ],
            'servis_estimasi_approval' => [
                'label' => 'Estimasi servis perlu persetujuan',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'biaya', 'link_approve', 'batas_waktu', 'nama_toko'],
                'opsional' => ['batas_waktu'],
                'default' => "Halo {nama},\n\nEstimasi biaya perbaikan unit {jenis_hp} ({no_tiket}) adalah {biaya}.\n\nSilakan tinjau & setujui di: {link_approve}\n{batas_waktu}\n\n{nama_toko}",
            ],
            'servis_perubahan_lingkup' => [
                'label' => 'Perubahan lingkup perlu persetujuan',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'link', 'nama_toko'],
                'opsional' => ['jenis_hp'],
                'default' => "Halo {nama},\n\nAda perubahan lingkup pada servis {no_tiket} di luar estimasi yang Anda setujui.\n\nTinjau & setujui di: {link}\n\n{nama_toko}",
            ],
            'servis_persetujuan_diulang' => [
                'label' => 'Kirim ulang link persetujuan',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'link', 'nama_toko'],
                'default' => "Halo {nama},\n\nPembaruan: servis {no_tiket} memerlukan persetujuan Anda.\n\nTinjau & setujui di: {link}\n\n{nama_toko}",
            ],
            'servis_progres' => [
                'label' => 'Progres pengerjaan servis',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'persentase', 'judul', 'url_tracking', 'nama_toko'],
                'opsional' => ['url_tracking'],
                'default' => "Halo {nama},\n\nProgress pengerjaan servis {no_tiket} ({jenis_hp}): {persentase}% — {judul}.\n\nPantau selengkapnya di {url_tracking}\n\n{nama_toko}",
            ],
            'servis_komplain_diterima' => [
                'label' => 'Komplain pasca-servis diterima',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'status_garansi', 'link', 'nama_toko'],
                'default' => "Halo {nama},\n\nKomplain untuk {no_tiket} ({jenis_hp}) telah kami terima. {status_garansi}.\n\nPantau tindak lanjut di: {link}\n\n{nama_toko}",
            ],
            'servis_batal_otomatis' => [
                'label' => 'Servis dibatalkan otomatis (overdue)',
                'group' => 'servis_pelanggan',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'no_tiket', 'jenis_hp', 'nama_cabang', 'alasan', 'nama_toko'],
                'default' => "Halo {nama},\n\nMohon maaf atas ketidaknyamanannya. Pengerjaan servis unit {jenis_hp} ({no_tiket}) melewati batas waktu estimasi dan dibatalkan otomatis.\n\nUnit siap diambil kembali di {nama_cabang}. Silakan hubungi kami untuk penjadwalan ulang.\n\n{nama_toko}",
            ],
            'servis_tugas_mulai' => [
                'label' => 'Tugas pengerjaan dimulai (teknisi)',
                'group' => 'servis_internal',
                'audience' => 'Teknisi',
                'aktif' => true,
                'variabel' => ['nama_teknisi', 'no_tiket', 'jenis_hp', 'batas_menit', 'deadline', 'nama_toko'],
                'default' => "Halo {nama_teknisi},\n\nAnda mulai mengerjakan servis {no_tiket} ({jenis_hp}).\nBatas waktu pengerjaan: {batas_menit} menit (Deadline: {deadline}).\n\nPantau progres dan unggah pembaruan secara berkala.\n\n{nama_toko}",
            ],
            'servis_sla_warning' => [
                'label' => 'Peringatan SLA menjelang deadline (teknisi)',
                'group' => 'servis_internal',
                'audience' => 'Teknisi',
                'aktif' => true,
                'variabel' => ['nama_teknisi', 'no_tiket', 'jenis_hp', 'sisa_menit', 'nama_toko'],
                'default' => "PERINGATAN BATAS WAKTU\n\nServis {no_tiket} ({jenis_hp}) sisa {sisa_menit} menit lagi.\nSegera selesaikan atau perbarui progres pengerjaan.\n\n{nama_toko}",
            ],
            'servis_sla_habis' => [
                'label' => 'SLA terlewati (teknisi)',
                'group' => 'servis_internal',
                'audience' => 'Teknisi',
                'aktif' => true,
                'variabel' => ['nama_teknisi', 'no_tiket', 'jenis_hp', 'nama_toko'],
                'default' => "BATAS WAKTU HABIS\n\nServis {no_tiket} ({jenis_hp}) telah melewati batas waktu SLA.\nMohon segera selesaikan atau koordinasikan penjadwalan ulang dengan konsumen.\n\n{nama_toko}",
            ],
            'servis_komplain_eskalasi_teknisi' => [
                'label' => 'Eskalasi komplain ke teknisi',
                'group' => 'servis_internal',
                'audience' => 'Teknisi',
                'aktif' => true,
                'variabel' => ['nama_teknisi', 'no_tiket', 'jenis_hp', 'nama_toko'],
                'default' => "ESKALASI KOMPLAIN\n\n{no_tiket} ({jenis_hp}) melewati SLA komplain. Segera tindak lanjuti dan koordinasikan dengan konsumen.\n\n{nama_toko}",
            ],
            'servis_komplain_eskalasi_cabang' => [
                'label' => 'Eskalasi komplain ke cabang',
                'group' => 'servis_internal',
                'audience' => 'Cabang',
                'aktif' => true,
                'variabel' => ['nama_cabang', 'no_tiket', 'jenis_hp', 'nama_toko'],
                'default' => "ESKALASI KOMPLAIN\n\n{no_tiket} ({jenis_hp}) melewati SLA komplain.\nMohon tindak lanjuti dari {nama_cabang}.\n\n{nama_toko}",
            ],
            'servis_komplain_masuk' => [
                'label' => 'Komplain masuk ke cabang',
                'group' => 'servis_internal',
                'audience' => 'Cabang',
                'aktif' => true,
                'variabel' => ['nama_cabang', 'no_tiket', 'jenis_hp', 'no_tiket_lama', 'status_garansi', 'nama_toko'],
                'opsional' => ['status_garansi'],
                'default' => "Komplain masuk {no_tiket} ({jenis_hp}) dari tiket {no_tiket_lama}{status_garansi}\n\nMohon tindak lanjuti dari {nama_cabang}.\n\n{nama_toko}",
            ],
            'broadcast' => [
                'label' => 'Broadcast kampanye WA',
                'group' => 'crm',
                'audience' => 'Pelanggan',
                'aktif' => true,
                'variabel' => ['nama', 'tier', 'pesan', 'nama_toko'],
                'default' => '{pesan}',
            ],
        ];
    }

    /** @return array<string, string> Label status servis yang pernah dikirim ke pelanggan. */
    public function statusServis(): array
    {
        return [
            'diajukan_online' => 'Menunggu Konfirmasi',
            'diterima' => 'Unit Diterima di Cabang',
            'diagnosa' => 'Sedang Didiagnosa Teknisi',
            'menunggu_approval' => 'Menunggu Persetujuan Estimasi Biaya',
            'disetujui' => 'Estimasi Disetujui',
            'dikerjakan' => 'Sedang Diperbaiki',
            'qc' => 'Pemeriksaan Kualitas (QC)',
            'selesai' => 'Perbaikan Selesai (Siap Diambil)',
            'diambil' => 'Unit Telah Diserahkan',
            'ditolak' => 'Estimasi Ditolak',
            'batal' => 'Servis Dibatalkan',
        ];
    }

    /** Override template per status servis (opsional; kosong = pakai template umum). */
    public function statusServisDefaults(): array
    {
        return [
            'selesai' => "Halo {nama},\n\nPerbaikan unit {jenis_hp} ({no_tiket}) telah selesai dan siap diambil di {nama_cabang}.\n\nPantau detail servis: {url_tracking}\n\n{nama_toko}",
            'diambil' => "Halo {nama},\n\nUnit {jenis_hp} ({no_tiket}) telah kami serahkan kepada Anda. Terima kasih telah menggunakan layanan {nama_toko}.",
            'ditolak' => "Halo {nama},\n\nEstimasi perbaikan unit {jenis_hp} ({no_tiket}) ditolak. Jika ada yang ingin didiskusikan, silakan hubungi {nama_cabang}.\n\n{nama_toko}",
            'batal' => "Halo {nama},\n\nServis unit {jenis_hp} ({no_tiket}) telah dibatalkan. Silakan hubungi {nama_cabang} untuk informasi lebih lanjut.\n\n{nama_toko}",
        ];
    }

    public function event(string $key): ?array
    {
        return $this->events()[$key] ?? null;
    }

    /** Konfigurasi efektif: selalu digabung dengan default agar aman saat kunci baru ditambahkan. */
    public function config(): array
    {
        $saved = $this->konfigurasi->get(self::CONFIG_KEY);

        return $this->normalize(is_array($saved) ? $saved : []);
    }

    public function normalize(array $cfg): array
    {
        $normalized = [
            'version' => 1,
            'master_enabled' => (bool) ($cfg['master_enabled'] ?? true),
            'groups' => [],
            'events' => [],
            'updated_at' => (string) ($cfg['updated_at'] ?? ''),
        ];

        foreach ($this->groups() as $groupKey => $group) {
            $normalized['groups'][$groupKey] = [
                'enabled' => (bool) ($cfg['groups'][$groupKey]['enabled'] ?? true),
            ];
        }

        foreach ($this->events() as $eventKey => $event) {
            $savedEvent = is_array($cfg['events'][$eventKey] ?? null) ? $cfg['events'][$eventKey] : [];
            $normalized['events'][$eventKey] = [
                'enabled' => (bool) ($savedEvent['enabled'] ?? $event['aktif'] ?? true),
                'template' => (string) ($savedEvent['template'] ?? $event['default']),
            ];

            if ($eventKey === 'servis_status') {
                $overrides = $this->statusServisDefaults();
                foreach ($this->statusServis() as $statusKey => $label) {
                    $overrides[$statusKey] = (string) ($savedEvent['overrides'][$statusKey] ?? ($overrides[$statusKey] ?? ''));
                }
                $normalized['events'][$eventKey]['overrides'] = $overrides;
            }
        }

        return $normalized;
    }

    /** Simpan + validasi konfigurasi template dari UI. */
    public function save(array $cfg): array
    {
        $errors = [];
        $normalized = $this->normalize($cfg);

        foreach ($this->events() as $eventKey => $event) {
            $template = (string) ($normalized['events'][$eventKey]['template'] ?? '');
            if (trim($template) === '') {
                $errors[$eventKey] = 'Template tidak boleh kosong.';

                continue;
            }
            if (mb_strlen($template) > 2000) {
                $errors[$eventKey] = 'Template maksimal 2000 karakter.';

                continue;
            }
            $unknown = $this->unknownPlaceholders($eventKey, $template);
            if ($unknown !== []) {
                $errors[$eventKey] = 'Placeholder tidak dikenal: '.implode(', ', array_map(fn ($t) => '{'.$t.'}', $unknown)).'. Gunakan: '.implode(', ', $this->placeholderTokens($eventKey));
            }

            if ($eventKey === 'servis_status') {
                foreach ($normalized['events'][$eventKey]['overrides'] as $statusKey => $override) {
                    $override = (string) $override;
                    if (trim($override) === '') {
                        continue;
                    }
                    $errorKey = $eventKey.'.'.$statusKey;
                    if (mb_strlen($override) > 2000) {
                        $errors[$errorKey] = 'Override status maksimal 2000 karakter.';

                        continue;
                    }
                    $unknownOverride = $this->unknownPlaceholders($eventKey, $override);
                    if ($unknownOverride !== []) {
                        $errors[$errorKey] = 'Placeholder tidak dikenal: '.implode(', ', array_map(fn ($t) => '{'.$t.'}', $unknownOverride)).'. Gunakan: '.implode(', ', $this->placeholderTokens($eventKey));
                    }
                }
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'config' => $normalized];
        }

        $normalized['updated_at'] = now()->toIso8601String();
        $this->konfigurasi->set(self::CONFIG_KEY, $normalized, 'Template notifikasi WhatsApp (global)');

        return ['ok' => true, 'errors' => [], 'config' => $normalized];
    }

    public function isEnabled(string $key): bool
    {
        $event = $this->event($key);
        if ($event === null) {
            return false;
        }

        $cfg = $this->config();
        if (! ($cfg['master_enabled'] ?? true)) {
            return false;
        }
        if (! ($cfg['groups'][$event['group']]['enabled'] ?? true)) {
            return false;
        }

        return (bool) ($cfg['events'][$key]['enabled'] ?? $event['aktif'] ?? true);
    }

    public function isCustomized(string $key): bool
    {
        $event = $this->event($key);
        if ($event === null) {
            return false;
        }

        return trim((string) ($this->config()['events'][$key]['template'] ?? '')) !== trim($event['default']);
    }

    /** Template aktif untuk event (atau override status servis). */
    public function templateFor(string $key, array $vars = []): string
    {
        $event = $this->event($key);
        if ($event === null) {
            return '';
        }

        $cfg = $this->config();
        $template = (string) ($cfg['events'][$key]['template'] ?? $event['default']);

        if ($key === 'servis_status') {
            $statusKey = (string) ($vars['status_key'] ?? $vars['status'] ?? '');
            $override = (string) ($cfg['events'][$key]['overrides'][$statusKey] ?? '');
            if (trim($override) !== '') {
                $template = $override;
            }
        }

        return $template !== '' ? $template : (string) $event['default'];
    }

    /** Variabel wajib yang tidak diisi (untuk payload audit & log). */
    public function missingVars(string $key, array $vars = []): array
    {
        $event = $this->event($key);
        if ($event === null) {
            return [];
        }

        $optional = $event['opsional'] ?? [];
        $missing = [];
        foreach ($event['variabel'] as $var) {
            if (in_array($var, $optional, true)) {
                continue;
            }
            if (! array_key_exists($var, $vars) || trim($this->formatValue($vars[$var])) === '') {
                $missing[] = $var;
            }
        }

        return $missing;
    }

    /**
     * Render template menjadi teks WA. `$force = true` tetap merender walaupun
     * event nonaktif (dipakai untuk pratinjau & log "terlewat").
     */
    public function render(string $key, array $vars = [], bool $force = false): string
    {
        if (! $force && ! $this->isEnabled($key)) {
            return '';
        }

        $event = $this->event($key);
        if ($event === null) {
            Log::warning('WA template tidak dikenal', ['event' => $key]);

            return '';
        }

        $template = $this->templateFor($key, $vars);
        $defaults = [
            'nama_toko' => $this->namaToko(),
            'waktu' => now()->format('d M Y H:i'),
            'tanggal' => now()->format('d M Y'),
        ];
        $values = array_merge($defaults, $vars);
        $replacements = [];
        foreach ($values as $token => $value) {
            if (is_string($token) && preg_match('/^[a-z0-9_]+$/i', $token)) {
                $replacements['{'.$token.'}'] = $this->formatValue($value);
            }
        }

        $missing = $this->missingVars($key, $vars);
        if ($missing !== []) {
            // Jangan log nilai/telepon pelanggan — cukup nama variabelnya.
            Log::warning('WA template variabel kosong', [
                'event' => $key,
                'variabel' => $missing,
            ]);
        }

        return trim(strtr($template, $replacements));
    }

    /** @return array<string, string> Data contoh untuk pratinjau & uji kirim. */
    public function sampleVars(string $key): array
    {
        $samples = [
            'nama' => 'Budi Santoso',
            'nama_teknisi' => 'Andi',
            'nama_cabang' => 'Cabang Pusat',
            'no_transaksi' => 'TRX-2026-0001',
            'invoice' => 'INV-2026-0001',
            'no_tiket' => 'SV-2026-0001',
            'no_tiket_lama' => 'SV-2026-0009',
            'jenis_hp' => 'iPhone 12 64GB',
            'total' => '1.250.000',
            'biaya' => 'Rp 850.000',
            'metode' => 'QRIS',
            'kurir' => 'JNE Reguler',
            'no_resi' => 'JNE1234567890',
            'url_pesanan' => url('/account'),
            'url_tracking' => url('/tracking/contoh-token'),
            'link_approve' => url('/tracking/contoh-token'),
            'link' => url('/tracking/contoh-token'),
            'qr_url' => 'https://contoh.example/qris.png',
            'batas_waktu' => 'Mohon setujui dalam 2 x 24 jam.',
            'persentase' => '65',
            'judul' => 'Pengecekan mainboard',
            'status' => 'Sedang Diperbaiki',
            'status_key' => 'dikerjakan',
            'status_garansi' => 'Komplain Anda dalam masa garansi',
            'alasan' => ': kode pembayaran tidak valid',
            'batas_menit' => '120',
            'deadline' => now()->addMinutes(120)->format('H:i'),
            'sisa_menit' => '30',
            'tier' => 'Gold',
            'pesan' => 'Promo spesial minggu ini: diskon 10% untuk semua suku cadang!',
        ];

        $event = $this->event($key);
        if ($event === null) {
            return [];
        }

        $wanted = array_merge($event['variabel'], ['status_key']);
        $result = [];
        foreach ($wanted as $var) {
            if (array_key_exists($var, $samples)) {
                $result[$var] = $samples[$var];
            }
        }

        return $result;
    }

    public function preview(string $key, ?string $template = null, array $vars = []): string
    {
        $event = $this->event($key);
        if ($event === null) {
            return '';
        }

        $values = array_merge($this->sampleVars($key), $vars);
        $defaults = ['nama_toko' => $this->namaToko(), 'waktu' => now()->format('d M Y H:i')];
        $values = array_merge($defaults, $values);

        $replacements = [];
        foreach ($values as $token => $value) {
            if (is_string($token) && preg_match('/^[a-z0-9_]+$/i', $token)) {
                $replacements['{'.$token.'}'] = $this->formatValue($value);
            }
        }

        $source = $template ?? $this->templateFor($key, $values);

        return trim(strtr($source, $replacements));
    }

    /** @return array<int, string> Token placeholder yang dikenal untuk event. */
    public function placeholderTokens(string $key): array
    {
        $event = $this->event($key);
        if ($event === null) {
            return [];
        }

        $tokens = $event['variabel'];
        if ($key === 'servis_status') {
            $tokens[] = 'status_key';
        }

        return array_values(array_unique($tokens));
    }

    /** @return array<int, string> Placeholder di template yang tidak dikenal event. */
    public function unknownPlaceholders(string $key, string $template): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $template, $matches);
        $known = $this->placeholderTokens($key);
        $known[] = 'nama_toko';
        $known[] = 'waktu';
        $known[] = 'tanggal';

        return array_values(array_unique(array_diff($matches[1] ?? [], $known)));
    }

    public function validateTemplate(string $key, string $template): ?string
    {
        if ($this->event($key) === null) {
            return 'Event template tidak dikenal.';
        }
        if (trim($template) === '') {
            return 'Template tidak boleh kosong.';
        }
        if (mb_strlen($template) > 2000) {
            return 'Template maksimal 2000 karakter.';
        }

        $unknown = $this->unknownPlaceholders($key, $template);
        if ($unknown !== []) {
            return 'Placeholder tidak dikenal: '.implode(', ', array_map(fn ($t) => '{'.$t.'}', $unknown)).'. Gunakan: '.implode(', ', $this->placeholderTokens($key));
        }

        return null;
    }

    /** @return array{total: int, aktif: int, nonaktif: int, grup_aktif: int, kustom: int} */
    public function summary(): array
    {
        $cfg = $this->config();
        $aktif = 0;
        $kustom = 0;
        foreach ($this->events() as $key => $event) {
            if ($this->isEnabled($key)) {
                $aktif++;
            }
            if ($this->isCustomized($key)) {
                $kustom++;
            }
        }

        $grupAktif = 0;
        foreach ($this->groups() as $groupKey => $group) {
            if ($cfg['master_enabled'] && ($cfg['groups'][$groupKey]['enabled'] ?? true)) {
                $grupAktif++;
            }
        }

        return [
            'total' => count($this->events()),
            'aktif' => $aktif,
            'nonaktif' => count($this->events()) - $aktif,
            'grup_aktif' => $grupAktif,
            'kustom' => $kustom,
        ];
    }

    protected function namaToko(): string
    {
        $struk = $this->konfigurasi->get('struk_config');

        return (string) (is_array($struk) ? ($struk['nama_toko'] ?? config('app.name', 'UTEParts')) : config('app.name', 'UTEParts'));
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y H:i');
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => $this->formatValue($v), $value));
        }

        return (string) $value;
    }
}
