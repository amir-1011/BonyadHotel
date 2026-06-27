<?php

namespace App\Services;

use App\Models\User;

class RoomBoardLayoutService
{
    public const DEFAULT_COLS = 6;

    public const MAX_ROW_LABEL_LENGTH = 60;

    /**
     * @return array{cols: int, rows: array<int, array<int>>, row_labels: array<int, string>}|null
     */
    public function getAccommodationLayout(User $user, int $accommodationId): ?array
    {
        $stored = $user->room_board_layout ?? [];
        $layouts = $stored['accommodations'] ?? $stored['groups'] ?? [];
        $key = (string) $accommodationId;
        $layout = $layouts[$key] ?? null;

        if (!$layout || empty($layout['rows'])) {
            return null;
        }

        $rows = array_values(array_map(
            fn ($row) => array_values(array_map('intval', $this->extractRoomIds($row))),
            $layout['rows'],
        ));

        $labels = $this->normalizeRowLabels($layout['row_labels'] ?? [], count($rows));

        return [
            'cols'       => max(1, min(12, (int) ($layout['cols'] ?? self::DEFAULT_COLS))),
            'rows'       => $rows,
            'row_labels' => $labels,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rooms
     * @return array{cols: int, rows: array<int, array{label: string, rooms: array<int, array<string, mixed>>}>}
     */
    public function organizeRooms(array $rooms, ?array $layout): array
    {
        $byId = [];
        foreach ($rooms as $room) {
            $byId[(int) $room['id']] = $room;
        }

        $allIds = array_keys($byId);
        $cols = max(1, min(12, (int) ($layout['cols'] ?? self::DEFAULT_COLS)));

        if ($layout === null || empty($layout['rows'])) {
            return $this->defaultOrganizedRows($rooms, $cols);
        }

        $labels = $this->normalizeRowLabels($layout['row_labels'] ?? [], count($layout['rows']));
        $used = [];
        $rows = [];

        foreach ($layout['rows'] as $rowIndex => $rowIds) {
            $row = [];
            foreach ($this->extractRoomIds($rowIds) as $id) {
                $id = (int) $id;
                if (!isset($byId[$id]) || isset($used[$id])) {
                    continue;
                }
                $row[] = $byId[$id];
                $used[$id] = true;
            }
            if ($row !== []) {
                $rows[] = [
                    'label' => $labels[$rowIndex] ?? '',
                    'rooms' => $row,
                ];
            }
        }

        $remaining = array_values(array_filter($allIds, fn ($id) => !isset($used[$id])));
        if ($remaining !== []) {
            $remainingRooms = array_map(fn ($id) => $byId[$id], $remaining);
            if ($rows === []) {
                $rows[] = ['label' => '', 'rooms' => $remainingRooms];
            } else {
                $last = count($rows) - 1;
                $rows[$last]['rooms'] = array_merge($rows[$last]['rooms'], $remainingRooms);
            }
        }

        if ($rows === []) {
            return $this->defaultOrganizedRows($rooms, $cols);
        }

        return ['cols' => $cols, 'rows' => $rows];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rooms
     * @return array{cols: int, rows: array<int, array{label: string, rooms: array<int, array<string, mixed>>}>}
     */
    private function defaultOrganizedRows(array $rooms, int $cols): array
    {
        if ($rooms === []) {
            return ['cols' => $cols, 'rows' => []];
        }

        $chunks = array_chunk($rooms, max(1, $cols));

        return [
            'cols' => $cols,
            'rows' => array_map(
                fn (array $chunk) => ['label' => '', 'rooms' => $chunk],
                $chunks,
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rooms
     * @return array{cols: int, rows: array<int, array<int>>, row_labels: array<int, string>}
     */
    public function buildEditableLayout(array $rooms, ?array $savedLayout): array
    {
        $roomIds = array_map(fn ($r) => (int) $r['id'], $rooms);

        if ($savedLayout !== null && !empty($savedLayout['rows'])) {
            return [
                'cols'       => max(1, min(12, (int) ($savedLayout['cols'] ?? self::DEFAULT_COLS))),
                'rows'       => $savedLayout['rows'],
                'row_labels' => $this->normalizeRowLabels($savedLayout['row_labels'] ?? [], count($savedLayout['rows'])),
            ];
        }

        return [
            'cols'       => self::DEFAULT_COLS,
            'rows'       => [$roomIds],
            'row_labels' => [''],
        ];
    }

    /**
     * @param  array{cols: int, rows: array<int, array<int>>, row_labels?: array<int, string>}  $layout
     */
    public function saveAccommodationLayout(User $user, int $accommodationId, array $layout): void
    {
        $rows = array_values(array_map(
            fn ($row) => array_values(array_map('intval', (array) $row)),
            $layout['rows'] ?? [],
        ));

        $stored = $user->room_board_layout ?? [];
        $stored['accommodations'] ??= [];
        $stored['accommodations'][(string) $accommodationId] = [
            'cols'       => max(1, min(12, (int) ($layout['cols'] ?? self::DEFAULT_COLS))),
            'rows'       => $rows,
            'row_labels' => $this->normalizeRowLabels($layout['row_labels'] ?? [], count($rows)),
        ];

        $user->room_board_layout = $stored;
        $user->save();
    }

    public function clearAccommodationLayout(User $user, int $accommodationId): void
    {
        $stored = $user->room_board_layout ?? [];
        unset($stored['accommodations'][(string) $accommodationId]);
        $user->room_board_layout = $stored;
        $user->save();
    }

    /**
     * @param  array{cols: int, rows: array<int, array<int>>, row_labels?: array<int, string>}  $layout
     * @return array{cols: int, rows: array<int, array<int>>, row_labels: array<int, string>}
     */
    public function applySortMove(array $layout, int $roomId, int $position, int $targetRow): array
    {
        $rows = $layout['rows'];
        $labels = $this->normalizeRowLabels($layout['row_labels'] ?? [], count($rows));

        foreach ($rows as $ri => $row) {
            $rows[$ri] = array_values(array_filter($row, fn ($id) => (int) $id !== $roomId));
        }

        if (!isset($rows[$targetRow])) {
            $rows[$targetRow] = [];
            $labels[$targetRow] = '';
        }

        $position = max(0, min($position, count($rows[$targetRow])));
        array_splice($rows[$targetRow], $position, 0, [$roomId]);

        $newRows = [];
        $newLabels = [];
        foreach ($rows as $i => $row) {
            if ($row !== []) {
                $newRows[] = $row;
                $newLabels[] = trim((string) ($labels[$i] ?? ''));
            }
        }

        if ($newRows === []) {
            $newRows = [[$roomId]];
            $newLabels = [''];
        }

        $layout['rows'] = $newRows;
        $layout['row_labels'] = $newLabels;

        return $layout;
    }

    public function sanitizeRowLabel(string $label): string
    {
        return mb_substr(trim($label), 0, self::MAX_ROW_LABEL_LENGTH);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRowLabels(array $labels, int $rowCount): array
    {
        $normalized = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $normalized[] = $this->sanitizeRowLabel((string) ($labels[$i] ?? ''));
        }

        return $normalized;
    }

    /**
     * @param  mixed  $row
     * @return array<int, int>
     */
    private function extractRoomIds(mixed $row): array
    {
        if (is_array($row) && isset($row['rooms']) && is_array($row['rooms'])) {
            $row = $row['rooms'];
        }

        return array_values(array_map('intval', (array) $row));
    }
}
