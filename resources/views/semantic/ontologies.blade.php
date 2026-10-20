@extends('layouts.semantic')
@section('title', 'RDF / OWL Files')
@section('page-label', 'RDF / OWL Files')
@section('content')
<div class="page-head"><div><div class="eyebrow">KNOWLEDGE REPRESENTATION</div><h1>RDF / OWL Ontologies</h1><p>File Turtle untuk workflow Sistem A, vocabulary akademik Sistem B, dan ontology alignment.</p></div></div>
<div class="ontology-grid">@foreach($ontologies as $ontology)<article class="ontology-card"><div class="ontology-icon">◈</div><span class="badge {{ $ontology['exists'] ? 'good' : 'bad' }}">{{ $ontology['exists'] ? 'FILE READY' : 'FILE MISSING' }}</span><h2>{{ $ontology['name'] }}.ttl</h2><p class="mono uri">{{ $ontology['uri'] }}</p><div class="ontology-footer"><span>{{ $ontology['exists'] ? number_format($ontology['size']) . ' bytes' : 'Belum tersedia' }}</span>@if($ontology['exists'])<a href="{{ route('semantic.ontology.download', $ontology['name']) }}" target="_blank" rel="noreferrer">Lihat Turtle ↗</a>@endif</div></article>@endforeach</div>
<div class="notice"><strong>Catatan:</strong> file TTL mendeskripsikan kelas, properti, dan alignment. Untuk inferensi OWL 2 RL/SPARQL sesungguhnya, tahap berikutnya adalah memuat file ini ke Apache Jena/Fuseki atau reasoner yang kompatibel.</div>
@endsection
