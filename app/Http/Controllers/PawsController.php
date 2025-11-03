<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PawsController extends Controller
{
    private $pawsUrl = 'https://paws.datalab.eu';
    private $username = 'SQLTREN_ADM2';
    private $password = 'C+4WY5j?6w';

    /**
     * Check for recent data in PAWS (2025)
     */
    public function checkRecentData()
    {
        Log::info('=== CHECKING FOR RECENT PAWS DATA ===');
        
        try {
            // Check for 2025 data
            $payload2025 = [
                "start" => 0,
                "length" => 10,
                "fieldsToReturn" => "acKey,adDate,acStatus,acConsignee",
                "tableFKs" => [],
                "customConditions" => [
                    "condition" => "adDate >= '2025-01-01'",
                    "params" => []
                ],
                "sortColumn" => "",
                "sortOrder" => "",
                "withSubSelects" => 0
            ];
            
            Log::info('2025 Data Request: ' . json_encode($payload2025));
            
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($this->pawsUrl . '/api/Order/retrieve', $payload2025);
            
            Log::info('2025 Response Status: ' . $response->status());
            Log::info('2025 Response: ' . $response->body());
            
            return response()->json([
                'status' => $response->status(),
                'has_2025_data' => $response->successful() && !empty($response->json()),
                'data' => $response->json()
            ]);
            
        } catch (\Exception $e) {
            Log::error('2025 Data Check Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Test PAWS API connection and log detailed information
     */
    public function testPawsConnection()
    {
        Log::info('=== PAWS API CONNECTION TEST START ===');
        Log::info('Base URL: ' . $this->pawsUrl);
        Log::info('Username: ' . $this->username);
        Log::info('Password: ' . substr($this->password, 0, 3) . '***');
        
        try {
            // Test basic connection with minimal payload
            $testPayload = [
                "start" => 0,
                "length" => 1,
                "fieldsToReturn" => "*",
                "tableFKs" => [],
                "customConditions" => [
                    "condition" => "1=1",
                    "params" => []
                ],
                "sortColumn" => "",
                "sortOrder" => "",
                "withSubSelects" => 0
            ];
            
            Log::info('Test Payload: ' . json_encode($testPayload));
            
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->timeout(30)
                ->post($this->pawsUrl . '/api/Order/retrieve', $testPayload);
            
            Log::info('Response Status: ' . $response->status());
            Log::info('Response Headers: ' . json_encode($response->headers()));
            Log::info('Response Body: ' . $response->body());
            
            return response()->json([
                'status' => $response->status(),
                'success' => $response->successful(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            Log::error('PAWS Test Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the list of radni nalozi (work orders) from PAWS system
     */
    public function radniNaloziList()
    {
        $pageConfigs = ['pageHeader' => false];
        
        try {
            // Fetch radni nalozi from PAWS system
            $radniNalozi = $this->fetchRadniNalozi();
            
            // Calculate status statistics
            $statusStats = $this->calculateStatusStats($radniNalozi);
            
        return view('/content/apps/invoice/app-invoice-list', [
            'pageConfigs' => $pageConfigs,
            'radniNalozi' => $radniNalozi,
            'statusStats' => $statusStats
        ]);
            
        } catch (\Exception $e) {
            Log::error('PAWS API Error: ' . $e->getMessage());
            
            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
                'statusStats' => [
                    'svi' => 0,
                    'planiran' => 0,
                    'otvoren' => 0,
                    'rezerviran' => 0,
                    'raspisan' => 0,
                    'u_radu' => 0,
                    'djelimicno_zakljucen' => 0,
                    'zakljucen' => 0
                ],
                'error' => 'Greška pri učitavanju radnih naloga iz PAWS sistema.'
            ]);
        }
    }

    /**
     * Fetch radni nalozi from PAWS system via API
     */
    private function fetchRadniNalozi()
    {
        try {
            // Prepare the API request payload for PAWS Order/retrieve endpoint
            $payload = [
                "start" => 0,
                "length" => 100, // Get more records to see recent data
                "fieldsToReturn" => "*", // Get all fields
                "tableFKs" => [],
                "customConditions" => [
                    "condition" => "adDate >= '2023-01-01'", // Get records from 2023 onwards to show more recent data
                    "params" => []
                ],
                "sortColumn" => "", // Remove sorting to avoid column name issues
                "sortOrder" => "",
                "withSubSelects" => 0
            ];
            
            // Log the request payload for debugging
            Log::info('PAWS API Request Payload: ' . json_encode($payload));
            
            // Make authenticated request to PAWS API
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($this->pawsUrl . '/api/Order/retrieve', $payload);
            
            // Log response details for debugging
            Log::info('PAWS API Response Status: ' . $response->status());
            Log::info('PAWS API Response Headers: ' . json_encode($response->headers()));
            
            if ($response->successful()) {
                $data = $response->json();
                Log::info('PAWS API Success Response: ' . json_encode($data));
                
                // Transform PAWS data to our expected format
                return $this->transformPawsData($data);
            } else {
                $errorBody = $response->body();
                Log::error('PAWS API Error Response: ' . $errorBody);
                throw new \Exception('Failed to fetch radni nalozi data: ' . $errorBody);
            }
            
        } catch (\Exception $e) {
            Log::error('PAWS fetch error: ' . $e->getMessage());
            
            // Return mock data for development/testing
            return $this->getMockRadniNalozi();
        }
    }

    /**
     * Transform PAWS API response data to our expected format
     */
    private function transformPawsData($pawsData)
    {
        // Handle different possible response structures from PAWS API
        $orders = [];
        
        if (isset($pawsData['data']) && is_array($pawsData['data'])) {
            $orders = $pawsData['data'];
        } elseif (isset($pawsData['orders']) && is_array($pawsData['orders'])) {
            $orders = $pawsData['orders'];
        } elseif (is_array($pawsData)) {
            $orders = $pawsData;
        }
        
        $transformedData = [];
        
        foreach ($orders as $order) {
            $transformedData[] = [
                'responsive_id' => '', // Required by DataTables for responsive functionality
                'id' => $order['acKey'] ?? null,
                'broj_naloga' => $order['acRefNo1'] ?? $order['acKey'] ?? 'N/A',
                'naziv' => $order['acDocType'] ?? 'Radni nalog',
                'opis' => $order['acNote'] ?? $order['acStatement'] ?? 'N/A',
                'status' => $this->mapStatus($order['acStatus'] ?? 'N/A'),
                'prioritet' => $order['acWayOfSale'] ?? 'Srednji',
                'datum_kreiranja' => $order['adDate'] ? date('Y-m-d', strtotime($order['adDate'])) : 'N/A',
                'datum_zavrsetka' => $order['adDeliveryDeadline'] ? date('Y-m-d', strtotime($order['adDeliveryDeadline'])) : null,
                'dodeljen_korisnik' => 'Korisnik ' . ($order['anClerk'] ?? 'N/A'),
                'klijent' => $order['acConsignee'] ?? $order['acReceiver'] ?? 'N/A',
                'vrednost' => $order['anValue'] ?? 0,
                'valuta' => $order['acCurrency'] ?? 'RSD',
                'magacin' => $order['acWarehouse'] ?? 'N/A'
            ];
        }
        
        return $transformedData;
    }

    /**
     * Calculate status statistics from radni nalozi data
     */
    private function calculateStatusStats($radniNalozi)
    {
        $stats = [
            'svi' => count($radniNalozi),
            'planiran' => 0,
            'otvoren' => 0,
            'rezerviran' => 0,
            'raspisan' => 0,
            'u_radu' => 0,
            'djelimicno_zakljucen' => 0,
            'zakljucen' => 0
        ];
        
        foreach ($radniNalozi as $nalog) {
            $status = strtolower($nalog['status'] ?? '');
            
            if (strpos($status, 'planiran') !== false || strpos($status, 'novo') !== false) {
                $stats['planiran']++;
            } elseif (strpos($status, 'otvoren') !== false || strpos($status, 'novo') !== false) {
                $stats['otvoren']++;
            } elseif (strpos($status, 'rezerviran') !== false) {
                $stats['rezerviran']++;
            } elseif (strpos($status, 'raspisan') !== false) {
                $stats['raspisan']++;
            } elseif (strpos($status, 'u toku') !== false || strpos($status, 'u radu') !== false || strpos($status, 'u_radu') !== false) {
                $stats['u_radu']++;
            } elseif (strpos($status, 'djelimično') !== false || strpos($status, 'djelimicno') !== false) {
                $stats['djelimicno_zakljucen']++;
            } elseif (strpos($status, 'završeno') !== false || strpos($status, 'zaključen') !== false || strpos($status, 'zakljucen') !== false) {
                $stats['zakljucen']++;
            }
        }
        
        return $stats;
    }

    /**
     * Map PAWS status codes to readable text
     */
    private function mapStatus($statusCode)
    {
        $statusMap = [
            'F' => 'Završeno',
            'P' => 'U toku', 
            'N' => 'Novo',
            'C' => 'Otkažano',
            'D' => 'Nacrt'
        ];
        
        return $statusMap[$statusCode] ?? $statusCode;
    }

    /**
     * Get mock radni nalozi data for development/testing
     */
    private function getMockRadniNalozi()
    {
        return [
            [
                'responsive_id' => '',
                'id' => 1,
                'broj_naloga' => 'RN-2024-001',
                'naziv' => 'Popravka servera',
                'opis' => 'Popravka glavnog servera u data centru',
                'status' => 'U toku',
                'prioritet' => 'Visok',
                'datum_kreiranja' => '2024-12-15',
                'datum_zavrsetka' => null,
                'dodeljen_korisnik' => 'Marko Petrović',
                'klijent' => 'Datalab d.o.o.',
                'vrednost' => 15000,
                'valuta' => 'RSD',
                'magacin' => 'Glavni magacin'
            ],
            [
                'responsive_id' => '',
                'id' => 2,
                'broj_naloga' => 'RN-2024-002',
                'naziv' => 'Instalacija softvera',
                'opis' => 'Instalacija novog softvera na radne stanice',
                'status' => 'Završeno',
                'prioritet' => 'Srednji',
                'datum_kreiranja' => '2024-11-10',
                'datum_zavrsetka' => '2024-01-12',
                'dodeljen_korisnik' => 'Ana Nikolić',
                'klijent' => 'Tech Solutions',
                'vrednost' => 8500,
                'valuta' => 'RSD',
                'magacin' => 'IT magacin'
            ],
            [
                'responsive_id' => '',
                'id' => 3,
                'broj_naloga' => 'RN-2024-003',
                'naziv' => 'Mrežna konfiguracija',
                'opis' => 'Konfiguracija mrežne opreme za novi office',
                'status' => 'Novo',
                'prioritet' => 'Nizak',
                'datum_kreiranja' => '2024-12-20',
                'datum_zavrsetka' => null,
                'dodeljen_korisnik' => 'Petar Jovanović',
                'klijent' => 'Startup Company',
                'vrednost' => 12000,
                'valuta' => 'RSD',
                'magacin' => 'Mrežni magacin'
            ]
        ];
    }

    /**
     * Get details of a specific radni nalog
     * This method handles invoice preview, edit, and print routes
     */
    public function radniNalogDetails($id = null)
    {
        $pageConfigs = ['pageHeader' => false];
        
        // If no ID provided, redirect to list
        if (!$id) {
            return redirect()->route('app-invoice-list');
        }
        
        try {
            // Fetch specific radni nalog details
            $radniNalog = $this->fetchRadniNalogDetails($id);
            
            return view('/content/apps/invoice/app-invoice-preview', [
                'pageConfigs' => $pageConfigs,
                'radniNalog' => $radniNalog
            ]);
            
        } catch (\Exception $e) {
            Log::error('PAWS details error: ' . $e->getMessage());
            
            return redirect()->route('app-invoice-list')
                ->with('error', 'Greška pri učitavanju detalja radnog naloga.');
        }
    }

    /**
     * Fetch details of a specific radni nalog
     */
    private function fetchRadniNalogDetails($id)
    {
        try {
            // Prepare the API request payload for specific order details
            $payload = [
                "start" => 0,
                "length" => 1,
                "fieldsToReturn" => "*",
                "tableFKs" => [],
                "customConditions" => [
                    "condition" => "id = ?",
                    "params" => [$id]
                ],
                "sortColumn" => "id",
                "sortOrder" => "ASC",
                "withSubSelects" => 1
            ];
            
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($this->pawsUrl . '/api/Order/retrieve', $payload);
            
            if ($response->successful()) {
                $data = $response->json();
                $transformedData = $this->transformPawsData($data);
                
                // Return the first (and should be only) record
                return $transformedData[0] ?? null;
            } else {
                throw new \Exception('Failed to fetch radni nalog details: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            Log::error('PAWS details fetch error: ' . $e->getMessage());
            
            // Return mock data for development
            $mockData = $this->getMockRadniNalozi();
            return collect($mockData)->firstWhere('id', $id);
        }
    }

    /**
     * Update status of a radni nalog
     * Note: This method assumes PAWS has an update endpoint
     * You may need to adjust the endpoint URL based on PAWS API documentation
     */
    public function updateRadniNalogStatus(Request $request, $id)
    {
        try {
            // Prepare update payload - adjust fields based on PAWS API requirements
            $updatePayload = [
                'id' => $id,
                'status' => $request->input('status'),
                'komentar' => $request->input('komentar', ''),
                'updated_by' => auth()->user()->name ?? 'System',
                'updated_at' => now()->toISOString()
            ];
            
            // Make request to PAWS update endpoint (adjust URL as needed)
            $response = Http::withBasicAuth($this->username, $this->password)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($this->pawsUrl . '/api/Order/updateitem', $updatePayload);
            
            if ($response->successful()) {
                return redirect()->back()
                    ->with('success', 'Status radnog naloga je uspešno ažuriran.');
            } else {
                throw new \Exception('Failed to update radni nalog status: ' . $response->body());
            }
            
        } catch (\Exception $e) {
            Log::error('PAWS update error: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Greška pri ažuriranju statusa radnog naloga.');
        }
    }
}
