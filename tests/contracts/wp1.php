<?php
// Focused executable contract checks; no framework boot or production database.
declare(strict_types=1);
require __DIR__ . "/../../app/Exceptions/ApiException.php";
require __DIR__ .
    "/../../app/Modules/Reconciliation/Application/CashAmount.php";
require __DIR__ .
    "/../../app/Modules/Audit/Application/AuditMetadataPolicy.php";
use App\Modules\Reconciliation\Application\CashAmount;
use App\Modules\Audit\Application\AuditMetadataPolicy;
use App\Exceptions\ApiException;

$checks = 0;
foreach (
    [
        "0" => "0.00",
        "0.5" => "0.50",
        "12500.50" => "12500.50",
        " 12.3 " => "12.30",
        "99999999999999999.99" => "99999999999999999.99",
    ]
    as $input => $expected
) {
    if (CashAmount::parse((string) $input) !== $expected) {
        throw new RuntimeException("Cash amount normalization failed.");
    }
    $checks++;
}
foreach (
    [
        "-1",
        "1.001",
        "1e4",
        "+1",
        "1,000",
        "01",
        "NaN",
        "",
        "999999999999999999.99",
        1.01,
        [],
        null,
        "1\n2",
    ]
    as $input
) {
    try {
        CashAmount::parse($input);
    } catch (ApiException) {
        $checks++;
        continue;
    }
    throw new RuntimeException("Invalid or inexact Cash input was accepted.");
}
$metadata = (new AuditMetadataPolicy())->sanitize([
    "access_invalidated" => true,
    "force_reset_requested" => true,
]);
if ($metadata["access_invalidated"] !== true) {
    throw new RuntimeException("Credential reset evidence rejected.");
}
$checks++;
try {
    (new AuditMetadataPolicy())->sanitize(["sessions_invalidated" => true]);
    throw new RuntimeException("Sensitive metadata guard weakened.");
} catch (ApiException) {
    $checks++;
}
echo "PASS: {$checks} Cash/Audit contract cases.\n";
