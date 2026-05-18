// ========================================================================
// Syriazzle - Zone Manager (Version 6.0 - Manual Center Marker)
// ========================================================================

document.addEventListener('DOMContentLoaded', () => {

    const mapModal = document.getElementById('mapModal');
    const mapModalTitle = document.getElementById('mapModalTitle');
    const cancelMapBtn = document.getElementById('cancelMapBtn');
    const saveMapBtn = document.getElementById('saveMapBtn');
    const tableBody = document.querySelector('.data-table tbody');
    
    let map = null;
    let editableLayers = new L.FeatureGroup();
    let centerMarker = null; // متغير لتخزين أيقونة المركز
    let currentZoneId = null;
    let hasChanges = false;

    // أيقونة خاصة للمركز (نقطة الانطلاق)
    const depotIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-gold.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // --- دالة فتح المحرر ---
    const openMapEditor = (zone) => {
        currentZoneId = zone.id;
        mapModalTitle.textContent = `تعديل منطقة: ${zone.zone_name} (حدد الحدود + نقطة الانطلاق)`;
        hasChanges = false;
        saveMapBtn.disabled = true;

        mapModal.style.display = 'flex';

        setTimeout(() => {
            if (!map) {
                map = L.map('mapEditor').setView([34.8021, 38.9968], 7);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19, attribution: '© OpenStreetMap'
                }).addTo(map);
                
                map.addLayer(editableLayers);

                const drawControl = new L.Control.Draw({
                    edit: { featureGroup: editableLayers, remove: true },
                    draw: {
                        polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#0d6efd', weight: 4 } },
                        polyline: false, marker: false, circle: false, circlemarker: false, rectangle: false 
                    }
                });
                map.addControl(drawControl);
                
                map.on(L.Draw.Event.CREATED, handleDrawCreate);
                map.on(L.Draw.Event.EDITED, handleDrawEdit);
                map.on(L.Draw.Event.DELETED, handleDrawEdit);
            } else {
                 map.invalidateSize(); 
            }
            
            // 1. تنظيف الطبقات والماركر
            editableLayers.clearLayers();
            if (centerMarker) {
                map.removeLayer(centerMarker);
                centerMarker = null;
            }

            // 2. تحميل الرسمة (المضلع)
            let hasPolygon = false;
            try {
                if (zone.polygon && zone.polygon !== "[]" && zone.polygon !== "") {
                    const polygonData = JSON.parse(zone.polygon);
                    if (Array.isArray(polygonData) && polygonData.length > 0) {
                        const polygon = L.polygon(polygonData, {color: '#28a745', weight: 4}).addTo(editableLayers);
                        map.fitBounds(polygon.getBounds());
                        hasPolygon = true;
                    }
                }
            } catch (e) { console.warn(e); }

            // 3. وضع نقطة الانطلاق (المركز)
            let centerLat = zone.center_latitude;
            let centerLng = zone.center_longitude;

            // إذا لم يكن هناك مركز محفوظ، نضعه في وسط الخريطة الحالية أو وسط المضلع
            if (!centerLat || !centerLng) {
                const center = map.getCenter();
                centerLat = center.lat;
                centerLng = center.lng;
            }

            // إنشاء الماركر القابل للسحب
            centerMarker = L.marker([centerLat, centerLng], {
                icon: depotIcon,
                draggable: true,
                title: "نقطة انطلاق الطلبات (اسحبني لتغيير المكان)"
            }).addTo(map);
            
            centerMarker.bindPopup("<b>نقطة الانطلاق</b><br>يتم حساب تكلفة التوصيل بدءاً من هذه النقطة.<br>اسحبها لتحديد موقع الفرع أو المستودع.").openPopup();

            // تفعيل زر الحفظ عند تحريك الماركر
            centerMarker.on('dragend', () => {
                hasChanges = true;
                saveMapBtn.disabled = false;
            });

            if (!hasPolygon) {
                map.setView([centerLat, centerLng], 10);
            }

        }, 100);
    };

    const handleDrawCreate = (e) => {
        editableLayers.clearLayers();
        editableLayers.addLayer(e.layer);
        hasChanges = true;
        saveMapBtn.disabled = false;
    };
    
    const handleDrawEdit = () => { hasChanges = true; saveMapBtn.disabled = false; };

    const closeMapEditor = () => {
        mapModal.style.display = 'none';
        editableLayers.clearLayers();
        if(centerMarker) map.removeLayer(centerMarker);
        currentZoneId = null;
    };

    const saveChanges = async () => {
        let latLngs = [];
        
        // 1. جلب إحداثيات المضلع
        if (editableLayers.getLayers().length > 0) {
            const polygonLayer = editableLayers.getLayers()[0];
            latLngs = polygonLayer.getLatLngs()[0].map(latlng => [latlng.lat, latlng.lng]);
        }

        // 2. جلب إحداثيات نقطة الانطلاق (من الماركر الذهبي)
        const centerPos = centerMarker.getLatLng();

        const formData = new FormData();
        formData.append('zone_id', currentZoneId);
        formData.append('polygon_data', JSON.stringify(latLngs));
        formData.append('center_lat', centerPos.lat);
        formData.append('center_lng', centerPos.lng);

        saveMapBtn.disabled = true;
        saveMapBtn.textContent = 'جاري الحفظ...';

        try {
            const response = await fetch('php/save_zone.php', { method: 'POST', body: formData });
            
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Invalid JSON response");
            }

            const result = await response.json();

            if (result.success) {
                // تحديث البيانات في الجدول فوراً
                const buttonInTable = tableBody.querySelector(`.edit-zone-btn[data-zone-id='${currentZoneId}']`);
                if(buttonInTable) {
                    buttonInTable.dataset.polygon = JSON.stringify(latLngs);
                    // تحديث المركز أيضاً في الزر للمرة القادمة
                    buttonInTable.dataset.centerLat = centerPos.lat;
                    buttonInTable.dataset.centerLng = centerPos.lng;
                }
                showSystemMessage(result.message, 'success');
                closeMapEditor();
            } else {
                alert('فشل الحفظ: ' + result.message);
            }
        } catch (error) {
            console.error('Save Error:', error);
            alert('حدث خطأ فني أثناء الاتصال بالسيرفر.');
        } finally {
            saveMapBtn.textContent = 'حفظ التغييرات';
        }
    };
    
    const showSystemMessage = (message, type) => {
        const container = document.getElementById('system-message-container');
        if(container) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `system-message ${type}`;
            msgDiv.textContent = message;
            container.appendChild(msgDiv);
            setTimeout(() => { msgDiv.remove(); }, 4000);
        } else { alert(message); }
    };

    if (tableBody) {
        tableBody.addEventListener('click', (e) => {
            const editButton = e.target.closest('.edit-zone-btn');
            if (editButton) {
                openMapEditor({
                    id: parseInt(editButton.dataset.zoneId),
                    zone_name: editButton.dataset.zoneName,
                    polygon: editButton.dataset.polygon,
                    // تمرير المركز المحفوظ حالياً
                    center_latitude: parseFloat(editButton.dataset.centerLat || 0),
                    center_longitude: parseFloat(editButton.dataset.centerLng || 0)
                });
            }
        });
    }

    if(cancelMapBtn) cancelMapBtn.addEventListener('click', closeMapEditor);
    if(saveMapBtn) saveMapBtn.addEventListener('click', saveChanges);
});