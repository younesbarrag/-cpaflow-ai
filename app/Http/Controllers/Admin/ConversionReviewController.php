<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversion;
use Illuminate\Contracts\View\View;

class ConversionReviewController extends Controller
{
    public function index(): View
    {
        $conversions = Conversion::with([
            'campaign' => fn ($q) => $q->with('offer'),
        ])
            ->pending()
            ->oldest('converted_at')
            ->paginate(20);

        return view('admin.conversions.index', compact('conversions'));
    }
}
