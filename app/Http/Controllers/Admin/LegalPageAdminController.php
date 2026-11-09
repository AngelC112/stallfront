<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalizedText;
use App\Support\LegalPages;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Legal pages written by the seller, one text per language.
 * Languages without a text use the built-in template.
 */
class LegalPageAdminController extends Controller
{
    public function index(): View
    {
        $pages = collect(LegalPages::PAGES)->mapWithKeys(fn (string $page): array => [$page => [
            'label' => LegalPages::label($page),
            'customized' => LocalizedText::localesFor(LegalPages::key($page)),
        ]]);

        return view('admin.legal.index', ['pages' => $pages]);
    }

    public function edit(Request $request, string $page): View
    {
        abort_unless(LegalPages::exists($page), 404);

        $locale = Locales::normalize($request->query('lang')) ?? app()->getLocale();
        $source = LegalPages::source($page, $locale);

        return view('admin.legal.edit', [
            'page' => $page,
            'label' => LegalPages::label($page),
            'locale' => $locale,
            'source' => $source,
            'customizedLocales' => LocalizedText::localesFor(LegalPages::key($page)),
            'placeholders' => LegalPages::placeholders(),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        abort_unless(LegalPages::exists($page), 404);

        $validated = $request->validate([
            'locale' => ['required', Rule::in(array_keys(Locales::all()))],
            'body' => ['required_unless:reset,1', 'nullable', 'string', 'max:60000'],
        ]);

        $locale = $validated['locale'];
        $body = str_replace("\r\n", "\n", (string) ($validated['body'] ?? ''));

        if ($request->boolean('reset') || trim($body) === trim(LegalPages::template($page, $locale))) {
            LocalizedText::remove(LegalPages::key($page), $locale);
        } else {
            LocalizedText::put(LegalPages::key($page), $locale, null, $body);
        }

        return redirect()
            ->route('admin.legal.edit', ['page' => $page, 'lang' => $locale])
            ->with('success', __('Testo salvato.'));
    }
}
