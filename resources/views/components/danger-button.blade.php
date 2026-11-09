<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-11 items-center justify-center rounded-xl border-2 border-[#8a2a2a] bg-white px-5 text-sm font-bold text-[#8a2a2a] hover:bg-[#fbe3e3] focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
