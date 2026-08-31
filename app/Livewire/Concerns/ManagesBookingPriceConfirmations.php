<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Models\BookingPaymentRecord;
use App\Services\BookingPaymentCaptureService;
use App\Services\BookingPriceChangePreviewService;
use App\Services\BookingRoomModificationService;
use App\Services\ManualBookingService;
use App\Services\PlatformCommissionService;
use App\Support\JalaliDateTimeInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

trait ManagesBookingPriceConfirmations
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function previewBookingPriceChange(string $action, array $params = []): array
    {
        $this->skipRender();

        $label = $this->priceChangeActionLabel($action);
        $description = $this->priceChangeActionDescription($action, $params);
        $previewService = app(BookingPriceChangePreviewService::class);

        if (!$previewService->bookingSupportsAutoRepricing($this->booking)) {
            return [
                'error'           => false,
                'affects_price'   => false,
                'current_total'   => (int) $this->booking->total_price,
                'projected_total' => (int) $this->booking->total_price,
                'auto_delta'      => 0,
                'action'          => $action,
                'action_label'    => $label,
                'description'     => $description,
                'info_message'    => $this->priceChangeInfoMessage($action),
            ];
        }

        try {
            $preview = $this->previewPriceChangingMutation($action, $params);
        } catch (ValidationException $exception) {
            return [
                'error'        => true,
                'message'      => collect($exception->errors())->flatten()->first() ?? 'ورودی معتبر نیست.',
                'action'       => $action,
                'action_label' => $label,
            ];
        } catch (\Throwable $exception) {
            return [
                'error'        => true,
                'message'      => $exception->getMessage(),
                'action'       => $action,
                'action_label' => $label,
            ];
        }

        return $this->enrichPriceChangePreview([
            'error'           => false,
            'affects_price'   => (bool) ($preview['affects_price'] ?? false),
            'current_total'   => (int) ($preview['current_total'] ?? 0),
            'projected_total' => (int) ($preview['projected_total'] ?? 0),
            'auto_delta'      => (int) ($preview['auto_delta'] ?? 0),
            'action'          => $action,
            'action_label'    => $label,
            'description'     => $description,
        ], $action, $params);
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function enrichPriceChangePreviewWithPaymentMeta(array $preview): array
    {
        $captureService = app(BookingPaymentCaptureService::class);
        $provinceId = $this->booking->accommodation?->resolvedProvince()?->id;

        $preview['payment_method'] = $this->booking->payment_method;
        $preview['skip_payment_capture'] = $this->booking->billsAsRegularGuest();
        $preview['pos_terminals'] = $captureService->terminalsForProvince($provinceId);
        $preview['default_payment_date'] = JalaliDateTimeInput::nowJalaliDate();
        $preview['default_payment_time'] = JalaliDateTimeInput::nowTime();
        $preview['calculated_total'] = (int) ($preview['current_total'] ?? 0);

        return $preview;
    }

    /**
     * @param  array<string, mixed>  $preview
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function enrichPriceChangePreview(array $preview, string $action, array $params): array
    {
        $listSubtotal = $this->priceChangeListSubtotal($action, $params);

        if ($listSubtotal === null || $listSubtotal <= 0) {
            return $this->enrichPriceChangePreviewWithPaymentMeta($preview);
        }

        $autoDelta = (int) ($preview['auto_delta'] ?? 0);
        $policyDiscount = $listSubtotal - $autoDelta;

        $preview['list_subtotal'] = $listSubtotal;

        if ($policyDiscount <= 0) {
            return $this->enrichPriceChangePreviewWithPaymentMeta($preview);
        }

        $preview['policy_discount'] = $policyDiscount;
        $preview['delta_explanation'] = 'مبلغ پیشنهادی پس از تخفیف/سهمیه ایثارگری خدمت است '
            . '(قیمت واحد ' . number_format($listSubtotal) . ' ریال − تخفیف ' . number_format($policyDiscount) . ' ریال).';

        return $this->enrichPriceChangePreviewWithPaymentMeta($preview);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function priceChangeListSubtotal(string $action, array $params): ?int
    {
        return match ($action) {
            'addServiceLine' => max(0, (int) $this->newServicePrice * (int) $this->newServiceQty),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function executeConfirmedPriceChange(string $action, int $confirmedDelta, array $params = []): void
    {
        $beforeTotal = (int) $this->booking->total_price;
        $naturalBefore = $this->captureNaturalBookingTotal($this->booking);

        try {
            $this->runPriceChangingMutation($action, $params, preview: false);
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->handlePriceChangeMutationError($action, $exception);

            return;
        }

        $this->booking->refresh();
        $naturalAfter = (int) $this->booking->total_price;
        $manualPortion = $confirmedDelta - ($naturalAfter - $naturalBefore);

        $this->finalizeBookingPriceChange($this->booking, $beforeTotal, $confirmedDelta);
        $this->persistServiceManualPriceAdjustment($action, $params, $manualPortion);
        $this->recordPaymentCaptureAfterPriceChange($action, $confirmedDelta, array_merge($params, [
            'payment_capture_uploads' => $this->pendingPaymentDocuments,
        ]));
        $this->clearPendingPaymentDocuments();
        $this->afterPriceChangingMutation($action);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function recordPaymentCaptureAfterPriceChange(string $action, int $confirmedDelta, array $params): void
    {
        $capture = $params['payment_capture'] ?? null;
        $uploads = $params['payment_capture_uploads'] ?? [];
        $reason = $params['price_adjustment_reason'] ?? null;

        if (is_array($capture)) {
            app(BookingPaymentCaptureService::class)->record(
                $this->booking->fresh(),
                $confirmedDelta,
                $capture,
                BookingPaymentRecord::CONTEXT_PRICE_CHANGE,
                $action,
                Auth::user(),
                is_array($uploads) ? $uploads : [],
            );

            return;
        }

        app(BookingPaymentCaptureService::class)->recordOptionalAdjustmentNote(
            $this->booking->fresh(),
            $confirmedDelta,
            is_string($reason) ? $reason : null,
            BookingPaymentRecord::CONTEXT_PRICE_CHANGE,
            $action,
            Auth::user(),
        );
    }

    /**
     * @return array<int, array{id:int, label:string}>
     */
    public function listPosTerminalsForPaymentCapture(): array
    {
        $this->skipRender();

        return app(BookingPaymentCaptureService::class)
            ->terminalsForProvince($this->booking->accommodation?->resolvedProvince()?->id);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function runDirectPriceChange(string $action, array $params = []): void
    {
        $previewService = app(BookingPriceChangePreviewService::class);
        $delta = 0;

        if ($previewService->bookingSupportsAutoRepricing($this->booking)) {
            try {
                $preview = $this->previewPriceChangingMutation($action, $params);
                $delta = (int) ($preview['auto_delta'] ?? 0);
            } catch (ValidationException|HttpExceptionInterface $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $this->handlePriceChangeMutationError($action, $exception);

                return;
            }
        }

        $this->executeConfirmedPriceChange($action, $delta, $params);
    }

    /**
     * Simulate the mutation on a cloned booking so the Livewire model
     * is not left looking deleted/changed after the preview transaction rolls back.
     *
     * @param  array<string, mixed>  $params
     * @return array{affects_price: bool, current_total: int, projected_total: int, auto_delta: int}
     */
    protected function previewPriceChangingMutation(string $action, array $params): array
    {
        return app(BookingPriceChangePreviewService::class)->preview(
            $this->booking,
            function (Booking $previewBooking) use ($action, $params): void {
                $originalBooking = $this->booking;
                $this->booking = $previewBooking;

                try {
                    $this->runPriceChangingMutation($action, $params, preview: true);
                } finally {
                    $this->booking = $originalBooking;
                }
            },
        );
    }

    public function commitAddRoomLine(): void
    {
        $this->runDirectPriceChange('commitAddRoomLine', []);
    }

    public function addGuestToSoldRoom(int $bookingRoomId): void
    {
        $this->runDirectPriceChange('addGuestToSoldRoom', ['bookingRoomId' => $bookingRoomId]);
    }

    public function revertServiceQuotaUi(int $serviceId): void
    {
        $service = $this->booking->services()->find($serviceId);

        if (!$service || !isset($this->editableServices[$serviceId])) {
            return;
        }

        $this->editableServices[$serviceId]['excluded_from_veteran_quota'] = (bool) $service->excluded_from_veteran_quota;
        $this->editableServices[$serviceId]['manual_discount_percentage'] = $service->manual_discount_percentage !== null
            ? (string) $service->manual_discount_percentage
            : '';
        $this->editableServices[$serviceId]['manual_discount_reason'] = $service->manual_discount_reason ?? '';
    }

    public function requestServiceQuotaPriceConfirm(int $serviceId, ?string $changedField = null): void
    {
        $row = $this->editableServices[$serviceId] ?? null;

        if (!$row) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);

        if (!$service || !$this->serviceBelongsToScope($service)) {
            return;
        }

        if (!$this->validateServiceManualDiscountRow($serviceId, $row)) {
            $this->revertServiceQuotaUi($serviceId);

            return;
        }

        $this->dispatch(
            'bnb-price-change-request',
            action: 'applyServiceQuotaSettings',
            params: ['serviceId' => $serviceId, 'changedField' => $changedField],
            componentId: $this->getId(),
        );
    }

    protected function finalizeBookingPriceChange(Booking $booking, int $beforeTotal, ?int $confirmedDelta): Booking
    {
        $booking->refresh();

        if ($confirmedDelta === null || !app(BookingPriceChangePreviewService::class)->bookingSupportsAutoRepricing($booking)) {
            return $booking;
        }

        $targetTotal = max(0, $beforeTotal + $confirmedDelta);

        if ((int) $booking->total_price === $targetTotal) {
            return $booking;
        }

        $booking->update(['total_price' => $targetTotal]);
        $booking->refresh();

        app(PlatformCommissionService::class)->syncBookingCommissions($booking, Auth::user());

        return $booking;
    }

    protected function captureNaturalBookingTotal(Booking $booking): int
    {
        $previewService = app(BookingPriceChangePreviewService::class);

        if (!$previewService->bookingSupportsAutoRepricing($booking)) {
            return (int) $booking->total_price;
        }

        DB::beginTransaction();

        try {
            app(ManualBookingService::class)->recalculateTotals($booking->fresh());

            return (int) $booking->fresh()->total_price;
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function persistServiceManualPriceAdjustment(string $action, array $params, int $manualPortion): void
    {
        if ($manualPortion === 0) {
            return;
        }

        $serviceId = match ($action) {
            'addServiceLine' => $this->booking->services()->orderByDesc('id')->value('id'),
            'applyServiceQuantity', 'applyServiceLineEdits', 'applyServiceQuotaSettings' => (int) ($params['serviceId'] ?? 0),
            default => null,
        };

        if (!$serviceId) {
            return;
        }

        $service = $this->booking->services()->find($serviceId);

        if (!$service) {
            return;
        }

        $nextAdjustment = $action === 'addServiceLine'
            ? $manualPortion
            : (int) $service->manual_price_adjustment + $manualPortion;

        $service->update(['manual_price_adjustment' => $nextAdjustment]);
        $this->booking->refresh();
    }

    protected function shouldCapturePriceBeforeChange(): bool
    {
        return app(BookingPriceChangePreviewService::class)->bookingSupportsAutoRepricing($this->booking);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function runPriceChangingMutation(string $action, array $params, bool $preview = false): void
    {
        match ($action) {
            'commitAddRoomLine' => $this->performCommitAddRoomLine($preview),
            'addGuestToSoldRoom' => $this->performAddGuestToSoldRoom((int) ($params['bookingRoomId'] ?? 0), $preview),
            'extendStayCheckout' => $this->performExtendStayCheckout($preview),
            'addServiceLine' => $this->performAddServiceLine($preview),
            'applyServiceQuantity' => $this->performApplyServiceQuantity((int) ($params['serviceId'] ?? 0), $preview),
            'removeServiceLine' => $this->performRemoveServiceLine((int) ($params['serviceId'] ?? 0), $preview),
            'applyServiceLineEdits' => $this->performApplyServiceLineEdits((int) ($params['serviceId'] ?? 0), $preview),
            'saveServiceEdits' => $this->performSaveServiceEdits($preview),
            'applyServiceQuotaSettings' => $this->performApplyServiceQuotaSettings(
                (int) ($params['serviceId'] ?? 0),
                $params['changedField'] ?? null,
                $preview,
            ),
            default => throw new \InvalidArgumentException('عملیات قیمت‌گذاری ناشناخته: ' . $action),
        };
    }

    protected function afterPriceChangingMutation(string $action): void
    {
        match ($action) {
            'commitAddRoomLine' => $this->afterCommitAddRoomLine(),
            'addGuestToSoldRoom' => $this->afterAddGuestToSoldRoom(),
            'extendStayCheckout' => $this->afterExtendStayCheckout(),
            'addServiceLine' => $this->afterAddServiceLine(),
            'applyServiceQuantity' => $this->afterApplyServiceQuantity(),
            'removeServiceLine' => $this->afterRemoveServiceLine(),
            'applyServiceLineEdits' => $this->afterApplyServiceLineEdits(),
            'saveServiceEdits' => $this->afterSaveServiceEdits(),
            'applyServiceQuotaSettings' => $this->afterApplyServiceQuotaSettings(),
            default => null,
        };
    }

    protected function handlePriceChangeMutationError(string $action, \Throwable $exception): void
    {
        match ($action) {
            'commitAddRoomLine' => $this->addError('addRoomRoomTypeId', $exception->getMessage()),
            'addGuestToSoldRoom' => $this->dispatchBookingToast($exception->getMessage(), 'error'),
            'extendStayCheckout' => $this->addError('extendCheckOutJalali', $exception->getMessage()),
            'applyServiceQuotaSettings' => $this->dispatchBookingToast($exception->getMessage(), 'error'),
            default => $this->dispatchBookingToast($exception->getMessage(), 'error'),
        };
    }

    protected function priceChangeActionLabel(string $action): string
    {
        return match ($action) {
            'commitAddRoomLine' => 'افزودن اتاق',
            'addGuestToSoldRoom' => 'افزودن مهمان',
            'extendStayCheckout' => $this->booking->isMedicalAccommodation() ? 'تغییر تاریخ پایان' : 'تمدید اقامت',
            'addServiceLine' => 'افزودن خدمت',
            'applyServiceQuantity' => 'تغییر تعداد خدمت',
            'removeServiceLine' => 'حذف خدمت',
            'applyServiceLineEdits' => 'ویرایش خدمت',
            'saveServiceEdits' => 'ذخیره خدمات',
            'applyServiceQuotaSettings' => 'تغییر سهمیه خدمت',
            default => 'تغییر رزرو',
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function priceChangeActionDescription(string $action, array $params): string
    {
        return match ($action) {
            'commitAddRoomLine' => 'یک خط اتاق جدید به این رزرو اضافه می‌شود.',
            'addGuestToSoldRoom' => 'ظرفیت اتاق فروخته‌شده افزایش می‌یابد.',
            'extendStayCheckout' => $this->booking->isMedicalAccommodation()
                ? 'تاریخ پایان اقامت بدون جریمه کنسلی به‌روز می‌شود و بدهی کارفرما بر اساس تعرفه شبانه محاسبه می‌گردد.'
                : 'تاریخ پایان اقامت تمدید می‌شود.',
            'addServiceLine' => 'خدمت جدید به رزرو افزوده می‌شود.',
            'applyServiceQuantity' => 'تعداد یکی از خدمات تغییر می‌کند.',
            'removeServiceLine' => 'یک خدمت از رزرو حذف می‌شود.',
            'applyServiceLineEdits' => 'قیمت یا مشخصات خدمت به‌روز می‌شود.',
            'saveServiceEdits' => 'همه تغییرات خدمات ذخیره می‌شود.',
            'applyServiceQuotaSettings' => 'تنظیمات سهمیه/تخفیف خدمت اعمال می‌شود.',
            default => 'این عملیات ممکن است مبلغ رزرو را تغییر دهد.',
        };
    }

    protected function priceChangeInfoMessage(string $action): string
    {
        if ($this->booking->isProgram()) {
            return 'برنامه/اردو: مبالغ مالی خودکار محاسبه نمی‌شوند. فقط ساختار رزرو تغییر می‌کند.';
        }

        return 'این عملیات مبلغ خودکار محاسبه نمی‌شود.';
    }

    protected function performCommitAddRoomLine(bool $preview): void
    {
        $this->assertCanCommitAddRoomLine();

        $this->booking = app(BookingRoomModificationService::class)->addRoomLine($this->booking, [
            'room_type_id'     => (int) $this->addRoomRoomTypeId,
            'room_rate_id'     => $this->addRoomRoomRateId !== '' ? (int) $this->addRoomRoomRateId : null,
            'room_id'          => $this->addRoomPhysicalRoomId !== null ? (int) $this->addRoomPhysicalRoomId : null,
            'adults'           => $this->addRoomAdults,
            'children_under_6' => $this->addRoomChildrenUnder6,
            'guests'           => $this->addRoomAdults + $this->addRoomChildrenUnder6,
            'extra_guests'     => $this->addRoomExtraGuests,
            'bill_full_rooms'  => $this->addRoomBillFullRooms,
        ], Auth::user());
    }

    protected function performAddGuestToSoldRoom(int $bookingRoomId, bool $preview): void
    {
        abort_if($bookingRoomId <= 0, 422);

        $this->booking = app(BookingRoomModificationService::class)->addGuestToRoom(
            $this->booking,
            $bookingRoomId,
            Auth::user(),
        );
    }

    protected function performExtendStayCheckout(bool $preview): void
    {
        abort_unless($this->canExtendStayDates(), 403);

        $gregorian = \App\Support\JalaliDateTimeInput::toGregorianDate($this->extendCheckOutJalali);

        abort_unless($gregorian, 422, 'تاریخ پایان معتبر نیست.');

        $this->booking = app(\App\Services\BookingStayExtensionService::class)->extendCheckout($this->booking, $gregorian);
    }

    protected function performAddServiceLine(bool $preview): void
    {
        if (!$this->validateAddServiceLineForm()) {
            throw new \RuntimeException('اطلاعات خدمت جدید معتبر نیست.');
        }

        $this->createServiceLineRecord();
    }

    protected function performApplyServiceQuantity(int $serviceId, bool $preview): void
    {
        $this->updateServiceQuantityRecord($serviceId);
    }

    protected function performRemoveServiceLine(int $serviceId, bool $preview): void
    {
        $this->deleteServiceLineRecord($serviceId);
    }

    protected function performApplyServiceLineEdits(int $serviceId, bool $preview): void
    {
        $this->updateServiceLineRecord($serviceId);
    }

    protected function performSaveServiceEdits(bool $preview): void
    {
        $this->persistAllServiceLineEdits();
    }

    protected function performApplyServiceQuotaSettings(int $serviceId, ?string $changedField, bool $preview): void
    {
        $row = $this->editableServices[$serviceId] ?? null;

        abort_unless($row, 422);

        $this->persistServiceQuotaSettings($serviceId, $row, $changedField, dispatchEvents: false);
    }

    protected function afterCommitAddRoomLine(): void
    {
        $this->afterBookingRoomModification($this->booking);
        $this->resetAddRoomForm();
        $this->preserveBootstrapModalOpen('bd-modal-rooms-' . $this->booking->id);
        $this->dispatchBookingToast('اتاق جدید به رزرو اضافه شد.');
    }

    protected function afterAddGuestToSoldRoom(): void
    {
        $this->afterBookingRoomModification($this->booking);
        $this->preserveBootstrapModalOpen('bd-modal-guests-' . $this->booking->id);
        $this->dispatchBookingToast('مهمان جدید به اتاق اضافه شد و مبلغ رزرو به‌روز شد.');
    }

    protected function afterExtendStayCheckout(): void
    {
        $this->booking->refresh();
        $this->afterStayExtension($this->booking);
        $this->extendCheckOutJalali = \Morilog\Jalali\Jalalian::fromCarbon($this->booking->check_out)->format('Y/m/d');
        $this->resetErrorBag('extendCheckOutJalali');
        $this->preserveBootstrapModalOpen();
        $this->dispatchBookingToast('تاریخ پایان رزرو با موفقیت به‌روز شد.');
    }

    protected function afterAddServiceLine(): void
    {
        $name = $this->lastMutatedServiceName ?? 'خدمت';
        $this->loadEditableServices();
        $this->resetNewServiceForm();
        $this->dispatchBookingToast('خدمت «' . $name . '» اضافه شد.');
        $this->dispatch('booking-services-updated');
    }

    protected function afterApplyServiceQuantity(): void
    {
        $name = $this->lastMutatedServiceName ?? 'خدمت';
        $qty = $this->lastMutatedServiceQuantity;
        $this->loadEditableServices();
        $message = $qty !== null
            ? 'تعداد «' . $name . '» به ' . $qty . ' تغییر کرد.'
            : 'تعداد «' . $name . '» به‌روز شد.';
        $this->dispatchBookingToast($message);
        $this->dispatch('booking-services-updated');
    }

    protected function afterRemoveServiceLine(): void
    {
        $name = $this->lastMutatedServiceName ?? 'خدمت';
        $this->loadEditableServices();
        $this->dispatchBookingToast('خدمت «' . $name . '» حذف شد.');
        $this->dispatch('booking-services-updated');
    }

    protected function afterApplyServiceLineEdits(): void
    {
        $name = $this->lastMutatedServiceName ?? 'خدمت';
        $this->loadEditableServices();
        $this->dispatchBookingToast('تغییرات «' . $name . '» اعمال شد.');
        $this->dispatch('booking-services-updated');
    }

    protected function afterSaveServiceEdits(): void
    {
        $this->loadEditableServices();
        $this->dispatchBookingToast('همه خدمات رزرو به‌روز شد.');
        $this->dispatch('booking-services-updated');
    }

    protected function afterApplyServiceQuotaSettings(): void
    {
        $name = $this->lastMutatedServiceName ?? 'خدمت';
        $changedField = $this->lastServiceQuotaChangedField;
        $excluded = (bool) $this->lastServiceQuotaExcluded;
        $this->loadEditableServices();
        $this->dispatchBookingToast($this->quotaSettingsToastMessage($name, $changedField, $excluded));
        $this->dispatch('booking-services-updated');
    }

    protected function assertCanCommitAddRoomLine(): void
    {
        abort_unless($this->canModifyBookingRooms(), 403);

        $this->validate([
            'addRoomRoomTypeId'     => ['required', 'integer', 'exists:room_types,id'],
            'addRoomRoomRateId'     => ['nullable', 'integer', 'exists:room_rates,id'],
            'addRoomAdults'         => ['required', 'integer', 'min:1', 'max:20'],
            'addRoomChildrenUnder6' => ['required', 'integer', 'min:0', 'max:19'],
            'addRoomExtraGuests'    => ['required', 'integer', 'min:0', 'max:10'],
            'addRoomBillFullRooms'  => ['boolean'],
        ], [], [
            'addRoomRoomTypeId'     => 'نوع اتاق',
            'addRoomRoomRateId'     => 'تعرفه',
            'addRoomAdults'         => 'بزرگسال',
            'addRoomChildrenUnder6' => 'کودک زیر ۶ سال',
            'addRoomExtraGuests'    => 'کف‌خواب',
            'addRoomBillFullRooms'  => 'رزرو کامل اتاق',
        ]);

        if ($this->addRoomChildrenUnder6 >= ($this->addRoomAdults + $this->addRoomChildrenUnder6)) {
            throw new \RuntimeException('تعداد کودک باید کمتر از کل نفرات اتاق باشد.');
        }
    }
}
