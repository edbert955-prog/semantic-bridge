@extends('layouts.semantic')
@section('title', 'Pipeline Overview')
@section('page-label', 'Pipeline Overview')
@section('content')
<div class="page-head"><div><div class="eyebrow">SEMANTIC INTEROPERABILITY HUB</div><h1>Pipeline Overview</h1><p>Ringkasan koneksi tiga database, ontology alignment, dan hasil evaluasi layanan akademik.</p></div><a class="btn primary" href="{{ route('semantic.mappings') }}">Lihat Pemetaan →</a></div>
<div class="status-banner {{ $allReady ? 'good' : 'warn' }}"><span class="dot"></span><div><strong>{{ $allReady ? 'Seluruh koneksi dan tabel utama tersedia' : 'Sebagian koneksi atau tabel belum siap' }}</strong><small>Periksa kartu koneksi di bawah. Katalog layanan pada backup Sistem A bisa tetap kosong meskipun tabelnya tersedia.</small></div></div>
<div class="stat-grid">
    <div class="stat"><span>Total mapping</span><strong>{{ number_format($stats['mappings']) }}</strong><small>{{ number_format($stats['active_mappings']) }} aktif</small></div>
    <div class="stat"><span>Evaluasi tersimpan</span><strong>{{ number_format($stats['evaluations']) }}</strong><small>Audit log inference</small></div>
    <div class="stat"><span>Layak</span><strong>{{ number_format($stats['eligible']) }}</strong><small>Semua bukti yang diperlukan terpenuhi</small></div>
    <div class="stat"><span>Perlu review</span><strong>{{ number_format($stats['review']) }}</strong><small>Evidence belum lengkap</small></div>
    <div class="stat"><span>Graph RDF lokal</span><strong>{{ number_format($stats['rdf_graphs']) }}</strong><small>{{ number_format($stats['rdf_verified']) }} terverifikasi via SPARQL</small></div>
</div>
<div class="section-title"><div><h2>Status koneksi</h2><p>Database tidak digabungkan; lapisan ini membaca sumber berdasarkan koneksi terpisah.</p></div></div>
<div class="connection-grid">
@foreach($connections as $key => $connection)
    <article class="connection-card"><div class="connection-head"><span class="connection-icon">{{ $key === 'metadata' ? '◇' : ($key === 'system_a' ? 'A' : 'B') }}</span><span class="badge {{ $connection['connected'] && $connection['ready'] ? 'good' : 'warn' }}">{{ $connection['connected'] ? ($connection['ready'] ? 'READY' : 'TABLE CHECK') : 'DISCONNECTED' }}</span></div><h3>{{ $connection['label'] }}</h3><div class="mono db-name">{{ $connection['database'] }}</div><div class="table-status">@foreach($connection['tables_state'] as $table => $exists)<span class="{{ $exists ? 'table-ok' : 'table-missing' }}">{{ $exists ? '✓' : '!' }} {{ $table }}</span>@endforeach</div></article>
@endforeach
</div>
<section class="panel" style="margin:0 0 22px">
    <div class="panel-head">
        <div><h2>RDF &amp; SPARQL Engine</h2><p>Status graph RDF lokal dan triple store eksternal.</p></div>
        <a class="btn primary" href="{{ route('semantic.sparql') }}">Buka SPARQL Console →</a>
    </div>
    <div style="padding:18px 20px;display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <span class="badge good">Turtle builder aktif</span>
        <span class="badge {{ config('semantic.reasoner.enabled') ? 'good' : 'warn' }}">OWL 2 RL: {{ config('semantic.reasoner.enabled') ? 'enabled (cek log tiap evaluasi)' : 'not configured' }}</span>
        <span class="badge {{ ($fusekiStatus['status'] ?? '') === 'ready' ? 'good' : 'warn' }}">Fuseki: {{ $fusekiStatus['status'] ?? 'unknown' }}</span>
        <span class="muted">{{ $fusekiStatus['message'] ?? '' }}</span>
    </div>
</section>
<div class="two-col">
<section class="panel"><div class="panel-head"><div><h2>Pemetaan terbaru</h2><p>Relasi source-to-target yang tersimpan</p></div><a href="{{ route('semantic.mappings') }}">Semua →</a></div><div class="table-wrap"><table><thead><tr><th>Sumber</th><th>Target</th><th>Tipe</th></tr></thead><tbody>@forelse($recentMappings as $mapping)<tr><td><span class="mono">{{ $mapping->source_system }}.{{ $mapping->source_table }}.{{ $mapping->source_column }}</span></td><td><span class="mono">{{ $mapping->target_entity }}.{{ $mapping->target_property }}</span></td><td><span class="badge">{{ $mapping->mapping_type }}</span></td></tr>@empty<tr><td colspan="3" class="empty">Belum ada mapping. Jalankan seeder.</td></tr>@endforelse</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Inference log</h2><p>Keputusan terakhir oleh rule evaluator</p></div><a href="{{ route('semantic.logs') }}">Semua →</a></div><div class="log-list">@forelse($recentLogs as $log)<div class="log-row"><span class="log-state {{ $log->result_status }}"></span><div class="log-info"><strong>{{ $log->service_code }} · {{ $log->applicant_npm ?: 'NPM tidak tersedia' }}</strong><small>{{ $log->result_status }} · {{ $log->created_at?->format('d M Y H:i:s') }}</small></div></div>@empty<div class="empty">Belum ada hasil evaluasi tersimpan.</div>@endforelse</div></section>
</div>
<div class="notice"><strong>Batasan implementasi:</strong> Laravel menghasilkan graph Turtle per evaluasi dan menyediakan SPARQL API. Query pada triple store hanya aktif setelah Apache Jena Fuseki dikonfigurasi. Aturan kelayakan merupakan rule engine aplikasi; jangan mengklaim reasoner OWL 2 RL formal sebelum memasang serta memvalidasi reasoner/profile yang sesuai.</div>
@endsection
