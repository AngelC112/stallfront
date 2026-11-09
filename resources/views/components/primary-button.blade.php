<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
