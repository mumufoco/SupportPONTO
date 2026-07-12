<?php

namespace App\Services\Geolocation;

class GeocodingService
{
    public function reverseGeocode(float $latitude, float $longitude): array
    {
        try {
            $client = \Config\Services::curlrequest();
            $response = $client->get('https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ],
                'headers' => ['User-Agent' => 'PontoEletronico/1.0'],
                'timeout' => 5,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return ['success' => false, 'error' => 'Erro ao obter endereço.'];
            }

            $data = json_decode($response->getBody(), true);
            if (!$data || isset($data['error'])) {
                return ['success' => false, 'error' => 'Endereço não encontrado.'];
            }

            $address = $data['address'] ?? [];

            return [
                'success' => true,
                'formatted_address' => $data['display_name'] ?? '',
                'address' => [
                    'road' => $address['road'] ?? '',
                    'suburb' => $address['suburb'] ?? '',
                    'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? '',
                    'state' => $address['state'] ?? '',
                    'country' => $address['country'] ?? '',
                    'postcode' => $address['postcode'] ?? '',
                ],
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            log_message('error', 'Reverse geocode error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao conectar com serviço de geolocalização.', 'details' => $e->getMessage()];
        }
    }

    public function geocode(string $address): array
    {
        try {
            $client = \Config\Services::curlrequest();
            $response = $client->get('https://nominatim.openstreetmap.org/search', [
                'query' => [
                    'format' => 'json',
                    'q' => $address,
                    'limit' => 1,
                    'addressdetails' => 1,
                ],
                'headers' => ['User-Agent' => 'PontoEletronico/1.0'],
                'timeout' => 5,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return ['success' => false, 'error' => 'Erro ao obter coordenadas.'];
            }

            $data = json_decode($response->getBody(), true);
            if (empty($data)) {
                return ['success' => false, 'error' => 'Endereço não encontrado.'];
            }

            $result = $data[0];

            return [
                'success' => true,
                'latitude' => (float) $result['lat'],
                'longitude' => (float) $result['lon'],
                'formatted_address' => $result['display_name'],
                'address' => $result['address'] ?? [],
            ];
        } catch (\Exception $e) {
            log_message('error', 'Geocode error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Erro ao conectar com serviço de geolocalização.', 'details' => $e->getMessage()];
        }
    }
}
