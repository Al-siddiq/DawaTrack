<?php

declare(strict_types=1);
namespace App\Controllers\Api;

use App\Modules\CustomerDebt\Application\DebtCorrectionService;
use App\Modules\CustomerDebt\Infrastructure\DebtPaymentQuery;
use Config\Services;

final class DebtCorrectionController extends CommercialController
{
    private function service(): DebtCorrectionService
    {
        return new DebtCorrectionService(db_connect(), $this->access(), Services::auditService(), Services::changeSequenceService(), Services::requestContext());
    }
    private function command(string $id, string $kind, bool $post)
    {
        return $this->execute(function ($context) use ($id, $kind, $post) {
            $result = $this->service()->execute($context, $id, $kind, $this->input(), $post);
            if ($post) $result = in_array($kind, ['reverse', 'correct'], true)
                ? (new DebtPaymentQuery(db_connect(), $this->access()))->show($context, $result['public_id'])
                : $this->service()->showAdjustment($context, $result['public_id']);
            return $this->ok($result);
        });
    }
    public function reversalPreview(string $id) { return $this->command($id, 'reverse', false); }
    public function reverse(string $id) { return $this->command($id, 'reverse', true); }
    public function correctionPreview(string $id) { return $this->command($id, 'correct', false); }
    public function correct(string $id) { return $this->command($id, 'correct', true); }
    public function adjustmentPreview(string $id) { return $this->command($id, 'adjust', false); }
    public function adjust(string $id) { return $this->command($id, 'adjust', true); }
    public function adjustmentReversalPreview(string $id) { return $this->command($id, 'adjust-reverse', false); }
    public function adjustmentReverse(string $id) { return $this->command($id, 'adjust-reverse', true); }
    public function show(string $id) { return $this->execute(fn($c) => $this->ok($this->service()->showAdjustment($c, $id))); }
    public function history(string $id) { return $this->execute(fn($c) => $this->ok($this->service()->adjustmentHistory($c, $id, $this->request->getGet()))); }
}
