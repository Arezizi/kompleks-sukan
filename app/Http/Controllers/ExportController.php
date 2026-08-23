<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Stream an operational report as CSV.
     */
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['loans', 'reservations', 'incidents'], true), 404);

        $filename = 'pms-'.$type.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($type): void {
            $out = fopen('php://output', 'w');

            if ($out !== false) {
                match ($type) {
                    'loans' => $this->loans($out),
                    'reservations' => $this->reservations($out),
                    default => $this->incidents($out),
                };

                fclose($out);
            }
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Write every loan with its items to the stream.
     *
     * @param  resource  $out
     */
    private function loans($out): void
    {
        fputcsv($out, ['id', 'student', 'matric_no', 'items', 'checked_out_at', 'due_at', 'returned_at', 'card_collected', 'card_returned']);

        Loan::query()->with(['user', 'items.equipmentType'])
            ->orderBy('id')
            ->chunk(200, function ($loans) use ($out): void {
                foreach ($loans as $loan) {
                    fputcsv($out, [
                        $loan->id,
                        $loan->user->name,
                        $loan->user->matric_no ?? '',
                        $loan->items->map(fn ($item) => $item->quantity.'x '.$item->equipmentType->name)->implode('; '),
                        optional($loan->checked_out_at)->toDateTimeString() ?? '',
                        optional($loan->due_at)->toDateTimeString() ?? '',
                        optional($loan->returned_at)?->toDateTimeString() ?? '',
                        $loan->card_collected ? 'yes' : 'no',
                        $loan->card_returned ? 'yes' : 'no',
                    ]);
                }
            });
    }

    /**
     * Write every reservation with its items to the stream.
     *
     * @param  resource  $out
     */
    private function reservations($out): void
    {
        fputcsv($out, ['id', 'student', 'matric_no', 'items', 'status', 'pickup_deadline', 'created_at']);

        Reservation::query()->with(['user', 'items.equipmentType'])
            ->orderBy('id')
            ->chunk(200, function ($reservations) use ($out): void {
                foreach ($reservations as $reservation) {
                    fputcsv($out, [
                        $reservation->id,
                        $reservation->user->name,
                        $reservation->user->matric_no ?? '',
                        $reservation->items->map(fn ($item) => $item->quantity.'x '.$item->equipmentType->name)->implode('; '),
                        $reservation->status->value,
                        optional($reservation->pickup_deadline)?->toDateTimeString() ?? '',
                        optional($reservation->created_at)?->toDateTimeString() ?? '',
                    ]);
                }
            });
    }

    /**
     * Write every incident report to the stream.
     *
     * @param  resource  $out
     */
    private function incidents($out): void
    {
        fputcsv($out, ['id', 'title', 'type', 'severity', 'facility', 'reporter', 'occurred_at', 'resolved_at']);

        Incident::query()->with(['reporter', 'facility'])
            ->orderBy('id')
            ->chunk(200, function ($incidents) use ($out): void {
                foreach ($incidents as $incident) {
                    fputcsv($out, [
                        $incident->id,
                        $incident->title,
                        $incident->type->value,
                        $incident->severity->value,
                        $incident->facility !== null ? $incident->facility->name : '',
                        $incident->reporter !== null ? $incident->reporter->name : '',
                        optional($incident->occurred_at)->toDateTimeString() ?? '',
                        optional($incident->resolved_at)?->toDateTimeString() ?? '',
                    ]);
                }
            });
    }
}
