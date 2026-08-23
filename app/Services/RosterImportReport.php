<?php

namespace App\Services;

/**
 * Outcome summary of a roster CSV import.
 */
final class RosterImportReport
{
    /**
     * @param  list<string>  $created  Matric numbers of newly created pending accounts.
     * @param  list<string>  $updated  Matric numbers of existing accounts whose profile was refreshed.
     * @param  list<array{row: int, matric_no: string|null, messages: list<string>}>  $failedRows
     */
    public function __construct(
        public readonly array $created = [],
        public readonly array $updated = [],
        public readonly array $failedRows = [],
    ) {}

    /**
     * Total number of rows processed successfully.
     */
    public function importedCount(): int
    {
        return count($this->created) + count($this->updated);
    }

    /**
     * Whether every row passed validation.
     */
    public function hasFailures(): bool
    {
        return $this->failedRows !== [];
    }
}
