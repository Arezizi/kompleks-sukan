<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\RosterInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Imports the student roster from CSV content.
 *
 * Expected header row: matric_no, name, email, programme, phone.
 * New matric numbers create pending accounts that receive an emailed
 * activation link; known matric numbers get their profile refreshed.
 */
class RosterImporter
{
    /**
     * Maximum number of data rows accepted per file.
     */
    public const MAX_ROWS = 500;

    /**
     * Import the given raw CSV text and return a validation report.
     *
     * @throws ValidationException when the file shape itself is unusable.
     */
    public function import(string $csv): RosterImportReport
    {
        $rows = $this->parse($csv);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('The file has no data rows.'),
            ]);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('The file exceeds the maximum of :n rows.', ['n' => self::MAX_ROWS]),
            ]);
        }

        $created = [];
        $updated = [];
        $failedRows = [];
        $seenMatrics = [];
        $seenEmails = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$failedRows, &$seenMatrics, &$seenEmails) {
            foreach ($rows as $index => $row) {
                [$matricNo, $messages, $wasCreated] = $this->persistRow($row, $seenMatrics, $seenEmails);

                if ($messages !== []) {
                    $failedRows[] = [
                        'row' => $index + 2,
                        'matric_no' => $matricNo,
                        'messages' => $messages,
                    ];

                    continue;
                }

                if ($wasCreated) {
                    $created[] = $matricNo;
                } else {
                    $updated[] = $matricNo;
                }
            }
        });

        return new RosterImportReport(created: $created, updated: $updated, failedRows: $failedRows);
    }

    /**
     * Parse CSV text into normalized associative rows.
     *
     * @return list<array<string, mixed>>
     */
    private function parse(string $csv): array
    {
        // Strip a UTF-8 BOM if present.
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? '';

        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];

        if ($lines === []) {
            return [];
        }

        $header = array_map(
            fn (?string $cell): string => Str::of((string) $cell)->lower()->trim()->replace(' ', '_')->toString(),
            str_getcsv(array_shift($lines)),
        );

        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line);
            $row = [];

            foreach ($header as $position => $key) {
                $row[$key] = isset($cells[$position]) ? trim($cells[$position]) : null;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Validate and persist a single roster row.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seenMatrics
     * @param  array<string, true>  $seenEmails
     * @return array{0: string|null, 1: list<string>, 2: bool}
     */
    private function persistRow(array $row, array &$seenMatrics, array &$seenEmails): array
    {
        $data = [
            'matric_no' => $row['matric_no'] ?? null,
            'name' => $row['name'] ?? null,
            'email' => isset($row['email']) ? Str::lower($row['email']) : null,
            'programme' => $row['programme'] ?? null,
            'phone' => $row['phone'] ?? null,
        ];

        $existing = $data['matric_no'] !== null && $data['matric_no'] !== ''
            ? User::query()->whereRaw('lower(matric_no) = ?', [Str::lower((string) $data['matric_no'])])->first()
            : null;

        $validator = Validator::make($data, [
            'matric_no' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'programme' => ['nullable', 'string', 'max:128'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $validator->after(function ($validator) use ($data, $seenMatrics, $seenEmails, $existing): void {
            $matricKey = Str::lower((string) $data['matric_no']);
            $emailKey = Str::lower((string) $data['email']);

            if (isset($seenMatrics[$matricKey])) {
                $validator->errors()->add('matric_no', __('This matric number appears twice in the file.'));
            }

            if ($data['email'] !== null && isset($seenEmails[$emailKey])) {
                $validator->errors()->add('email', __('This email appears twice in the file.'));
            }

            if ($data['email'] !== null) {
                $query = User::query()->whereRaw('lower(email) = ?', [Str::lower($data['email'])]);

                if ($existing !== null) {
                    $query->whereKeyNot($existing->getKey());
                }

                if ((clone $query)->exists()) {
                    $validator->errors()->add('email', __('This email is already used by another account.'));
                }
            }
        });

        if ($validator->fails()) {
            return [$data['matric_no'], array_values($validator->errors()->all()), false];
        }

        /** @var string $matricNo */
        $matricNo = $data['matric_no'];
        $seenMatrics[Str::lower($matricNo)] = true;
        $seenEmails[Str::lower((string) $data['email'])] = true;

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'programme' => $data['programme'],
            'phone' => $data['phone'],
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return [$matricNo, [], false];
        }

        $user = new User;
        $user->forceFill([
            ...$attributes,
            'matric_no' => $matricNo,
            'role' => UserRole::Student,
            'password' => Str::password(32),
            'setup_token' => User::hashSetupToken($activationCode = Str::random(48)),
            'setup_completed_at' => null,
            // Ownership of the address is proven by the activation email itself.
            'email_verified_at' => now(),
        ])->save();

        Mail::to($user->email)->send(new RosterInvitation($user, $activationCode));

        return [$matricNo, [], true];
    }
}
