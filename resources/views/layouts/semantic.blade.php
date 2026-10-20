<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Semantic Bridge') · SIAKADU</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/semantic-bridge.css') }}">
</head>
<body>
<div class="shell">
    <aside class="side">
        <a class="brand" href="{{ route('semantic.dashboard') }}"><span class="brand-mark">SB</span><span><strong>SEM-WEB BRIDGE</strong><small>Academic Services</small></span></a>
        <div class="status-mini"><span class="dot"></span> Semantic Hub v2.0 <small>Laravel + RDF</small></div>
        <div class="nav-label">WORKSPACE</div>
        <nav>
            <a class="nav-item {{ request()->routeIs('semantic.dashboard') ? 'active' : '' }}" href="{{ route('semantic.dashboard') }}"><span>▦</span> Pipeline Overview</a>
            <a class="nav-item {{ request()->routeIs('semantic.mappings') ? 'active' : '' }}" href="{{ route('semantic.mappings') }}"><span>⌘</span> Ontology Mappings</a>
            <a class="nav-item {{ request()->routeIs('semantic.ontologies') ? 'active' : '' }}" href="{{ route('semantic.ontologies') }}"><span>◈</span> RDF / OWL Files</a>
            <a class="nav-item {{ request()->routeIs('semantic.logs') ? 'active' : '' }}" href="{{ route('semantic.logs') }}"><span>≋</span> Inference Logs</a>
            <a class="nav-item {{ request()->routeIs('semantic.sparql*') ? 'active' : '' }}" href="{{ route('semantic.sparql') }}"><span>⌕</span> SPARQL Console</a>
        </nav>
        <div class="side-bottom"><div class="side-warning">Development dashboard<br><small>Gunakan hanya di lingkungan lokal.</small></div><div class="version">SEMANTIC BRIDGE · 2.0.0</div></div>
    </aside>
    <main class="main">
        <header class="topbar"><div><span class="muted">SIAKADU</span><span class="crumb">/</span><strong>@yield('page-label', 'Pipeline Overview')</strong></div><div class="top-right"><span class="top-pill"><i class="dot"></i> Ontology Alignment Hub</span><span class="mono">LOCAL · :8002</span></div></header>
        <section class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            @yield('content')
        </section>
        <footer class="footer">SIAKADU Semantic Bridge · Data sumber dibaca dari database Sistem A dan Sistem B.</footer>
    </main>
</div>
</body>
</html>
