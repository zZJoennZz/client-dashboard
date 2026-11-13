<?php

namespace Tests\Feature;

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PublicPageController;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginViewResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_are_named_and_served_by_controllers(): void
    {
        $routes = [
            'home' => [PublicPageController::class, 'home'],
            'benefits' => [PublicPageController::class, 'benefits'],
            'businesses.index' => [BusinessController::class, 'index'],
            'businesses.show' => [BusinessController::class, 'show'],
            'pricing' => [PublicPageController::class, 'pricing'],
            'contact' => [PublicPageController::class, 'contact'],
        ];

        foreach ($routes as $name => [$controller, $method]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertInstanceOf(RoutingRoute::class, $route);
            $this->assertSame($controller.'@'.$method, $route->getAction('uses'));
        }
    }

    public function test_each_public_page_renders_its_intended_view(): void
    {
        $pages = [
            ['/', 'pages.home'],
            ['/benefits', 'pages.benefits'],
            ['/businesses', 'businesses.index'],
            ['/pricing', 'pages.pricing'],
            ['/contact', 'pages.contact'],
        ];

        foreach ($pages as [$uri, $view]) {
            $this->get($uri)->assertOk()->assertViewIs($view);
        }
    }

    public function test_homepage_uses_configured_membership_details_and_includes_faq(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('membership.price_label', 'Pricing coming soon')
            ->assertSee('Support Argyle.')
            ->assertSee('How it works')
            ->assertSee('Frequently asked questions')
            ->assertSee('Membership pricing is still being finalized.');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('storage/branding/the-argyle-buzz-logo.png'), false)
            ->assertSee(asset('storage/img/Hero Section.jpg'), false);

        config(['membership.amount' => '200']);

        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('membership.price_label', '$200.00 / year')
            ->assertSee('$200.00 / year');
    }

    public function test_directory_renders_businesses_passed_from_the_controller(): void
    {
        $business = $this->publishedBusiness([
            'company_name' => 'Roanoke Coffee',
            'slug' => 'roanoke-coffee',
            'short_description' => 'Locally roasted.',
        ]);

        $response = $this->get(route('businesses.index'));

        $response->assertOk()
            ->assertViewIs('businesses.index')
            ->assertViewHas('businesses', fn ($businesses): bool => $businesses->total() === 1
                && $businesses->first()->is($business))
            ->assertSee('Roanoke Coffee')
            ->assertSee(route('businesses.show', 'roanoke-coffee'));
    }

    public function test_directory_search_and_category_filters_match_business_data(): void
    {
        $this->publishedBusiness([
            'company_name' => 'Mill Mountain Makers',
            'slug' => 'mill-mountain-makers',
            'city' => 'Grandin Village',
        ], 'Arts & retail');
        $this->publishedBusiness([
            'company_name' => 'Blue Ridge Coffee Co.',
            'slug' => 'blue-ridge-coffee',
            'city' => 'Downtown Roanoke',
        ], 'Food & drink');

        $this->get(route('businesses.index', ['q' => 'Grandin', 'category' => 'arts-retail']))
            ->assertOk()
            ->assertViewHas('businesses', fn ($businesses): bool => $businesses->total() === 1
                && $businesses->first()->slug === 'mill-mountain-makers')
            ->assertSee('Mill Mountain Makers')
            ->assertDontSee('Blue Ridge Coffee Co.');

        $this->get(route('businesses.index', ['q' => 'no matching business']))
            ->assertOk()
            ->assertSee('No matches just yet.')
            ->assertSee('Clear filters');
    }

    public function test_shared_layout_includes_accessible_mobile_navigation_and_site_links(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<header class="sticky top-0', false)
            ->assertSee('aria-label="Navigation menu"', false)
            ->assertSee(route('login'))
            ->assertSee(route('businesses.index'));
    }

    public function test_fortify_login_route_renders_a_csrf_protected_login_view(): void
    {
        $this->assertTrue($this->app->bound(LoginViewResponse::class));

        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Welcome back')
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee(route('password.request'));
    }

    public function test_fortify_password_reset_views_are_bound_and_rendered(): void
    {
        $this->assertTrue($this->app->bound(RequestPasswordResetLinkViewResponse::class));
        $this->assertTrue($this->app->bound(ResetPasswordViewResponse::class));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertViewIs('auth.forgot-password')
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('password.email').'"', false);

        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertViewIs('auth.reset-password')
            ->assertSee('value="'.$token.'"', false)
            ->assertSee('value="'.$user->email.'"', false);
    }

    public function test_fortify_password_reset_link_and_reset_password_flow_work(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);
        $newPassword = 'A-new-secure-password-2026';

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_business_profile_is_found_by_slug_and_unknown_slug_returns_404(): void
    {
        $this->publishedBusiness([
            'company_name' => 'Blue Ridge Coffee Co.',
            'slug' => 'blue-ridge-coffee',
            'address_line_1' => '123 Market Street',
            'city' => 'Roanoke',
            'state' => 'VA',
            'postal_code' => '24011',
        ]);

        $this->get(route('businesses.show', 'blue-ridge-coffee'))
            ->assertOk()
            ->assertViewIs('businesses.show')
            ->assertViewHas('business.name', 'Blue Ridge Coffee Co.')
            ->assertViewHas('business.google_maps_url', 'https://www.google.com/maps/search/?api=1&query=123%20Market%20Street%2C%20Roanoke%2C%20VA%2C%2024011%2C%20US');

        $this->get(route('businesses.show', 'not-a-real-business'))->assertNotFound();
    }

    public function test_unpublished_businesses_never_appear_in_public_directory_or_profile(): void
    {
        Business::factory()->create([
            'company_name' => 'Private Preview Business',
            'slug' => 'private-preview-business',
            'is_published' => false,
            'directory_visibility' => 'private',
        ]);

        $this->get(route('businesses.index'))->assertDontSee('Private Preview Business');
        $this->get(route('businesses.show', 'private-preview-business'))->assertNotFound();
    }

    private function publishedBusiness(array $attributes = [], string $categoryName = 'Local Services'): Business
    {
        $business = Business::factory()->create(array_replace([
            'is_active' => true,
            'is_published' => true,
            'directory_visibility' => 'public',
        ], $attributes));
        $category = BusinessCategory::query()->firstOrCreate(
            ['name' => $categoryName],
            ['slug' => Str::slug($categoryName)]
        );
        $business->categories()->attach($category->id, ['is_primary' => true]);

        return $business;
    }

    public function test_pricing_uses_configurable_membership_amount(): void
    {
        config(['membership.amount' => '125.00']);

        $this->get(route('pricing'))->assertOk()->assertSee('$125.00 / year');
    }
}
