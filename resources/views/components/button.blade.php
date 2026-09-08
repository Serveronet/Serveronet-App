<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-secondary ml-2']) }}>
    {{ $slot }}
</button>