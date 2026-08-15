@props([
    'label' => 'محتوا',
    'hint' => 'محتوا با Markdown ذخیره می‌شود.',
    'placeholder' => 'محتوا را بنویسید...',
])

@php
    $config = [
        'direction' => 'rtl',
        'spellChecker' => false,
        'status' => false,
        'uploadImage' => false,
        'minHeight' => '22rem',
        'maxHeight' => '62vh',
        'placeholder' => $placeholder,
        'toolbar' => [
            'undo',
            'redo',
            '|',
            'heading',
            'bold',
            'italic',
            '|',
            'unordered-list',
            'ordered-list',
            'quote',
            'code',
            'table',
            '|',
            'link',
            'horizontal-rule',
            '|',
            'preview',
            'side-by-side',
            'fullscreen',
        ],
    ];
@endphp

<x-markdown
    :label="$label"
    :hint="$hint"
    :config="$config"
    {{ $attributes }}
/>
