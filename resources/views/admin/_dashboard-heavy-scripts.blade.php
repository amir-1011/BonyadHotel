@push('styles')
<link rel="stylesheet" href="{{ vasset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
@vite(['resources/js/rsb-layout-sort.js', 'resources/js/rsb-datepicker.js', 'resources/js/occupancy-calendar.js', 'resources/js/admin-overview-stats.js'])
<script src="{{ vasset('vendor/apexcharts/apexcharts.min.js') }}" id="apexcharts-sdk"></script>
<script>
(function () {
    function readAdminPayload() {
        const el = document.getElementById('admin-dashboard-payload');
        if (!el) return null;
        try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
    }

    let dashboardPayload = readAdminPayload() || {};
    const VENDOR_LEAFLET = @json(vasset('vendor/leaflet/leaflet.js'));
    const GEOJSON_URL = @json(vasset('vendor/iran-map/provinces.min.geojson'));
    const ns = window.__taIranMapDashboard = window.__taIranMapDashboard || {};
    let _iranMapInstance = ns.instance || null;
    let _vendorLeafletLoading = false;
    let _mapGeneration = ns.generation || 0;
    let _geoFetchAbort = null;
    let _mapInitScheduled = false;

    let geoCounts = dashboardPayload.geoCounts || {};
    let cityAccom = dashboardPayload.cityAccom || {};
    let provinceAccom = dashboardPayload.provinceAccom || {};
    let geoMax = dashboardPayload.geoMax || 0;
    let sparklines = dashboardPayload.sparklines || {};
    let chartsRendered = false;

    function syncAdminPayload() {
        dashboardPayload = readAdminPayload() || dashboardPayload;
        geoCounts = dashboardPayload.geoCounts || {};
        cityAccom = dashboardPayload.cityAccom || {};
        provinceAccom = dashboardPayload.provinceAccom || {};
        geoMax = dashboardPayload.geoMax || 0;
        sparklines = dashboardPayload.sparklines || {};
    }
    const faNum = n => new Intl.NumberFormat('fa-IR').format(n);

    function heatColor(v) {
        if (!v) return '#eef1f6';
        const t = geoMax > 0 ? v / geoMax : 0;
        if (t > 0.8) return '#1d39c4';
        if (t > 0.6) return '#465fff';
        if (t > 0.4) return '#7592ff';
        if (t > 0.2) return '#a4b6ff';
        return '#cdd8ff';
    }

    function mapErrorHtml(retryFn) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:#98a2b3;font-size:.85rem';
        wrap.innerHTML = '<div>خطا در بارگذاری نقشه</div>';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'تلاش دوباره';
        btn.style.cssText = 'padding:6px 20px;border:1px solid #d0d5dd;border-radius:8px;background:#fff;color:#344054;font-size:.82rem;font-family:Vazirmatn,sans-serif;cursor:pointer;transition:background .15s';
        btn.onmouseover = () => btn.style.background = '#f9fafb';
        btn.onmouseout  = () => btn.style.background = '#fff';
        btn.onclick = retryFn;
        wrap.appendChild(btn);
        return wrap;
    }

    function destroyIranMap() {
        _mapGeneration++;
        ns.generation = _mapGeneration;

        if (_geoFetchAbort) {
            _geoFetchAbort.abort();
            _geoFetchAbort = null;
        }

        const el = document.getElementById('iranMap');
        if (el) {
            el.style.pointerEvents = 'none';
        }

        const map = _iranMapInstance || el?._leafletMap;
        if (map) {
            try {
                map.off();
                map.remove();
            } catch (e) {}
        }

        _iranMapInstance = null;
        ns.instance = null;
        delete ns.focusCity;
        delete ns.focusProvinceByName;
        delete ns.resetView;

        if (el) {
            el._leafletMap = null;
            delete el.dataset.iranMapReady;
            delete el._leaflet_id;
            el.replaceChildren();
            el.style.pointerEvents = '';
        }
    }

    function ensureVendorLeaflet(callback) {
        if (!document.getElementById('iranMap')) return;

        const vendorSdk = document.getElementById('vendor-leaflet-sdk');
        if (vendorSdk?.dataset.ready === '1' && window.L) {
            callback();
            return;
        }

        if (window.L && document.getElementById('neshan-sdk-accommodation')?.dataset.ready === '1') {
            window.__neshanLeafletBackup = window.L;
            delete window.L;
        } else if (window.L && !vendorSdk) {
            const marker = document.createElement('script');
            marker.id = 'vendor-leaflet-sdk';
            marker.dataset.ready = '1';
            document.body.appendChild(marker);
            callback();
            return;
        }

        if (_vendorLeafletLoading) return;
        _vendorLeafletLoading = true;

        if (vendorSdk) vendorSdk.remove();

        const script = document.createElement('script');
        script.id = 'vendor-leaflet-sdk';
        script.src = VENDOR_LEAFLET;
        script.onload = function () {
            script.dataset.ready = '1';
            _vendorLeafletLoading = false;
            callback();
        };
        script.onerror = function () { _vendorLeafletLoading = false; };
        document.body.appendChild(script);
    }

    function bindCityListOnce() {
        if (ns.cityListBound) return;
        const cityList = document.getElementById('cityList');
        if (!cityList) return;
        ns.cityListBound = true;
        cityList.addEventListener('click', (e) => {
            const row = e.target.closest('.city-row');
            if (!row || !ns.focusCity) return;
            document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
            row.style.background = '#eef2ff';
            ns.focusCity(row.dataset.city, row.dataset.province);
        });
    }

    function initIranMapCore() {
        const mapEl = document.getElementById('iranMap');
        if (!mapEl || !window.L || !mapEl.isConnected) return;

        if (!mapEl.offsetWidth) {
            requestAnimationFrame(initIranMapCore);
            return;
        }

        if (mapEl.dataset.iranMapReady === '1' && mapEl._leafletMap?._container?.isConnected && ns.focusCity) {
            _iranMapInstance = mapEl._leafletMap;
            ns.instance = _iranMapInstance;
            try { _iranMapInstance.invalidateSize(); } catch (e) {}
            bindCityListOnce();
            return;
        }

        destroyIranMap();

        const generation = _mapGeneration;
        const map = L.map(mapEl, { zoomControl: true, attributionControl: false, scrollWheelZoom: false, zoomSnap: 0.1, zoomDelta: 0.5 });
        mapEl._leafletMap = map;
        _iranMapInstance = map;
        ns.instance = map;
        const provinceLayers = {};
        let geoLayer = null, homeBounds = null, selectedLayer = null;

        const CloseControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                const btn = L.DomUtil.create('button', 'ta-map-reset');
                btn.type = 'button';
                btn.innerHTML = '✕';
                btn.title = 'بازگشت به نمای کلی';
                btn.style.cssText = 'display:none;width:30px;height:30px;border:none;border-radius:8px;background:#fff;box-shadow:0 1px 4px rgba(16,24,40,.18);color:#475467;font-size:15px;font-weight:700;cursor:pointer;line-height:30px;text-align:center;padding:0';
                L.DomEvent.disableClickPropagation(btn);
                L.DomEvent.on(btn, 'click', function (e) { L.DomEvent.preventDefault(e); resetView(); });
                this._btn = btn;
                return btn;
            }
        });
        const closeControl = new CloseControl();
        map.addControl(closeControl);
        function showCloseControl() { if (closeControl._btn) closeControl._btn.style.display = 'block'; }
        function hideCloseControl() { if (closeControl._btn) closeControl._btn.style.display = 'none'; }
        function resetView() {
            const detail = document.getElementById('cityDetail');
            if (detail) detail.style.display = 'none';
            document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
            resetSelection();
            hideCloseControl();
            if (homeBounds) map.flyToBounds(homeBounds, { padding: [14, 14], duration: 0.8 });
        }

        const baseStyle = f => ({
            fillColor: heatColor(geoCounts[f.properties['name:fa']] || 0),
            weight: 1, color: '#ffffff', fillOpacity: 0.9
        });

        function resetSelection() {
            if (geoLayer) geoLayer.eachLayer(l => geoLayer.resetStyle(l));
            selectedLayer = null;
            map.closePopup();
        }
        function highlight(lyr) {
            if (!geoLayer) return;
            geoLayer.eachLayer(l => {
                if (l === lyr) {
                    l.setStyle({ fillColor: '#465fff', fillOpacity: 0.95, weight: 2.5, color: '#ffffff' });
                    l.bringToFront();
                } else {
                    l.setStyle({ fillOpacity: 0.18, weight: 1, color: '#ffffff' });
                }
            });
            selectedLayer = lyr;
        }

        function renderDetail(title, list) {
            const detail = document.getElementById('cityDetail');
            const titleEl = document.getElementById('cityDetailTitle');
            const body   = document.getElementById('cityDetailBody');
            if (!detail) return;
            titleEl.textContent = title;
            if (!list.length) {
                body.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.8rem">اقامتگاهی برای این محدوده ثبت نشده است</div>';
            } else {
                body.innerHTML = list.map(a => `
                    <div class="d-flex align-items-center justify-content-between p-2" style="background:#f9fafb;border:1px solid #f2f4f7;border-radius:10px">
                        <div class="text-truncate" style="max-width:55%">
                            <div class="fw-semibold text-truncate" style="font-size:.82rem;color:#101828">${a.name}</div>
                            <div class="text-muted" style="font-size:.7rem">${a.city ? a.city + ' · ' : ''}${faNum(a.confirmed)} تأییدشده از ${faNum(a.bookings)} رزرو</div>
                        </div>
                        <div class="text-start">
                            <div class="fw-bold" style="font-size:.8rem;color:#465fff">${faNum(a.revenue)}</div>
                            <div class="text-muted" style="font-size:.68rem">ریال</div>
                        </div>
                    </div>`).join('');
            }
            detail.style.display = 'block';
        }

        function showCityDetail(city, province) {
            renderDetail(city + ' (' + province + ')', cityAccom[city] || []);
        }

        function showProvinceDetail(province) {
            renderDetail('استان ' + province, provinceAccom[province] || []);
        }

        function focusProvince(city, province) {
            const lyr = provinceLayers[province];
            if (lyr) {
                highlight(lyr);
                const v = geoCounts[province] || 0;
                map.flyToBounds(lyr.getBounds(), { padding: [50, 50], maxZoom: 6, duration: 1.0 });
                L.popup({ closeButton: false, autoClose: false, closeOnClick: false, className: 'ta-city-pop' })
                    .setLatLng(lyr.getBounds().getCenter())
                    .setContent(`<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:center;line-height:1.6"><b style="font-size:13px;color:#101828">${city}</b><br><span style="color:#465fff">استان ${province} · ${faNum(v)} رزرو</span></div>`)
                    .openOn(map);
            }
            showCityDetail(city, province);
            showCloseControl();
        }

        function focusProvinceByName(province) {
            const lyr = provinceLayers[province];
            if (lyr) {
                highlight(lyr);
                const v = geoCounts[province] || 0;
                map.flyToBounds(lyr.getBounds(), { padding: [50, 50], maxZoom: 6, duration: 1.0 });
                L.popup({ closeButton: false, autoClose: false, closeOnClick: false, className: 'ta-city-pop' })
                    .setLatLng(lyr.getBounds().getCenter())
                    .setContent(`<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:center;line-height:1.6"><b style="font-size:13px;color:#101828">استان ${province}</b><br><span style="color:#465fff">${faNum(v)} رزرو</span></div>`)
                    .openOn(map);
            }
            document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
            showProvinceDetail(province);
            showCloseControl();
        }

        _geoFetchAbort = new AbortController();
        fetch(GEOJSON_URL, { signal: _geoFetchAbort.signal })
            .then(r => {
                if (!r.ok) throw new Error('geojson');
                return r.json();
            })
            .then(geo => {
                if (generation !== _mapGeneration || !mapEl.isConnected || map !== mapEl._leafletMap) return;
                geoLayer = L.geoJSON(geo, {
                    style: baseStyle,
                    onEachFeature: (f, lyr) => {
                        const name = f.properties['name:fa'];
                        provinceLayers[name] = lyr;
                        const v = geoCounts[name] || 0;
                        lyr.bindTooltip(
                            `<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:right"><b>${name}</b><br>${faNum(v)} رزرو</div>`,
                            { sticky: true, direction: 'top' }
                        );
                        lyr.on({
                            mouseover: e => { if (e.target !== selectedLayer) e.target.setStyle({ weight: 2, color: '#465fff', fillOpacity: 1 }); },
                            mouseout:  e => { if (e.target !== selectedLayer) geoLayer.resetStyle(e.target); },
                            click:     () => focusProvinceByName(name),
                        });
                    }
                }).addTo(map);
                homeBounds = geoLayer.getBounds();
                map.fitBounds(homeBounds, { padding: [14, 14] });
                mapEl.dataset.iranMapReady = '1';

                ns.focusCity = focusProvince;
                ns.focusProvinceByName = focusProvinceByName;
                ns.resetView = resetView;
                bindCityListOnce();

                const closeBtn = document.getElementById('cityDetailClose');
                if (closeBtn && !closeBtn.dataset.iranMapBound) {
                    closeBtn.dataset.iranMapBound = '1';
                    closeBtn.addEventListener('click', () => ns.resetView?.());
                }
            })
            .catch(err => {
                if (err?.name === 'AbortError') return;
                if (generation !== _mapGeneration || !mapEl.isConnected) return;
                destroyIranMap();
                mapEl.appendChild(mapErrorHtml(() => initIranMap()));
            })
            .finally(() => {
                if (_geoFetchAbort?.signal.aborted) return;
                _geoFetchAbort = null;
            });
    }

    function initIranMap() {
        if (!document.getElementById('iranMap')) return;
        if (_mapInitScheduled) return;
        _mapInitScheduled = true;
        ensureVendorLeaflet(function () {
            _mapInitScheduled = false;
            requestAnimationFrame(function () { requestAnimationFrame(initIranMapCore); });
        });
    }

    function onDashboardNavigating() {
        destroyIranMap();
        _vendorLeafletLoading = false;
        _mapInitScheduled = false;
    }

    if (!ns.listenersBound) {
        ns.listenersBound = true;
        document.addEventListener('livewire:navigating', onDashboardNavigating);
        document.addEventListener('livewire:navigated', initIranMap);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initIranMap);
        }
    }

    initIranMap();

    // ── Per-accommodation sparklines ──────────────────────────────────
    function renderSparklines() {
        if (!window.ApexCharts) return;
        chartsRendered = true;
        Object.entries(sparklines).forEach(([id, data]) => {
            const el = document.querySelector('#spark-' + id);
            if (!el) return;
            el.innerHTML = '';
            new ApexCharts(el, {
                series: [{ data }],
                chart: { type: 'bar', height: 60, sparkline: { enabled: true } },
                plotOptions: { bar: { columnWidth: '75%', borderRadius: 3 } },
                colors: ['#465fff'],
                tooltip: {
                    fixed: { enabled: false },
                    x: { show: false },
                    y: { formatter: v => new Intl.NumberFormat('fa-IR').format(v) + ' ریال' },
                    marker: { show: false }
                }
            }).render();
        });
    }

    function refreshAdminDashboardVisuals() {
        syncAdminPayload();
        destroyIranMap();
        chartsRendered = false;
        _mapInitScheduled = false;
        initIranMap();
        renderSparklines();
    }

    const collapseEl = document.getElementById('salesGridCollapse');
    const chevron    = document.getElementById('salesChevron');
    if (collapseEl) {
        collapseEl.addEventListener('show.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(0deg)';
            setTimeout(renderSparklines, 50);
        });
        collapseEl.addEventListener('hide.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        });
        if (collapseEl.classList.contains('show')) {
            document.addEventListener('DOMContentLoaded', renderSparklines);
            renderSparklines();
        }
    }
    document.getElementById('apexcharts-sdk')?.addEventListener('load', renderSparklines);
    document.addEventListener('livewire:navigated', renderSparklines);

    function bindDashboardFilterRefresh() {
        if (window._adminDashboardFilterBound) return;
        window._adminDashboardFilterBound = true;
        const refresh = () => requestAnimationFrame(refreshAdminDashboardVisuals);
        document.addEventListener('dashboard-accommodation-filter-changed', refresh);
        if (window.Livewire) {
            Livewire.on('dashboard-accommodation-filter-changed', refresh);
        }
    }
    bindDashboardFilterRefresh();
})();
</script>
@endpush
