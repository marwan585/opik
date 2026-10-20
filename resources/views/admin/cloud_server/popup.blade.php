@extends('layouts.admin')

@section('title')
    Popup Settings
@endsection

@section('content-header')
    <h1>
        <i class="fa fa-bullhorn text-primary"></i>
        Popup Settings
        <small>Atur banner popup yang tampil saat user masuk server mereka</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloud Server · Popup Settings</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="box box-primary" style="border-top: 3px solid #3c8dbc;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-image"></i> &nbsp;Konfigurasi Popup</h3>
            </div>
            <form method="POST" action="{{ route('admin.cloud.popup') }}">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="enabled">
                            <input type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                            <strong>Popup Aktif</strong>
                            <span class="text-muted small">— tampilkan popup saat user masuk server mereka</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="title"><strong>Judul Popup</strong></label>
                        <input type="text" class="form-control" name="title" id="title"
                               value="{{ old('title', $title) }}" maxlength="191"
                               placeholder="Contoh: Pengumuman Maintenance">
                        <p class="text-muted small">Judul header popup. Maks 191 karakter.</p>
                    </div>

                    <div class="form-group">
                        <label for="image_url"><strong>URL Gambar</strong></label>
                        <input type="url" class="form-control" name="image_url" id="image_url"
                               value="{{ old('image_url', $imageUrl) }}" maxlength="500"
                               placeholder="https://example.com/banner.jpg">
                        <p class="text-muted small">
                            URL gambar (boleh <code>.jpg</code>, <code>.png</code>, <code>.webp</code>, atau <code>.gif</code>).
                            Disarankan rasio 2:1 (mis. 1120×560).
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="text"><strong>Teks Popup</strong></label>
                        <textarea class="form-control" name="text" id="text" rows="6"
                                  maxlength="5000"
                                  placeholder="Tulis pengumuman yang ingin ditampilkan ke user di sini. Boleh multiline.">{{ old('text', $text) }}</textarea>
                        <p class="text-muted small">Teks yang tampil di bawah gambar. Maks 5000 karakter.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> &nbsp;Simpan &amp; Aktifkan
                    </button>
                    <a href="{{ route('admin.index') }}" class="btn btn-default">Batal</a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-eye"></i> &nbsp;Preview Live</h3>
            </div>
            <div class="box-body" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 0;">
                <div id="preview-card" style="position: relative; border-radius: 12px; overflow: hidden;">
                    <button type="button" style="position:absolute;top:10px;right:10px;z-index:10;width:30px;height:30px;border-radius:50%;background:rgba(15,23,42,0.85);border:1px solid rgba(255,255,255,0.12);color:#e5e7eb;cursor:pointer;font-size:12px;">×</button>
                    <div id="preview-image-area" style="width:100%;max-height:200px;overflow:hidden;background:#0f172a;min-height:80px;display:flex;align-items:center;justify-content:center;color:#475569;font-size:12px;">
                        <span id="preview-image-placeholder">— no image —</span>
                        <img id="preview-image" src="" alt="" style="display:none;width:100%;height:auto;max-height:200px;object-fit:cover;" />
                    </div>
                    <div style="padding: 16px 18px 20px;">
                        <h4 id="preview-title" style="margin:0 0 8px;color:#f3f4f6;font-size:16px;font-weight:700;">Pengumuman</h4>
                        <div id="preview-text" style="color:#cbd5e1;font-size:13px;line-height:1.5;white-space:pre-wrap;">— belum ada teks —</div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <p class="small text-muted" style="margin: 0;">
                    Preview ini menunjukkan tampilan popup yang akan dilihat user
                    saat masuk server mereka.
                </p>
            </div>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> &nbsp;Info</h3>
            </div>
            <div class="box-body">
                <p class="small">
                    Popup akan tampil <strong>setiap kali user masuk</strong> ke halaman
                    server mereka (URL <code>/server/&lt;uuid&gt;</code>). User bisa
                    menutupnya dengan tombol <code>×</code> atau tekan <code>Esc</code>,
                    tapi akan muncul lagi saat mereka masuk server lain.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview updater
(function() {
    const enabledEl = document.getElementById('enabled');
    const titleEl = document.getElementById('title');
    const imageEl = document.getElementById('image_url');
    const textEl = document.getElementById('text');
    const pTitle = document.getElementById('preview-title');
    const pText = document.getElementById('preview-text');
    const pImg = document.getElementById('preview-image');
    const pImgPlaceholder = document.getElementById('preview-image-placeholder');

    function updatePreview() {
        pTitle.textContent = (titleEl.value || 'Pengumuman').trim();
        pText.textContent = textEl.value || '— belum ada teks —';
        if (imageEl.value) {
            pImg.src = imageEl.value;
            pImg.style.display = 'block';
            pImgPlaceholder.style.display = 'none';
        } else {
            pImg.src = '';
            pImg.style.display = 'none';
            pImgPlaceholder.style.display = 'block';
        }
    }

    [enabledEl, titleEl, imageEl, textEl].forEach(function(el) {
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
    updatePreview();
})();
</script>
@endsection
