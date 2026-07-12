<?php

namespace App\Services\Biometric\SourceAFIS;

use Exception;

class SourceAFISNativeMatcher
{
    public function extractTemplate(string $imagePath): array
    {
        try {
            $image = $this->loadImage($imagePath);
            if ($image === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to load image',
                ];
            }

            imagefilter($image, IMG_FILTER_GRAYSCALE);
            $minutiae = $this->extractMinutiaeSimplified($image);
            $template = base64_encode(json_encode($minutiae));

            imagedestroy($image);

            return [
                'success' => true,
                'template' => $template,
                'minutiae_count' => count($minutiae),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Extraction error: ' . $e->getMessage(),
            ];
        }
    }

    public function compareTemplates(string $template1, string $template2, float $threshold): array
    {
        try {
            $minutiae1 = json_decode(base64_decode($template1), true);
            $minutiae2 = json_decode(base64_decode($template2), true);

            if (!$minutiae1 || !$minutiae2) {
                return [
                    'success' => false,
                    'error' => 'Invalid template format',
                ];
            }

            $similarity = $this->calculateMinutiaeSimilarity($minutiae1, $minutiae2);

            return [
                'success' => true,
                'similarity' => $similarity,
                'match' => $similarity >= $threshold,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Comparison error: ' . $e->getMessage(),
            ];
        }
    }

    private function extractMinutiaeSimplified($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minutiae = [];
        $threshold = 127;

        for ($y = 2; $y < $height - 2; $y += 3) {
            for ($x = 2; $x < $width - 2; $x += 3) {
                $pixel = imagecolorat($image, $x, $y);
                $gray = $pixel & 0xFF;

                if ($gray < $threshold) {
                    $neighbors = $this->countRidgeNeighbors($image, $x, $y, $threshold);
                    if ($neighbors === 1 || $neighbors === 3) {
                        $minutiae[] = [
                            'x' => $x,
                            'y' => $y,
                            'type' => $neighbors === 1 ? 'ending' : 'bifurcation',
                            'angle' => $this->estimateRidgeAngle($image, $x, $y, $threshold),
                        ];
                    }
                }
            }
        }

        return $minutiae;
    }

    private function countRidgeNeighbors($image, int $x, int $y, int $threshold): int
    {
        $count = 0;
        $offsets = [
            [-1, -1], [0, -1], [1, -1],
            [-1,  0],          [1,  0],
            [-1,  1], [0,  1], [1,  1],
        ];

        foreach ($offsets as [$dx, $dy]) {
            $nx = $x + $dx;
            $ny = $y + $dy;

            if ($nx >= 0 && $nx < imagesx($image) && $ny >= 0 && $ny < imagesy($image)) {
                $pixel = imagecolorat($image, $nx, $ny);
                $gray = $pixel & 0xFF;

                if ($gray < $threshold) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function estimateRidgeAngle($image, int $x, int $y, int $threshold): float
    {
        $gx = 0;
        $gy = 0;

        for ($dy = -1; $dy <= 1; $dy++) {
            for ($dx = -1; $dx <= 1; $dx++) {
                $nx = $x + $dx;
                $ny = $y + $dy;

                if ($nx >= 0 && $nx < imagesx($image) && $ny >= 0 && $ny < imagesy($image)) {
                    $pixel = imagecolorat($image, $nx, $ny);
                    $gray = $pixel & 0xFF;

                    $gx += $dx * $gray;
                    $gy += $dy * $gray;
                }
            }
        }

        return atan2($gy, $gx);
    }

    private function calculateMinutiaeSimilarity(array $minutiae1, array $minutiae2): float
    {
        if (empty($minutiae1) || empty($minutiae2)) {
            return 0.0;
        }

        $matches = 0;
        $maxDistance = 20;
        $maxAngleDiff = 0.5;

        foreach ($minutiae1 as $m1) {
            foreach ($minutiae2 as $m2) {
                if ($m1['type'] !== $m2['type']) {
                    continue;
                }

                $distance = sqrt(
                    pow($m1['x'] - $m2['x'], 2) +
                    pow($m1['y'] - $m2['y'], 2)
                );

                if ($distance > $maxDistance) {
                    continue;
                }

                $angleDiff = abs($m1['angle'] - $m2['angle']);
                if ($angleDiff > pi()) {
                    $angleDiff = 2 * pi() - $angleDiff;
                }

                if ($angleDiff <= $maxAngleDiff) {
                    $matches++;
                    break;
                }
            }
        }

        $avgCount = (count($minutiae1) + count($minutiae2)) / 2;

        return min(1.0, $matches / max(1, $avgCount));
    }

    private function loadImage(string $path)
    {
        $imageInfo = getimagesize($path);
        if ($imageInfo === false) {
            return false;
        }

        return match ($imageInfo['mime']) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/bmp' => imagecreatefrombmp($path),
            default => false,
        };
    }
}
