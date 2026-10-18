<?php

namespace App\Providers;

use App\Http\Controllers\RegistrationController;
use App\Http\Responses\LoginResponse;
use App\Models\User;
use App\Services\MemberActivityRecorder;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView('auth.login');
        Fortify::registerView('auth.register');
        Fortify::createUsersUsing(RegistrationController::class);
        Gate::define('super-admin', fn (User $user): bool => $user->isSuperAdmin());
        Event::listen(Login::class, function (Login $login): void {
            if (! ($login->user instanceof User)) {
                return;
            }

            $login->user->forceFill(['last_login_at' => now()])->saveQuietly();
            if ($login->user->isSuperAdmin() || ! request()->hasSession()) {
                return;
            }

            $session = request()->session();
            $recordedKey = 'member_login_activity_recorded_'.$login->user->id;
            if ($session->get($recordedKey, false)) {
                return;
            }

            $businesses = $login->user->businesses()
                ->wherePivot('status', 'active')
                ->orderByPivot('is_primary', 'desc')
                ->get(['clients.id']);
            $recorder = app(MemberActivityRecorder::class);
            if ($businesses->isEmpty()) {
                $recorder->record($login->user, 'login');
            } else {
                foreach ($businesses as $business) {
                    $recorder->record($login->user, 'login', $business->id);
                }
            }
            $session->put($recordedKey, true);
        });

        config([
            'fortify.home' => '/dashboard',
            'fortify.redirects.register' => '/dashboard',
        ]);
    }
}
