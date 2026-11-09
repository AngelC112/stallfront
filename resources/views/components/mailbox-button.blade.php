@props(['email'])
@php
    $web = \App\Support\MailboxLink::for($email);
    $app = \App\Support\MailboxLink::appLink($email, request()->userAgent());
@endphp
@if ($web || $app)
    {{-- Sul telefono prova ad aprire l'app; se la pagina resta visibile apre il sito del provider. --}}
    <a href="{{ $web ?? $app }}"
       @if ($app)
       data-app="{{ $app }}"
       onclick="var a=this.dataset.app,w=this.getAttribute('href');if(a){event.preventDefault();var t=setTimeout(function(){if(!document.hidden&&w&&w.indexOf('http')===0){location.href=w;}},1500);document.addEventListener('visibilitychange',function(){clearTimeout(t);},{once:true});location.href=a;}"
       @else
       target="_blank" rel="noopener"
       @endif
       class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        {{ __('Apri l\'app email') }}
    </a>
@endif
