<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Sanctum\PersonalAccessToken;

class AuthenticationController extends Controller
{
    // Login basic
    public function login_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-login-basic', ['pageConfigs' => $pageConfigs]);
    }

    // Login Cover
    public function login_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-login-cover', ['pageConfigs' => $pageConfigs]);
    }

    // Handle Login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except('password'));
        }

        $loginField = $request->input('email');
        $password = $request->input('password');
        // Default to persistent login so web auth survives long idle periods.
        $remember = $request->boolean('remember', true);

        // Determine if login field is email or username
        $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        
        $credentials = [
            $fieldType => $loginField,
            'password' => $password
        ];

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            $existingTokenId = $request->session()->get('auth_token_id');
            if ($existingTokenId) {
                PersonalAccessToken::whereKey($existingTokenId)->delete();
            }

            $existingToken = $request->session()->get('auth_token');
            if ($existingToken) {
                $previousToken = PersonalAccessToken::findToken($existingToken);
                if ($previousToken) {
                    $previousToken->delete();
                }
            }

            // Keep API auth in sync with web auth by issuing a Sanctum token.
            $tokenResult = $user->createToken('auth_token');
            $request->session()->put('auth_token', $tokenResult->plainTextToken);
            $request->session()->put('auth_token_id', $tokenResult->accessToken->id);

            if ($user && $user->hasRole('user')) {
                return redirect()->intended(route('app-invoice-preview'));
            }

            return redirect()->intended(route('dashboard-ecommerce'));
        }

        return redirect()->back()
            ->withErrors(['email' => 'Uneseni podaci se ne poklapaju sa našim zapisima.'])
            ->withInput($request->except('password'));
    }

    // Handle Logout
    public function logout(Request $request)
    {
        $tokenId = $request->session()->pull('auth_token_id');
        if ($tokenId) {
            PersonalAccessToken::whereKey($tokenId)->delete();
        }

        $plainTextToken = $request->session()->get('auth_token');
        if ($plainTextToken) {
            $currentToken = PersonalAccessToken::findToken($plainTextToken);
            if ($currentToken) {
                $currentToken->delete();
            }
            $request->session()->forget('auth_token');
        }

        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            $currentToken = PersonalAccessToken::findToken($bearerToken);
            if ($currentToken) {
                $currentToken->delete();
            }
        }

        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('auth-login-cover');
    }

    // Register basic
    public function register_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-register-basic', ['pageConfigs' => $pageConfigs]);
    }

    // Register cover
    public function register_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-register-cover', ['pageConfigs' => $pageConfigs]);
    }

    // Forgot Password basic
    public function forgot_password_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-forgot-password-basic', ['pageConfigs' => $pageConfigs]);
    }

    // Forgot Password cover
    public function forgot_password_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-forgot-password-cover', ['pageConfigs' => $pageConfigs]);
    }

    // Reset Password basic
    public function reset_password_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-reset-password-basic', ['pageConfigs' => $pageConfigs]);
    }

    // Reset Password cover
    public function reset_password_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-reset-password-cover', ['pageConfigs' => $pageConfigs]);
    }

    // email verify basic
    public function verify_email_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-verify-email-basic', ['pageConfigs' => $pageConfigs]);
    }

    // email verify cover
    public function verify_email_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-verify-email-cover', ['pageConfigs' => $pageConfigs]);
    }

    // two steps basic
    public function two_steps_basic()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-two-steps-basic', ['pageConfigs' => $pageConfigs]);
    }

    // two steps cover
    public function two_steps_cover()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-two-steps-cover', ['pageConfigs' => $pageConfigs]);
    }

    // register multi steps
    public function register_multi_steps()
    {
        $pageConfigs = ['blankPage' => true];

        return view('/content/authentication/auth-register-multisteps', ['pageConfigs' => $pageConfigs]);
    }
}
