<?php
// ملف يحتوي على دوال قابلة لإعادة الاستخدام لفحص مناطق التوصيل

/**
 * يفحص منطقة التوصيل لزوج من الإحداثيات (خط العرض وخط الطول).
 *
 * @param float $latitude خط عرض الزبون.
 * @param float $longitude خط طول الزبون.
 * @param PDO $pdo كائن الاتصال بقاعدة البيانات.
 * @return array مصفوفة تحتوي على نتيجة الفحص.
 */
function checkDeliveryZone(float $latitude, float $longitude, PDO $pdo): array
{
    try {
        // جلب جميع المناطق الفعالة من قاعدة البيانات
        $stmt = $pdo->query("SELECT zone_type, zone_polygon, surcharge_fee, zone_name FROM delivery_zones WHERE is_active = 1");
        $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $customerPoint = [$longitude, $latitude]; // التنسيق: [x, y] للخوارزمية
        $foundZone = null;

        // المرور على كل منطقة تم جلبها من قاعدة البيانات
        foreach ($zones as $zone) {
            $polygonVertices = json_decode($zone['zone_polygon'], true);
            
            // ---  الحل والإضافة الجديدة هنا ---
            // إذا فشلت عملية فك تشفير JSON (لأن الخريطة لم تُرسم بعد) أو كانت المصفوفة فارغة،
            // تجاهل هذه المنطقة وانتقل إلى المنطقة التالية في الحلقة.
            if (!is_array($polygonVertices) || empty($polygonVertices)) {
                continue; // هذا السطر هو الذي يمنع حدوث الخطأ الفادح
            }
            // --- نهاية الإضافة ---

            // بيانات المضلع من Leaflet.draw تكون [[lat, lng]]، نحتاج لقلبها للخوارزمية لتصبح [[lng, lat]]
            $polygonForCheck = array_map(function($vertex) {
                return [$vertex[1], $vertex[0]]; // قلب الإحداثيات لتصبح [x, y]
            }, $polygonVertices);


            if (isPointInPolygon($customerPoint, $polygonForCheck)) {
                // إذا تم العثور على النقطة، نعطي الأولوية للمنطقة "الممتدة" في حال كانت النقطة تقع في منطقتين
                if (!$foundZone || $zone['zone_type'] === 'extended') {
                    $foundZone = $zone;
                }
            }
        }

        if ($foundZone) {
            // إذا تم العثور على الزبون في منطقة خدمة
            return [
                'status'    => 'in_service',
                'surcharge' => (float) $foundZone['surcharge_fee'],
                'zone_name' => $foundZone['zone_name']
            ];
        } else {
            // إذا لم يتم العثور على الزبون في أي منطقة مرسومة
            return ['status' => 'out_of_service', 'surcharge' => 0, 'zone_name' => 'N/A'];
        }

    } catch (PDOException $e) {
        error_log("Zone Checker Error: " . $e->getMessage());
        // في حال حدوث خطأ في قاعدة البيانات، نفترض أنها خارج الخدمة كإجراء وقائي
        return ['status' => 'out_of_service', 'surcharge' => 0, 'zone_name' => 'Error'];
    }
}

/**
 * خوارزمية النقطة داخل المضلع (Point in Polygon).
 * تفحص إذا كانت نقطة ما تقع داخل مضلع معين.
 *
 * @param array $point النقطة المطلوب فحصها [x, y] (lng, lat).
 * @param array $polygon مصفوفة من رؤوس المضلع [[x, y], [x, y], ...].
 * @return bool
 */
function isPointInPolygon(array $point, array $polygon): bool
{
    $intersections = 0;
    $vertices_count = count($polygon);

    for ($i = 0, $j = $vertices_count - 1; $i < $vertices_count; $j = $i++) {
        $vertex1 = $polygon[$i];
        $vertex2 = $polygon[$j];

        if ($point[1] > min($vertex1[1], $vertex2[1]) && 
            $point[1] <= max($vertex1[1], $vertex2[1]) &&
            $point[0] <= max($vertex1[0], $vertex2[0]) &&
            $vertex1[1] != $vertex2[1]) 
        {
            $xinters = ($point[1] - $vertex1[1]) * ($vertex2[0] - $vertex1[0]) / ($vertex2[1] - $vertex1[1]) + $vertex1[0];
            if ($vertex1[0] == $vertex2[0] || $point[0] <= $xinters) {
                $intersections++;
            }
        }
    }

    return ($intersections % 2 != 0);
}