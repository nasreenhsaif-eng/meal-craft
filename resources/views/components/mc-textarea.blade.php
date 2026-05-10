@props([
    'label' => null,
    'error' => null,
    'rows' => 5,
])

@php
    $boxNormal =
        'relative w-full rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] p-px shadow-sm transition-[border-color,box-shadow] duration-200 focus-within:border-[#5A6B44] focus-within:ring-2 focus-within:ring-[#5A6B44] focus-within:ring-offset-2';
    $boxError =
        'relative w-full rounded-[12px] border border-status-error bg-[#FFFFFF] p-px shadow-sm transition-[border-color,box-shadow] duration-200 focus-within:border-status-error';
    $boxClass = filled($error) ? $boxError : $boxNormal;

    $areaClass =
        'min-h-[120px] w-full resize-y appearance-none rounded-[11px] border-none bg-transparent px-[20px] py-3 font-montserrat text-sm font-medium tracking-tight text-[#364153] outline-none placeholder:text-[#364153]/50 disabled:cursor-not-allowed disabled:text-[#364153]/40';

    $wrapperClass = trim((string) $attributes->get('class'));
    $fieldAttributes = $attributes->except(['class']);

    $fieldId = $fieldAttributes->get('id');
    $errorId = filled($fieldId) ? $fieldId.'-error' : null;
@endphp

<div @class(['block w-full max-w-none text-left', $wrapperClass ?: null])>
    @if (filled($label))
        <label
            @if (filled($fieldId)) for="{{ $fieldId }}" @endif
            class="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94"
        >
            {{ $label }}
        </label>
    @endif

    <div class="{{ $boxClass }}">
        <textarea
            rows="{{ (int) $rows }}"
            {{ $fieldAttributes->merge(['class' => $areaClass]) }}
            aria-invalid="{{ filled($error) ? 'true' : 'false' }}"
            @if (filled($errorId) && filled($error)) aria-describedby="{{ $errorId }}" @endif
        ></textarea>
    </div>

    @if (filled($error))
        <p
            @if (filled($errorId)) id="{{ $errorId }}" @endif
            class="mt-1.5 text-sm text-status-error"
            role="alert"
        >
            {{ $error }}
        </p>
    @endif
</div>
