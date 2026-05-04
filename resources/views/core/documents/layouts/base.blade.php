<!doctype html>
<html lang="{{ $language }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    @include('core.documents.layouts.styles')
    @include('core.documents.layouts.bilingual-styles')
</head>
<body class="{{ $direction === 'rtl' ? 'rtl' : 'ltr' }}">
    @include($contentView, $data)
</body>
</html>
