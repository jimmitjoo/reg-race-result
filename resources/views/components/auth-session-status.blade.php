@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-moss']) }}>
        {{ $status }}
    </div>
@endif
