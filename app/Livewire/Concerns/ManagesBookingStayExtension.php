<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Services\BookingStayExtensionService;
use App\Support\JalaliDateTimeInput;
use Illuminate\Support\Facades\Auth;
use Morilog\Jalali\Jalalian;

trait ManagesBookingStayExtension
{
    public string $extendCheckOutJalali = '';

    protected function bootStayExtension(Booking $booking): void
    {
        $this->extendCheckOutJalali = Jalalian::fromCarbon($booking->check_out)->format('Y/m/d');
    }

    public function canExtendStayDates(): bool
    {
        $booking = $this->resolveStayExtensionBooking();

        if (!$booking?->canExtendStay(Auth::user())) {
            return false;
        }

        $user = Auth::user();

        if ($user?->isAdmin()) {
            return true;
        }

        return (bool) $user?->hostCan($this->stayExtensionPermissionPage(), 'edit');
    }

    public function extendStayCheckout(): void
    {
        if (method_exists($this, 'runDirectPriceChange')) {
            $this->runDirectPriceChange('extendStayCheckout', []);

            return;
        }

        abort_unless($this->canExtendStayDates(), 403, 'امکان تمدید این رزرو وجود ندارد.');

        $booking = $this->resolveStayExtensionBooking();

        $gregorian = JalaliDateTimeInput::toGregorianDate($this->extendCheckOutJalali);

        if (!$gregorian) {
            $this->addError('extendCheckOutJalali', 'تاریخ پایان معتبر نیست. فرمت: ۱۴۰۴/۰۱/۱۵');

            return;
        }

        try {
            $updated = app(BookingStayExtensionService::class)->extendCheckout($booking, $gregorian);
            $this->afterStayExtension($updated);
            $this->extendCheckOutJalali = Jalalian::fromCarbon($updated->check_out)->format('Y/m/d');
            $this->resetErrorBag('extendCheckOutJalali');
            $this->preserveBootstrapModalOpen();
        $this->dispatch('toast', type: 'success', message: 'تاریخ پایان رزرو با موفقیت به‌روز شد.');
        } catch (\RuntimeException $exception) {
            $this->addError('extendCheckOutJalali', $exception->getMessage());
        }
    }

    public function addStayExtensionNights(int $nights): void
    {
        if ($nights === 0) {
            return;
        }

        $booking = $this->resolveStayExtensionBooking();

        if (!$booking) {
            return;
        }

        $baseDate = JalaliDateTimeInput::toGregorianDate($this->extendCheckOutJalali)
            ?? $booking->check_out->toDateString();

        $newCheckOut = \Carbon\Carbon::parse($baseDate)->addDays($nights)->format('Y-m-d');
        $this->extendCheckOutJalali = Jalalian::fromCarbon(
            \Carbon\Carbon::parse($newCheckOut)
        )->format('Y/m/d');
    }

    protected function stayExtensionPermissionPage(): string
    {
        return 'bookings.dates';
    }

    abstract protected function resolveStayExtensionBooking(): ?Booking;

    protected function afterStayExtension(Booking $booking): void
    {
        // Override in consuming component when needed.
    }

    protected function stayExtensionModalId(): ?string
    {
        $booking = $this->resolveStayExtensionBooking();

        return $booking ? 'bd-modal-booking-' . $booking->id : null;
    }

    protected function preserveBootstrapModalOpen(?string $modalId = null): void
    {
        $modalId = trim((string) ($modalId ?? $this->stayExtensionModalId() ?? ''));

        if ($modalId === '') {
            $this->js('window.bnbPreserveBootstrapModalOpen?.()');

            return;
        }

        $this->js("window.bnbPreserveBootstrapModalOpen?.('{$modalId}')");
    }

    protected function releaseBootstrapModalLock(?string $modalId = null): void
    {
        $modalId = trim((string) ($modalId ?? $this->stayExtensionModalId() ?? ''));

        if ($modalId === '') {
            $this->js(<<<'JS'
                (() => {
                    const cleanup = () => {
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('padding-right');
                    };
                    cleanup();
                    setTimeout(cleanup, 200);
                })();
            JS);

            return;
        }

        $this->js(<<<JS
            (() => {
                const modalEl = document.getElementById('{$modalId}');
                if (modalEl && window.bootstrap?.Modal) {
                    const instance = bootstrap.Modal.getInstance(modalEl)
                        ?? bootstrap.Modal.getOrCreateInstance(modalEl);
                    instance.hide();
                }
                const cleanup = () => {
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                    if (modalEl) {
                        modalEl.classList.remove('show');
                        modalEl.setAttribute('aria-hidden', 'true');
                        modalEl.style.removeProperty('display');
                    }
                };
                cleanup();
                setTimeout(cleanup, 200);
                setTimeout(cleanup, 500);
            })();
        JS);
    }
}
