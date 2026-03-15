<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\Salesperson;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class AuthController extends Controller
{
    
    public function index()
    {
        try{
            if (Auth::guard('sales')->check()) {
                    return redirect()->route('user.dashboard');
            }else{
                return redirect()->route('user.login');
            }

        }
        catch(Exception $e){
            return back()->with("error",$e->getMessage());
        }
    }

    public function login()
    {
        return view("user.auth.login");
    }

    public function postLogin(Request $request)
    {
        try{
            $request->validate([
                "mobile" => "required",
                "password" => "required",
            ]);
            $user = Salesperson::where('mobile', $request->mobile)->first();
            if($user){
                if (Auth::guard('sales')->attempt($request->only('mobile', 'password')))
                {
                    $request->session()->regenerate();
                    return redirect()->route("user.dashboard")->with("success", "Welcome to your dashboard.");
                }
                return back()->with("error","Invalid credentials");
            }else{
                return back()->with("error","Invalid credentials");
            }

        }
        catch(Exception $e){
            return back()->with("error",$e->getMessage());
        }
    }

    public function updatePassword(Request $request)
    {
        try{
            $request->validate([
                "old_password" => "required",
                "new_password" => "required|confirmed",
            ]);
            $salesperson = Auth::guard('sales')->user();

            if (!Hash::check($request->old_password, $salesperson->password)) {
                return back()->with("error", "Old Password Doesn't match!");
            }

            Salesperson::whereKey($salesperson->id)->update([
                "password" => Hash::make($request->new_password),
            ]);

            return back()->with("success", "Password changed successfully!");
        }
        catch(Exception $e){
            return back()->with("error",$e->getMessage());
        }
    }

    

    public function logout()
    {
        try{
            Auth::guard('sales')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route("user.login")->withSuccess('Logout Successful!');
        }
        catch(Exception $e){
            return back()->with("error",$e->getMessage());
        }
    }

    public function profile()
    {
        try{
            $user = Auth::guard('sales')->user();
            return view("user.auth.profile", compact("user"));

        }
        catch(Exception $e){
            return back()->with("error",$e->getMessage());
        }
    }

    public function updateProfile(Request $request)
    {
        try
        {
            $user = Auth::guard('sales')->user();
            $data = $request->all();
            $validator = Validator::make($data,[
                "name" => "required",
                "mobile" => "required|min:9|unique:salespersons,mobile," .$user->id,
            ]);
            
            if($validator->fails()) {
                return redirect()->back()->withInput($request->all())->withErrors($validator->errors());
            }
        
            $user->name = $request->name;
            $user->mobile = $request->mobile;
            $user->save();
            return redirect()->back()->with("success", "Profile update successfully!");
        }
        catch (Exception $e) {
            return redirect()->back()->with("error", $e->getMessage());
        }
    }

    /**
     * Admin Dashboard Data
     * Fetch summary statistics for dashboard cards
     */
    public function dashboard()
    {   
        return view('user.dashboard.index');
    }


}
