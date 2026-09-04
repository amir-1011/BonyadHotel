<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

class PanelBreadcrumbBuilder
{
  /**
   * @return list<array{label: string, url: string|null}>
   */
  public static function build(string $panel): array
  {
    $route = RouteFacade::current();
    if (!$route || !str_starts_with((string) $route->getName(), "{$panel}.")) {
      return [];
    }

    $crumbs = [
      ['label' => 'داشبورد', 'url' => route("{$panel}.dashboard")],
    ];

    $name = (string) $route->getName();
  $suffix = substr($name, strlen($panel) + 1);

    return match (true) {
      $suffix === 'dashboard' => [['label' => 'داشبورد', 'url' => null]],
      $suffix === 'medical-accommodation-report' => self::append($crumbs, 'اسکان درمانی', null),

      str_starts_with($suffix, 'users.') => self::users($panel, $suffix, $crumbs),
      str_starts_with($suffix, 'host-positions.') => self::hostPositions($crumbs),
      str_starts_with($suffix, 'accommodations.') => self::accommodations($panel, $suffix, $route, $crumbs),
      str_starts_with($suffix, 'room-types.') => self::roomTypes($panel, $suffix, $route, $crumbs),
      str_starts_with($suffix, 'bookings') => self::bookings($panel, $suffix, $route, $crumbs),
      str_starts_with($suffix, 'cancellation-') => self::cancellation($panel, $suffix, $crumbs),
      str_starts_with($suffix, 'commission-wallet') => self::commissionWallet($panel, $suffix, $crumbs),
      str_starts_with($suffix, 'booking-payment-records') => self::append($crumbs, 'تراکنش‌های مالی', null),
      str_starts_with($suffix, 'pos-terminals') => self::append($crumbs, 'ترمینال‌های پز', null),
      str_starts_with($suffix, 'reviews.') => self::append($crumbs, 'نظرات', null),
      $suffix === 'veteran-policy' => self::append($crumbs, 'تعاریف اولیه', null),
      $suffix === 'location-catalog' => self::append($crumbs, 'استان‌ها و انواع', null),
      str_starts_with($suffix, 'programs.') => self::programs($panel, $suffix, $route, $crumbs),
      str_starts_with($suffix, 'facility.') => self::facility($panel, $suffix, $crumbs),
      $suffix === 'profile' => self::append($crumbs, 'پروفایل', null),

      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function hostPositions(array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'کاربران', route('admin.users.index'));

    return self::append($crumbs, 'سمت‌ها و دسترسی کاربر', null);
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function append(array $crumbs, string $label, ?string $url): array
  {
    $crumbs[] = ['label' => $label, 'url' => $url];

    return $crumbs;
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function users(string $panel, string $suffix, array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'کاربران', route("{$panel}.users.index"));

    return match ($suffix) {
      'users.index' => self::setLast($crumbs, 'کاربران'),
      'users.create-host' => self::append($crumbs, 'افزودن کاربر', null),
      'users.show' => self::append($crumbs, 'جزئیات کاربر', null),
      'users.edit' => self::append($crumbs, 'ویرایش کاربر', null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function accommodations(string $panel, string $suffix, Route $route, array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'اقامتگاه‌ها', route("{$panel}.accommodations.index"));
    $accommodation = $route->parameter('accommodation');

    return match ($suffix) {
      'accommodations.index' => self::setLast($crumbs, 'اقامتگاه‌ها'),
      'accommodations.import' => self::append($crumbs, 'درون‌ریزی گروهی', null),
      'accommodations.create' => self::append($crumbs, 'افزودن اقامتگاه', null),
      'accommodations.edit' => self::append($crumbs, self::modelLabel($accommodation, 'ویرایش اقامتگاه'), null),
      'accommodations.report' => self::append($crumbs, self::modelLabel($accommodation, 'گزارش فروش'), null),
      'accommodations.veteran-policy' => self::append($crumbs, self::modelLabel($accommodation, 'تعاریف اولیه'), null),
      'accommodations.cancellation-policy' => self::append($crumbs, self::modelLabel($accommodation, 'سیاست کنسلی'), null),
      'accommodations.medical-accommodation' => self::append($crumbs, self::modelLabel($accommodation, 'اسکان درمانی'), null),
      'accommodations.manual-booking' => self::append($crumbs, self::modelLabel($accommodation, 'رزرو دستی'), null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function roomTypes(string $panel, string $suffix, Route $route, array $crumbs): array
  {
    $accommodation = $route->parameter('accommodation');
    $roomType = $route->parameter('roomType');

    $crumbs = self::append($crumbs, 'اقامتگاه‌ها', route("{$panel}.accommodations.index"));
    if ($accommodation) {
      $crumbs = self::append(
        $crumbs,
        self::modelLabel($accommodation),
        route("{$panel}.accommodations.edit", $accommodation)
      );
    }
    $crumbs = self::append($crumbs, 'اتاق‌ها', $accommodation ? route("{$panel}.room-types.index", $accommodation) : null);

    return match ($suffix) {
      'room-types.index' => self::setLast($crumbs, 'اتاق‌ها'),
      'room-types.create' => self::append($crumbs, 'اتاق جدید', null),
      'room-types.edit' => self::append($crumbs, self::modelLabel($roomType, 'ویرایش اتاق'), null),
      'room-types.blocked-dates' => self::append($crumbs, self::modelLabel($roomType, 'تاریخ‌های مسدود'), null),
      'room-types.daily-availability' => self::append($crumbs, self::modelLabel($roomType, 'قیمت روزانه'), null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function bookings(string $panel, string $suffix, Route $route, array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'رزروها', route("{$panel}.bookings.index"));
    $booking = $route->parameter('booking');

    return match ($suffix) {
      'bookings.index' => self::setLast($crumbs, 'رزروها'),
      'bookings.show' => self::append(
        $crumbs,
        $booking ? 'رزرو ' . ($booking->tracking_code ?? $booking->id) : 'جزئیات رزرو',
        null
      ),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function cancellation(string $panel, string $suffix, array $crumbs): array
  {
    return match ($suffix) {
      'cancellation-settings' => self::append($crumbs, 'کنسلی و استرداد وجه', null),
      'cancellation-requests.index' => self::append($crumbs, 'درخواست‌های کنسلی', null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function commissionWallet(string $panel, string $suffix, array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'کیف پول کارمزد', route("{$panel}.commission-wallet"));

    return match ($suffix) {
      'commission-wallet' => self::setLast($crumbs, 'کیف پول کارمزد'),
      'commission-wallet.show' => self::append($crumbs, 'جزئیات تراکنش', null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function facility(string $panel, string $suffix, array $crumbs): array
  {
    return match (true) {
      str_starts_with($suffix, 'facility.surplus') => match ($suffix) {
        'facility.surplus.index' => self::append(
          self::append($crumbs, 'مدیریت اماکن', null),
          'اقلام مازاد',
          null,
        ),
        'facility.surplus.create' => self::append(
          self::append(self::append($crumbs, 'مدیریت اماکن', route("{$panel}.facility.surplus.index")), 'اقلام مازاد', route("{$panel}.facility.surplus.index")),
          'ثبت مورد',
          null,
        ),
        'facility.surplus.edit' => self::append(
          self::append(self::append($crumbs, 'مدیریت اماکن', route("{$panel}.facility.surplus.index")), 'اقلام مازاد', route("{$panel}.facility.surplus.index")),
          'ویرایش',
          null,
        ),
        default => $crumbs,
      },
      str_starts_with($suffix, 'facility.needed') => match ($suffix) {
        'facility.needed.index' => self::append(
          self::append($crumbs, 'مدیریت اماکن', null),
          'اقلام مورد نیاز',
          null,
        ),
        'facility.needed.create' => self::append(
          self::append(self::append($crumbs, 'مدیریت اماکن', route("{$panel}.facility.needed.index")), 'اقلام مورد نیاز', route("{$panel}.facility.needed.index")),
          'ثبت درخواست',
          null,
        ),
        'facility.needed.edit' => self::append(
          self::append(self::append($crumbs, 'مدیریت اماکن', route("{$panel}.facility.needed.index")), 'اقلام مورد نیاز', route("{$panel}.facility.needed.index")),
          'ویرایش درخواست',
          null,
        ),
        default => $crumbs,
      },
      str_starts_with($suffix, 'facility.catalog') => self::append(
        self::append($crumbs, 'مدیریت اماکن', route("{$panel}.facility.surplus.index")),
        'دسته‌بندی و برند',
        null,
      ),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function programs(string $panel, string $suffix, Route $route, array $crumbs): array
  {
    $crumbs = self::append($crumbs, 'برنامه‌ها و اردوها', route("{$panel}.programs.index"));
    $program = $route->parameter('program');

    return match ($suffix) {
      'programs.index' => self::setLast($crumbs, 'برنامه‌ها'),
      'programs.create' => self::append($crumbs, 'ثبت برنامه', null),
      'programs.supportive-report' => self::append($crumbs, 'خدمات حمایتی', null),
      'programs.show' => self::append($crumbs, self::modelLabel($program, 'جزئیات برنامه'), null),
      default => $crumbs,
    };
  }

  /**
   * @param  list<array{label: string, url: string|null}>  $crumbs
   * @return list<array{label: string, url: string|null}>
   */
  private static function setLast(array $crumbs, string $label): array
  {
    if ($crumbs === []) {
      return [['label' => $label, 'url' => null]];
    }

    $last = array_key_last($crumbs);
    $crumbs[$last]['label'] = $label;
    $crumbs[$last]['url'] = null;

    return $crumbs;
  }

  private static function modelLabel(mixed $model, ?string $fallback = null): string
  {
    if (is_object($model)) {
      return (string) ($model->name ?? $model->title ?? $fallback ?? 'جزئیات');
    }

    return $fallback ?? 'جزئیات';
  }
}
