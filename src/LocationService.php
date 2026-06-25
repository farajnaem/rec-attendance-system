<?php

declare(strict_types=1);

class LocationService
{
    public const MIN_TOLERANCE_METERS = 50;
    public const DEFAULT_TOLERANCE_METERS = 200;

    public static function all(bool $activeOnly = true): array
    {
        $pdo = Database::getConnection();
        $sql = 'SELECT * FROM work_locations';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';
        return $pdo->query($sql)->fetchAll();
    }

    public static function get(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM work_locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(
        string $name,
        string $address,
        float $latitude,
        float $longitude,
        int $radiusMeters,
        int $createdBy
    ): int {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('اسم الموقع مطلوب.');
        }
        self::assertValidCoordinates($latitude, $longitude);
        $radiusMeters = self::normalizeTolerance($radiusMeters);

        $pdo = Database::getConnection();
        $pdo->prepare(
            'INSERT INTO work_locations (name, address, latitude, longitude, radius_meters, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        )->execute([$name, trim($address), $latitude, $longitude, $radiusMeters, $createdBy]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(
        int $id,
        string $name,
        string $address,
        float $latitude,
        float $longitude,
        int $radiusMeters,
        bool $isActive
    ): void {
        if (!self::get($id)) {
            throw new RuntimeException('الموقع غير موجود.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('اسم الموقع مطلوب.');
        }
        self::assertValidCoordinates($latitude, $longitude);
        $radiusMeters = self::normalizeTolerance($radiusMeters);

        $pdo = Database::getConnection();
        $pdo->prepare(
            'UPDATE work_locations SET name=?, address=?, latitude=?, longitude=?, radius_meters=?, is_active=? WHERE id=?'
        )->execute([$name, trim($address), $latitude, $longitude, $radiusMeters, $isActive ? 1 : 0, $id]);
    }

    public static function hasActiveLocations(): bool
    {
        $pdo = Database::getConnection();
        return (int) $pdo->query('SELECT COUNT(*) FROM work_locations WHERE is_active = 1')->fetchColumn() > 0;
    }

    /** للواجهة: إحداثيات المواقع المعتمدة */
    public static function activeForClient(): array
    {
        return array_map(static function (array $loc): array {
            return [
                'id' => (int) $loc['id'],
                'name' => $loc['name'],
                'latitude' => (float) $loc['latitude'],
                'longitude' => (float) $loc['longitude'],
                'tolerance_meters' => self::toleranceFor($loc),
            ];
        }, self::all(true));
    }

    public static function toleranceFor(array $loc): int
    {
        return self::normalizeTolerance((int) ($loc['radius_meters'] ?? self::DEFAULT_TOLERANCE_METERS));
    }

    /**
     * يطابق إحداثيات الموظف مع إحداثيات المشرف المسجّلة.
     * يُقبل فرق بسيط (بالمتر) لأن GPS الهاتف ليس دقيقاً إلى السنتيمتر.
     */
    public static function employeeAtRegisteredLocation(float $empLat, float $empLng, array $loc): bool
    {
        $refLat = (float) $loc['latitude'];
        $refLng = (float) $loc['longitude'];
        $tolerance = self::toleranceFor($loc);
        $distance = self::distanceMeters($empLat, $empLng, $refLat, $refLng);

        return $distance <= $tolerance;
    }

    public static function findMatchingLocation(float $latitude, float $longitude): ?array
    {
        $best = null;
        foreach (self::all(true) as $loc) {
            if (!self::employeeAtRegisteredLocation($latitude, $longitude, $loc)) {
                continue;
            }
            $distance = self::distanceMeters(
                $latitude,
                $longitude,
                (float) $loc['latitude'],
                (float) $loc['longitude']
            );
            if ($best === null || $distance < $best['distance']) {
                $best = ['location' => $loc, 'distance' => $distance];
            }
        }
        return $best;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function validateSignCoordinates(?float $latitude, ?float $longitude): array
    {
        if (!self::hasActiveLocations()) {
            return ['location_id' => null, 'location_name' => null];
        }
        if ($latitude === null || $longitude === null) {
            throw new RuntimeException('يجب تفعيل الموقع الجغرافي في المتصفح لتسجيل الحضور.');
        }

        $match = self::findMatchingLocation($latitude, $longitude);
        if (!$match) {
            throw new RuntimeException(self::buildMismatchMessage($latitude, $longitude));
        }

        return [
            'location_id' => (int) $match['location']['id'],
            'location_name' => $match['location']['name'],
        ];
    }

    public static function buildMismatchMessage(float $empLat, float $empLng): string
    {
        $locations = self::all(true);
        if (empty($locations)) {
            return 'لا توجد مواقع عمل معتمدة.';
        }

        $parts = [
            sprintf(
                'إحداثيات موقعك عند التوقيع (خط العرض: %.6f، خط الطول: %.6f) لا تطابق المواقع المعتمدة.',
                $empLat,
                $empLng
            ),
        ];

        foreach ($locations as $loc) {
            $refLat = (float) $loc['latitude'];
            $refLng = (float) $loc['longitude'];
            $tolerance = self::toleranceFor($loc);
            $distance = (int) round(self::distanceMeters($empLat, $empLng, $refLat, $refLng));
            $parts[] = sprintf(
                'الموقع «%s»: خط العرض %.6f، خط الطول %.6f — أنت على بُعد ~%d م (المسموح %d م).',
                $loc['name'],
                $refLat,
                $refLng,
                $distance,
                $tolerance
            );
        }

        $parts[] = 'تأكد أنك في موقع العمل وأن المشرف أدخل الإحداثيات الصحيحة من خرائط Google.';

        return implode(' ', $parts);
    }

    private static function assertValidCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('إحداثيات غير صالحة. خط العرض بين -90 و90، وخط الطول بين -180 و180.');
        }
        if (abs($latitude) < 0.0001 && abs($longitude) < 0.0001) {
            throw new InvalidArgumentException('الإحداثيات تبدو فارغة أو خاطئة.');
        }
    }

    private static function normalizeTolerance(int $meters): int
    {
        if ($meters < self::MIN_TOLERANCE_METERS) {
            return self::MIN_TOLERANCE_METERS;
        }
        if ($meters > 5000) {
            return 5000;
        }
        return $meters;
    }
}
