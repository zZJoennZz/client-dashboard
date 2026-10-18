<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\PlatformSetting;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        $featuredBusinesses = Business::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('is_featured', true)
            ->where('directory_visibility', 'public')
            ->with('categories')
            ->orderBy('company_name')
            ->limit(3)
            ->get()
            ->map(fn (Business $business): array => [
                'name' => $business->company_name,
                'slug' => $business->slug,
                'category' => $business->categories->firstWhere('pivot.is_primary', true)?->name
                    ?? data_get($business->settings, 'category', 'Local business'),
                'description' => $business->short_description ?: $business->description ?: $business->tagline,
            ])
            ->all();
        $membership = $this->membershipDetails();

        return view('pages.home', compact('featuredBusinesses', 'membership'));
    }

    public function benefits(): View
    {
        return view('pages.benefits');
    }

    public function pricing(): View
    {
        $membership = $this->membershipDetails();

        return view('pages.pricing', compact('membership'));
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    /**
     * @return array{amount: mixed, currency_symbol: string, period: string, price_label: string}
     */
    private function membershipDetails(): array
    {
        $membership = config('membership');
        $membership['amount'] = PlatformSetting::getValue('membership.amount', $membership['amount']);
        $membership['currency_symbol'] = PlatformSetting::getValue('membership.currency_symbol', $membership['currency_symbol']);
        $membership['period'] = PlatformSetting::getValue('membership.period', $membership['period']);
        $amount = $membership['amount'];
        $membership['price_label'] = is_numeric($amount)
            ? $membership['currency_symbol'].number_format((float) $amount, 2).' / '.$membership['period']
            : 'Pricing coming soon';

        return $membership;
    }
}
