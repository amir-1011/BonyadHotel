<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')->latest();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('mobile', 'like', "%$q%")
                  ->orWhere('national_id', 'like', "%$q%");
            });
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function show(User $user)
    {
        $user->load('roles', 'bookings.accommodation.city', 'accommodations.city', 'reviews');
        return view('admin.users.show', compact('user'));
    }

    public function assignRole(Request $request, User $user)
    {
        $request->validate(['role' => ['required', 'string', 'exists:roles,name']]);
        $user->syncRoles([$request->role]);
        return back()->with('status', 'نقش کاربر با موفقیت تغییر یافت.');
    }

    public function destroy(User $user)
    {
        abort_if($user->hasRole('super_admin'), 403, 'حذف سوپر ادمین مجاز نیست.');
        $user->delete();
        return redirect()->route('admin.users.index')->with('status', 'کاربر حذف شد.');
    }

    public function toggleStatus(User $user)
    {
        // We use national_id_verified_at as a "soft ban" mechanism — null = banned
        if ($user->mobile_verified_at) {
            $user->update(['mobile_verified_at' => null]);
            $msg = 'کاربر غیرفعال شد.';
        } else {
            $user->update(['mobile_verified_at' => now()]);
            $msg = 'کاربر فعال شد.';
        }
        return back()->with('status', $msg);
    }
}
