<?php

namespace App\Http\Controllers;

use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Suggerimenti di indirizzo per il campo con completamento automatico (registrazione e account).
 */
class AddressSuggestionController extends Controller
{
    public function __invoke(Request $request, AddressService $addresses): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:120']]);

        return response()->json($addresses->suggest((string) $request->query('q')));
    }
}
