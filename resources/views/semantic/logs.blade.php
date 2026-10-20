@extends('layouts.semantic')
@section('title', 'Inference Logs')
@section('page-label', 'Inference Logs')
@section('content')
<div class="page-head"><div><div class="eyebrow">AUDIT TRAIL</div><h1>Inference Logs</h1><p>Hasil pemeriksaan, evidence, jejak keputusan, dan status pembentukan graph RDF.</p></div></div>
<form class="filters" method="GET" action="{{ route('semantic.logs') }}">
    <input name="npm" value="{{ request('npm') }}" placeholder="Filter NPM...">
    <select name="status"><option value="">Semua hasil</option>@foreach(['eligible'=>'Layak','ineligible'=>'Tidak layak','needs_manual_review'=>'Perlu review','error'=>'Error'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select>
    <select name="rdf_status"><option value="">Semua status RDF</option>@foreach(['pending','local_only','published_verified','publish_failed','generation_failed'] as $state)<option value="{{ $state }}" @selected(request('rdf_status')===$state)>{{ $state }}</option>@endforeach</select>
    <button class="btn primary" type="submit">Filter</button><a class="btn" href="{{ route('semantic.logs') }}">Reset</a>
</form>
<section class="panel"><div class="panel-head"><div><h2>Riwayat evaluasi</h2><p>{{ number_format($logs->total()) }} evaluasi ditemukan</p></div></div><div class="table-wrap"><table><thead><tr><th>Waktu</th><th>NPM</th><th>Layanan</th><th>Hasil</th><th>RDF / SPARQL</th><th>Alasan / evidence / trace</th><th>Request UUID</th></tr></thead><tbody>
@forelse($logs as $log)
<tr>
    <td>{{ $log->evaluated_at?->format('d M Y H:i:s') ?? $log->created_at?->format('d M Y H:i:s') }}</td>
    <td class="mono">{{ $log->applicant_npm ?: '—' }}</td><td>{{ $log->service_code }}</td>
    <td><span class="badge {{ $log->result_status === 'eligible' ? 'good' : ($log->result_status === 'ineligible' ? 'bad' : 'warn') }}">{{ $log->result_status }}</span></td>
    <td><span class="badge {{ $log->rdf_status === 'published_verified' ? 'good' : ($log->rdf_status === 'publish_failed' || $log->rdf_status === 'generation_failed' ? 'bad' : 'warn') }}">{{ $log->rdf_status ?: 'not generated' }}</span><small style="display:block;margin-top:5px">{{ $log->rdf_triple_count ?? 0 }} triples · reasoner {{ $log->reasoner_status ?? 'not configured' }}</small>@if($log->rdf_graph_uri)<a href="{{ route('semantic.graph.download', $log->request_uuid) }}">Unduh Turtle</a>@endif</td>
    <td><details><summary>{{ count($log->reasons ?? []) }} alasan · {{ count($log->evidence ?? []) }} bukti · {{ count($log->reasoning_trace ?? []) }} langkah</summary><pre>{{ json_encode(['reasons'=>$log->reasons,'evidence'=>$log->evidence,'trace'=>$log->reasoning_trace,'graph_uri'=>$log->rdf_graph_uri,'sparql_verified'=>$log->sparql_verified], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
    <td class="mono">{{ $log->request_uuid }}</td>
</tr>
@empty<tr><td colspan="7" class="empty">Belum ada log. Kirim request ke endpoint eligibility untuk menjalankan evaluasi.</td></tr>@endforelse
</tbody></table></div><div class="pagination"><span>{{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }}</span>{{ $logs->links() }}</div></section>
@endsection
