<?php

namespace App\Livewire\Concerns;

use App\Services\ProgramDocumentService;

trait ManagesPendingPaymentDocuments
{
    /** @var array<int, mixed> */
    public array $pendingPaymentDocuments = [];

    public function clearPendingPaymentDocuments(): void
    {
        $this->pendingPaymentDocuments = [];
    }

  /** @return array<int, string> */
    public function pendingPaymentDocumentRules(): array
    {
        return ProgramDocumentService::fileRules(nullable: true);
    }
}
