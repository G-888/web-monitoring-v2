<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $isFirstUser = User::count() === 0;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_approved' => $isFirstUser,
        ]);

        // Ensure roles exist (in case the database was wiped)
        if (!\Spatie\Permission\Models\Role::where('name', 'Super Admin')->exists()) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        }

        // If this is the first user, make them a Super Admin
        if ($isFirstUser) {
            $user->assignRole('Super Admin');
        } else {
            // Default role for others
            $user->assignRole('Viewer');
        }

        event(new Registered($user));

        if ($user->is_approved) {
            Auth::login($user);
            return redirect(route('dashboard', absolute: false));
        }

        return redirect()->route('approval.pending');
    }
}
