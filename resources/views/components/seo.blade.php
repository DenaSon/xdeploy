@props(['seo'])

<title>{{ $seo->title }}</title>
<meta name="description" content="{{ $seo->description }}">
<meta name="robots" content="{{ $seo->robots }}">
<link rel="canonical" href="{{ $seo->canonical }}">

@if($seo->googleSiteVerification)
    <meta name="google-site-verification" content="{{ $seo->googleSiteVerification }}">
@endif

@if($seo->bingSiteVerification)
    <meta name="msvalidate.01" content="{{ $seo->bingSiteVerification }}">
@endif

<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:title" content="{{ $seo->title }}">
<meta property="og:description" content="{{ $seo->description }}">
<meta property="og:url" content="{{ $seo->canonical }}">
<meta property="og:site_name" content="Coreflare">
<meta property="og:locale" content="{{ $seo->locale }}">

@if($seo->image)
    <meta property="og:image" content="{{ $seo->image }}">
@endif

@if($seo->publishedTime)
    <meta property="article:published_time" content="{{ $seo->publishedTime }}">
@endif

@if($seo->modifiedTime)
    <meta property="article:modified_time" content="{{ $seo->modifiedTime }}">
@endif

<meta name="twitter:card" content="{{ $seo->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->title }}">
<meta name="twitter:description" content="{{ $seo->description }}">

@if($seo->image)
    <meta name="twitter:image" content="{{ $seo->image }}">
@endif

@if($seo->schema !== [])
    <script type="application/ld+json">{!! json_encode(
        $seo->schema,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT,
    ) !!}</script>
@endif
