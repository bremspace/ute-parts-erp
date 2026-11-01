<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Marketplace\Models\WebArticle;
use App\Modules\Marketplace\Services\CartService;
use App\Modules\Marketplace\Services\WebKonsumenService;
use App\Modules\Wms\Models\Produk;
use Livewire\Component;
use Livewire\WithPagination;

class KnowledgeBasePage extends Component
{
    use WithPagination;

    public ?string $slug = null;

    // Filter & Search
    public string $search = '';

    public string $kategori = '';

    public function mount(?string $slug = null): void
    {
        $this->slug = $slug;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingKategori(): void
    {
        $this->resetPage();
    }

    public function setKategori(string $kat): void
    {
        $this->kategori = ($this->kategori === $kat) ? '' : $kat;
        $this->resetPage();
    }

    /**
     * Content-to-commerce: Beli atau tambahkan part dari artikel panduan langsung ke keranjang.
     */
    public function tambahKeKeranjang(int $produkId): void
    {
        $produk = Produk::find($produkId);
        if (! $produk || ! $produk->is_active) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Produk tidak tersedia.']);

            return;
        }

        try {
            app(CartService::class)->tambah($produk->id, null, 1);
            $this->dispatch('cart-updated', count: app(CartService::class)->jumlahItem());
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => 'Suku cadang "'.$produk->nama.'" berhasil ditambahkan ke keranjang!',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function render()
    {
        $webConfig = app(WebKonsumenService::class)->getAll();

        if ($this->slug) {
            $article = WebArticle::with(['penulis', 'rekomendasiServis'])
                ->where('slug', $this->slug)
                ->where('is_published', true)
                ->firstOrFail();

            // Naikkan jumlah view
            $article->increment('views_count');

            // Artikel Terkait
            $relatedArticles = WebArticle::published()
                ->where('id', '!=', $article->id)
                ->where('kategori', $article->kategori)
                ->limit(3)
                ->get();

            // Produk Terkait dari JSON IDs
            $produkTerkait = $article->produkTerkait;

            return view('modules.marketplace.livewire.panduan-detail', [
                'article' => $article,
                'relatedArticles' => $relatedArticles,
                'produkTerkait' => $produkTerkait,
                'webConfig' => $webConfig,
            ])->layout('layouts.marketplace', [
                'title' => $article->meta_title ?: $article->judul,
            ]);
        }

        // List Panduan / Index
        $query = WebArticle::published();

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('judul', 'like', '%'.$this->search.'%')
                    ->orWhere('ringkasan', 'like', '%'.$this->search.'%')
                    ->orWhere('konten_lengkap', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->kategori !== '') {
            $query->where('kategori', $this->kategori);
        }

        $articles = $query->paginate(9);

        // Highlight article (artikel dengan video atau views terbanyak)
        $highlightArticle = WebArticle::published()
            ->whereNotNull('youtube_video_id')
            ->latest('views_count')
            ->first();

        // Kategori dinamis yang ada di database
        $kategoriList = WebArticle::published()
            ->distinct()
            ->pluck('kategori')
            ->filter()
            ->values()
            ->all();

        return view('modules.marketplace.livewire.panduan-index', [
            'articles' => $articles,
            'highlightArticle' => $highlightArticle,
            'webConfig' => $webConfig,
            'kategoriList' => $kategoriList,
        ])->layout('layouts.marketplace', [
            'title' => 'Panduan Teknis & Servis — '.($webConfig['store_name'] ?? config('app.name', 'Pusat Edukasi')),
        ]);
    }
}
