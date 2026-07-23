<?php

namespace Tests\Unit;

use App\Services\RoomBoardLayoutService;
use PHPUnit\Framework\TestCase;

class RoomBoardLayoutServiceTest extends TestCase
{
    public function test_apply_row_sort_move_reorders_rows_and_labels(): void
    {
        $service = new RoomBoardLayoutService();

        $layout = [
            'cols'       => 6,
            'rows'       => [[1, 2], [3, 4], [5]],
            'row_labels' => ['طبقه اول', 'طبقه دوم', 'همکف'],
        ];

        $result = $service->applyRowSortMove($layout, 0, 2);

        $this->assertSame([[3, 4], [5], [1, 2]], $result['rows']);
        $this->assertSame(['طبقه دوم', 'همکف', 'طبقه اول'], $result['row_labels']);
    }

    public function test_apply_row_sort_move_is_noop_for_invalid_index(): void
    {
        $service = new RoomBoardLayoutService();

        $layout = [
            'cols'       => 6,
            'rows'       => [[1, 2]],
            'row_labels' => ['ردیف ۱'],
        ];

        $result = $service->applyRowSortMove($layout, 5, 0);

        $this->assertSame($layout, $result);
    }

    public function test_apply_sort_move_moves_room_between_rows(): void
    {
        $service = new RoomBoardLayoutService();

        $layout = [
            'cols'       => 6,
            'rows'       => [[1, 2], [3, 4]],
            'row_labels' => ['الف', 'ب'],
        ];

        $result = $service->applySortMove($layout, 2, 0, 1);

        $this->assertSame([[1], [2, 3, 4]], $result['rows']);
        $this->assertSame(['الف', 'ب'], $result['row_labels']);
    }

    public function test_build_editable_layout_merges_new_rooms_into_saved_layout(): void
    {
        $service = new RoomBoardLayoutService();

        $rooms = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 99],
        ];

        $saved = [
            'cols'       => 4,
            'rows'       => [[1, 2], [3]],
            'row_labels' => ['طبقه ۱', 'طبقه ۲'],
        ];

        $result = $service->buildEditableLayout($rooms, $saved);

        $this->assertSame(4, $result['cols']);
        $this->assertSame([[1, 2], [3, 99]], $result['rows']);
        $this->assertSame(['طبقه ۱', 'طبقه ۲'], $result['row_labels']);
    }

    public function test_build_editable_layout_drops_removed_room_ids(): void
    {
        $service = new RoomBoardLayoutService();

        $rooms = [
            ['id' => 1],
            ['id' => 2],
        ];

        $saved = [
            'cols'       => 4,
            'rows'       => [[1, 2, 999]],
            'row_labels' => ['طبقه ۱'],
        ];

        $result = $service->buildEditableLayout($rooms, $saved);

        $this->assertSame([[1, 2]], $result['rows']);
    }
}
