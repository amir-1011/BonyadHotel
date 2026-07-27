<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminUsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NationalIdVerificationService;
use App\Support\VeteranGroups;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function export(Request $request)
    {
        $section = (string) $request->input('section', 'all');
        $role    = (string) $request->input('role', '');

        if ($section === 'users') {
            $role = 'guest';
        } elseif ($section === 'roles' && $role === '') {
            $role = null;
        } elseif ($section === 'all' || $section === '') {
            $role = null;
        }

        $filters = array_filter([
            'search' => $request->input('search'),
            'role'   => $role,
        ]);

        return Excel::download(new AdminUsersExport($filters), 'users.xlsx');
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
        $veteranKeys = array_keys(VeteranGroups::options());

        $request->validate([
            'name'        => ['nullable', 'string', 'max:100'],
            'mobile'      => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile,' . $user->id],
            'national_id' => ['nullable', 'digits:10', 'unique:users,national_id,' . $user->id],
            'veteran_type'=> ['nullable', 'string', Rule::in($veteranKeys)],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
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
        } elseif ($request->has('veteran_type')) {
            $veteranType = $request->input('veteran_type') ?: null;
            $data['veteran_type'] = $veteranType;
            $data['discount_percentage'] = $veteranType
                ? (int) ($request->input('discount_percentage') ?? VeteranGroups::accommodationDiscount($veteranType))
                : 0;

            if (!$request->filled('national_id')) {
                $data['national_id'] = $user->national_id;
            }
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
