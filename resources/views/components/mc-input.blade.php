@props([
    'label' => null,
    'error' => null,
])

@php
    $boxNormal =
        'relative flex h-[49px] w-full items-center rounded-[12px] border border-[#E5E7EB] bg-[#FFFFFF] shadow-sm transition-[border-color,box-shadow] duration-200 focus-within:border-[#5A6B44] focus-within:ring-2 focus-within:ring-[#5A6B44] focus-within:ring-offset-2';
    $boxError =
        'relative flex h-[49px] w-full items-center rounded-[12px] border border-status-error bg-[#FFFFFF] shadow-sm transition-[border-color,box-shadow] duration-200 focus-within:border-status-error';
    $boxClass = filled($error) ? $boxError : $boxNormal;

    $inputClass =
        'h-full min-w-0 w-full flex-1 appearance-none border-none bg-transparent px-[20px] font-montserrat text-[16px] font-medium tracking-tight text-[#364153] outline-none placeholder:text-[#364153]/50 disabled:cursor-not-allowed disabled:text-[#364153]/40 disabled:placeholder:text-[#364153]/25';

    $wrapperClass = trim((string) $attributes->get('class'));
    $inputAttributes = $attributes->except(['class']);

    $inputId = $inputAttributes->get('id');
    $errorId = filled($inputId) ? $inputId.'-error' : null;
@endphp

<div @class(['block w-full max-w-none text-left', $wrapperClass ?: null])>
    @if (filled($label))
        <label
            @if (filled($inputId)) for="{{ $inputId }}" @endif
            class="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94"
        >
            {{ $label }}
        </label>
    @endif
    <div class="{{ $boxClass }}">
        <input
            {{ $inputAttributes->merge(['class' => $inputClass]) }}
            aria-invalid="{{ filled($error) ? 'true' : 'false' }}"
            @if (filled($errorId) && filled($error)) aria-describedby="{{ $errorId }}" @endif
        />
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
