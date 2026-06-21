<style>
:root {
    --bnb-red:        #FF385C;
    --bnb-red-hover:  #E31C5F;
    --bnb-dark:       #222222;
    --bnb-gray:       #717171;
    --bnb-border:     #DDDDDD;
    --bnb-bg:         #FFFFFF;
    --bnb-bg-light:   #F7F7F7;
    --bnb-shadow:     0 2px 16px rgba(0,0,0,.12);
    --bnb-radius:     12px;
    --bnb-radius-sm:  8px;
    --bnb-font:       'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.btn-bnb {
    background: var(--bnb-red);
    color: #fff !important;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    padding: 12px 24px;
    cursor: pointer;
    transition: background .15s;
    font-family: var(--bnb-font);
    font-size: 14px;
}
.btn-bnb:hover { background: var(--bnb-red-hover); }
.bnb-cnt-btn {
    width: 32px; height: 32px; border-radius: 50%;
    border: 1px solid var(--bnb-border); background: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--bnb-dark); font-size: 14px;
    transition: border-color .15s, color .15s;
}
.bnb-cnt-btn:hover:not([disabled]) { border-color: var(--bnb-dark); color: var(--bnb-dark); }
.bnb-cnt-btn[disabled] { opacity: .3; cursor: not-allowed; }
.bnb-room-card { border: 1px solid var(--bnb-border); border-radius: 12px; overflow: hidden; margin-bottom: 16px; position: relative; transition: border-color .2s; }
.bnb-room-card.rt-cap-exceeded { border-color: rgba(255,56,92,.35); }
.bnb-room-card.rt-cap-exceeded::after {
    content: '';
    position: absolute; inset: 0; pointer-events: none; z-index: 2; border-radius: 12px;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 9px, rgba(255,56,92,.07) 9px, rgba(255,56,92,.07) 11px);
    border: 2px solid rgba(255,56,92,.3);
}
.rt-overcap-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600; color: var(--bnb-red);
    background: rgba(255,56,92,.08); border: 1px solid rgba(255,56,92,.2);
    border-radius: 20px; padding: 2px 8px; margin-top: 4px;
}
.rt-avail-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; border-radius: 20px; padding: 2px 8px; margin-top: 4px;
}
.rt-avail-badge.avail-ok    { color: #065f46; background: #ecfdf5; border: 1px solid #6ee7b7; }
.rt-avail-badge.avail-low   { color: #92400e; background: #fffbeb; border: 1px solid #fcd34d; }
.rt-avail-badge.avail-none  { color: #94a3b8; background: #f1f5f9; border: 1px solid #cbd5e1; }
.bnb-rate-row { display: flex; flex-direction: column; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--bnb-border); }
@media (min-width: 576px) {
    .bnb-rate-row { display: grid; grid-template-columns: 1fr auto auto; align-items: center; }
}
.bnb-rate-row:last-child { border-bottom: none; }
.bnb-rate-action { display: flex; justify-content: flex-end; }
@media (min-width: 576px) {
    .bnb-rate-action { display: block; }
}
.bnb-cal-square-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 3px;
}
.bnb-cal-square-cell {
    aspect-ratio: 1;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    padding: 4px;
}
.bnb-cal-square-cell .cd { line-height: 1.2; font-size: 15px; margin-bottom: 2px; }
.bnb-cal-square-cell .cs { font-size: 9px; font-weight: 600; line-height: 1; opacity: 0.9; }
.bnb-cal-square-cell.past { opacity: .3; cursor: not-allowed; }
.bnb-cal-square-cell.cal-empty { background: transparent !important; border-color: transparent !important; pointer-events: none; }
.bnb-cal-square-cell.cal-avail { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.bnb-cal-square-cell.cal-low-avail { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.bnb-cal-square-cell.cal-unavailable {
    background: #f1f5f9; border-color: #cbd5e1; color: #94a3b8;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(0,0,0,.06) 4px, rgba(0,0,0,.06) 5px);
}
.bnb-cal-square-cell.cal-blocked {
    background: #fff0f0; border-color: #fca5a5; color: #dc2626;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(220,38,38,0.12) 4px, rgba(220,38,38,0.12) 5px);
}
.bnb-cal-square-cell.cal-end {
    background: #fff !important;
    border-color: var(--bnb-red) !important;
    color: var(--bnb-red) !important;
    box-shadow: inset 0 0 0 2px var(--bnb-red);
}
.bnb-cal-square-cell.cal-selected:not(.cal-start):not(.cal-last-night):not(.cal-end),
.bnb-cal-square-cell.cal-range:not(.cal-start):not(.cal-last-night):not(.cal-end) {
    background: rgba(255, 56, 92, 0.18) !important;
    border-color: rgba(255, 56, 92, 0.45) !important;
    color: #9f1239 !important;
}
.bnb-cal-square-cell.cal-hover-range:not(.cal-start):not(.cal-end) {
    background: rgba(255, 56, 92, 0.1) !important;
    color: #9f1239 !important;
}
.bnb-cal-square-cell.cal-start,
.bnb-cal-square-cell.cal-last-night {
    background: var(--bnb-red) !important;
    border-color: var(--bnb-red) !important;
    color: #fff !important;
}
.bnb-cal-square-cell.cal-start .cd,
.bnb-cal-square-cell.cal-start .cs,
.bnb-cal-square-cell.cal-last-night .cd,
.bnb-cal-square-cell.cal-last-night .cs { color: #fff !important; opacity: 1; }
.bnb-cal-square-cell.cal-selected:not(.cal-start):not(.cal-last-night):not(.cal-end) .cd,
.bnb-cal-square-cell.cal-selected:not(.cal-start):not(.cal-last-night):not(.cal-end) .cs,
.bnb-cal-square-cell.cal-range:not(.cal-start):not(.cal-last-night) .cd,
.bnb-cal-square-cell.cal-range:not(.cal-start):not(.cal-last-night) .cs { color: #9f1239 !important; opacity: 1; }
.bnb-cal-square-cell.cal-end .cd,
.bnb-cal-square-cell.cal-end .cs { color: var(--bnb-red) !important; opacity: 1; font-weight: 700; }
.bnb-cal-square-cell .cal-day-check {
    position: absolute; top: 2px; right: 2px; font-size: 9px; line-height: 1;
    color: var(--bnb-red); font-weight: 700; z-index: 2;
}
.bnb-cal-square-cell.cal-start .cal-day-check,
.bnb-cal-square-cell.cal-last-night .cal-day-check { color: #fff; }
.bnb-avail-legend {
    display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
    font-size: 11px; color: var(--bnb-gray); margin-bottom: 10px;
    padding: 8px 10px; background: #fafafa; border-radius: 8px;
}
.bnb-avail-legend-item { display: flex; align-items: center; gap: 5px; }
.bnb-legend-dot { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }
.bnb-avail-loading {
    display: flex; align-items: center; justify-content: center;
    padding: 20px; color: var(--bnb-gray); font-size: 13px; gap: 8px;
}
@keyframes bnb-spin { to { transform: rotate(360deg); } }
.bnb-spinner {
    width: 16px; height: 16px; border: 2px solid var(--bnb-border);
    border-top-color: var(--bnb-red); border-radius: 50%;
    animation: bnb-spin 0.7s linear infinite; flex-shrink: 0;
}
.bnb-pay-bar {
    position: fixed; bottom: 0; right: 0; left: 0; z-index: 1055;
    background: #fff; border-top: 1px solid var(--bnb-border);
    padding: 12px 16px; box-shadow: 0 -4px 20px rgba(0,0,0,.12);
}
.swal2-container { z-index: 9999 !important; }
[x-cloak] { display: none !important; }
</style>
