@extends('layouts.semantic')
@section('title', 'SPARQL Console')
@section('page-label', 'SPARQL Console')
@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">RDF QUERY WORKSPACE</div>
        <h1>SPARQL Console</h1>
        <p>Jalankan query baca terhadap graph RDF yang dipublikasikan di Apache Jena Fuseki. Console ini hanya menerima SELECT dan ASK.</p>
    </div>
</div>

<div class="status-banner {{ ($fusekiStatus['status'] ?? '') === 'ready' ? 'good' : 'warn' }}">
    <span class="dot"></span>
    <div>
        <strong>{{ ($fusekiStatus['status'] ?? '') === 'ready' ? 'Fuseki terhubung' : (($fusekiStatus['status'] ?? '') === 'not_configured' ? 'Fuseki belum dikonfigurasi' : 'Fuseki belum dapat diakses') }}</strong>
        <small>{{ $fusekiStatus['message'] ?? 'Status endpoint Fuseki tidak diketahui.' }}</small>
        @if(!empty($fusekiStatus['triple_count']))<small>Triple pada dataset: {{ number_format($fusekiStatus['triple_count']) }}</small>@endif
    </div>
</div>

@if($errorMessage)
    <div class="alert error">{{ $errorMessage }}</div>
@endif

<section class="panel" style="margin-bottom:22px">
    <div class="panel-head">
        <div><h2>Query editor</h2><p>Gunakan PREFIX ontologi yang tersedia. SPARQL Update tidak diperbolehkan dari halaman ini.</p></div>
    </div>
    <div style="padding:18px 20px">
        <form method="POST" action="{{ route('semantic.sparql.execute') }}">
            @csrf
            <textarea name="query" rows="13" spellcheck="false" style="display:block;width:100%;box-sizing:border-box;resize:vertical;border:1px solid #31435c;border-radius:9px;background:#081321;color:#d4e7ff;padding:15px;font-family:'IBM Plex Mono',monospace;font-size:13px;line-height:1.6">{{ old('query', $queryText) }}</textarea>
            @error('query')<div class="alert error" style="margin-top:10px">{{ $message }}</div>@enderror
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:14px">
                <button class="btn primary" type="submit" {{ ($fusekiStatus['status'] ?? '') !== 'ready' ? 'disabled' : '' }}>Jalankan SELECT / ASK</button>
                <a class="btn" href="{{ route('semantic.sparql') }}">Reset</a>
                <span class="muted" style="font-size:12px">Endpoint: {{ config('semantic.fuseki.query_url') }}</span>
            </div>
        </form>
    </div>
</section>

@if(is_array($result))
    @if(array_key_exists('boolean', $result))
        <section class="panel">
            <div class="panel-head"><div><h2>Hasil ASK</h2><p>Jawaban boolean dari dataset RDF.</p></div></div>
            <div style="padding:24px"><span class="badge {{ $result['boolean'] ? 'good' : 'warn' }}">{{ $result['boolean'] ? 'TRUE' : 'FALSE' }}</span></div>
        </section>
    @else
        @php($variables = data_get($result, 'head.vars', []))
        @php($bindings = data_get($result, 'results.bindings', []))
        <section class="panel">
            <div class="panel-head"><div><h2>Query result</h2><p>{{ count($bindings) }} baris dikembalikan.</p></div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr>@foreach($variables as $variable)<th>{{ $variable }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse($bindings as $binding)
                        <tr>@foreach($variables as $variable)<td class="mono" style="max-width:420px;overflow-wrap:anywhere">{{ data_get($binding, $variable.'.value', '—') }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ max(1, count($variables)) }}" class="empty">Tidak ada hasil yang cocok dengan query.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="padding:16px 20px"><details><summary>Lihat respons JSON</summary><pre style="white-space:pre-wrap;overflow:auto">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></div>
        </section>
    @endif
@endif

<div class="notice"><strong>Catatan:</strong> query dijalankan hanya jika Fuseki diaktifkan dan dataset <code>/siakadu</code> bisa diakses. Bila Fuseki belum dipasang, evaluasi masih menghasilkan file Turtle lokal yang dapat diunduh dari halaman Inference Logs.</div>
@endsection
