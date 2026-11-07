<?php
declare(strict_types=1);
namespace App\Modules\Inventory\Application;

final class CostStateReconstructor
{
    /** Reconstruct before each movement; accepted pre-Return history retains its original rules. */
    public function beforeStates(array $movements): array
    {
        return $this->reconstruct($movements)["before"];
    }
    public function afterStates(array $movements): array
    {
        return $this->reconstruct($movements)["after"];
    }
    private function reconstruct(array $movements): array
    {
        $state = "UNESTABLISHED";
        $before = [];
        $after = [];
        $returnEra = false;
        foreach ($movements as $m) {
            $id = (int) $m["id"];
            $before[$id] = $state;
            if (($m["movement_type"] ?? "") === "CUSTOMER_RETURN") {
                $returnEra = true;
                $original =
                    $before[(int) ($m["original_sale_movement_id"] ?? 0)] ??
                    "UNESTABLISHED";
                if (
                    $original === "UNESTABLISHED" ||
                    ($state === "UNESTABLISHED" &&
                        $this->positive(
                            (string) ($m["quantity_before"] ?? "0.0000"),
                        ))
                ) {
                    $state = "UNESTABLISHED";
                } else {
                    $state = $this->positive((string) $m["quantity_after"])
                        ? "VALID"
                        : ($state === "UNESTABLISHED"
                            ? "UNESTABLISHED"
                            : "RETAINED");
                }
            } elseif (
                $m["source_kind"] === "REVERSAL" &&
                isset($before[(int) $m["reversal_of_movement_id"]]) &&
                in_array(
                    $m["original_source_kind"] ?? "",
                    ["INTAKE_ITEM", "SALE_ITEM"],
                    true,
                )
            ) {
                $state = $before[(int) $m["reversal_of_movement_id"]];
            } elseif ($m["source_kind"] === "INTAKE_ITEM") {
                if (
                    !(
                        $returnEra &&
                        $state === "UNESTABLISHED" &&
                        $this->positive(
                            (string) ($m["quantity_before"] ?? "0.0000"),
                        )
                    )
                ) {
                    $state = $this->positive((string) $m["quantity_after"])
                        ? "VALID"
                        : ($state === "UNESTABLISHED"
                            ? "UNESTABLISHED"
                            : "RETAINED");
                }
            } elseif (
                $state === "VALID" &&
                !$this->positive((string) $m["quantity_after"])
            ) {
                $state = "RETAINED";
            }
            $after[$id] = $state;
        }
        return ["before" => $before, "after" => $after];
    }
    private function positive(string $value): bool
    {
        return !str_starts_with($value, "-") &&
            !preg_match('/^0(?:\.0+)?$/D', $value);
    }
}
