<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NationalIdVerificationService;
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

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'        => ['nullable', 'string', 'max:100'],
            'mobile'      => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,' . $user->id],
            'national_id' => ['nullable', 'digits:10', 'unique:users,national_id,' . $user->id],
            'role'        => ['nullable', 'string', 'exists:roles,name'],
        ], [
            'mobile.required'    => 'شماره موبایل الزامی است.',
            'mobile.regex'       => 'شماره موبایل معتبر نیست. مثال: 09123456789',
            'mobile.unique'      => 'این شماره موبایل قبلاً ثبت شده است.',
            'national_id.digits' => 'کد ملی باید ۱۰ رقم باشد.',
            'national_id.unique' => 'این کد ملی قبلاً ثبت شده است.',
            'role.exists'        => 'نقش انتخاب‌شده معتبر نیست.',
        ]);

        $data = [
            'name'   => $request->input('name'),
            'mobile' => $request->input('mobile'),
        ];

        if ($request->input('mobile') !== $user->mobile) {
            $data['mobile_verified_at'] = null;
        }

        if ($request->filled('national_id')) {
            $result = app(NationalIdVerificationService::class)->verify($request->input('national_id'));

            if (! $result['valid']) {
                return back()->withErrors(['national_id' => $result['message']])->withInput();
            }

            $data['national_id'] = $request->input('national_id');
            $data['veteran_type'] = $result['veteran_type'];
            $data['discount_percentage'] = $result['discount'];
            $data['national_id_verified_at'] = now();
        } else {
            $data['national_id'] = null;
            $data['veteran_type'] = null;
            $data['discount_percentage'] = 0;
            $data['national_id_verified_at'] = null;
        }

        $user->update($data);

        if ($request->filled('role')) {
            $user->syncRoles([$request->input('role')]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'اطلاعات کاربر با موفقیت ویرایش شد.');
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
