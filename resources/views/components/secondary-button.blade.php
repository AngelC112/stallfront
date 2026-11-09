<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-secondary focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
