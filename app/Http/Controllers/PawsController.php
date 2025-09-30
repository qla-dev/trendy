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
            
        return view('/content/apps/invoice/app-invoice-list', [
            'pageConfigs' => $pageConfigs,
            'radniNalozi' => $radniNalozi
        ]);
            
        } catch (\Exception $e) {
            Log::error('PAWS API Error: ' . $e->getMessage());
            
            return view('/content/apps/invoice/app-invoice-list', [
                'pageConfigs' => $pageConfigs,
                'radniNalozi' => [],
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
                "length" => 50, // Limit to 50 records for initial load
                "fieldsToReturn" => "*", // Get all fields
                "tableFKs" => [],
                "customConditions" => [
                    "condition" => "1=1", // Get all records, can be customized
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
                'id' => $order['acKey'] ?? $order['id'] ?? $order['order_id'] ?? null,
                'broj_naloga' => $order['acDocType'] ?? $order['order_number'] ?? $order['broj_naloga'] ?? 'N/A',
                'naziv' => $order['acTitle'] ?? $order['title'] ?? $order['naziv'] ?? $order['description'] ?? 'N/A',
                'opis' => $order['acDescription'] ?? $order['description'] ?? $order['opis'] ?? $order['details'] ?? 'N/A',
                'status' => $order['acStatus'] ?? $order['status'] ?? $order['order_status'] ?? 'N/A',
                'prioritet' => $order['acPriority'] ?? $order['priority'] ?? $order['prioritet'] ?? 'Srednji',
                'datum_kreiranja' => $order['acCreatedDate'] ?? $order['created_date'] ?? $order['datum_kreiranja'] ?? date('Y-m-d'),
                'datum_zavrsetka' => $order['acCompletedDate'] ?? $order['completed_date'] ?? $order['datum_zavrsetka'] ?? null,
                'dodeljen_korisnik' => $order['acAssignedTo'] ?? $order['assigned_to'] ?? $order['dodeljen_korisnik'] ?? 'N/A',
                'klijent' => $order['acClient'] ?? $order['client'] ?? $order['klijent'] ?? $order['customer'] ?? 'N/A'
            ];
        }
        
        return $transformedData;
    }

    /**
     * Get mock radni nalozi data for development/testing
     */
    private function getMockRadniNalozi()
    {
        return [
            [
                'id' => 1,
                'broj_naloga' => 'RN-2024-001',
                'naziv' => 'Popravka servera',
                'opis' => 'Popravka glavnog servera u data centru',
                'status' => 'U toku',
                'prioritet' => 'Visok',
                'datum_kreiranja' => '2024-01-15',
                'datum_zavrsetka' => null,
                'dodeljen_korisnik' => 'Marko Petrović',
                'klijent' => 'Datalab d.o.o.'
            ],
            [
                'id' => 2,
                'broj_naloga' => 'RN-2024-002',
                'naziv' => 'Instalacija softvera',
                'opis' => 'Instalacija novog softvera na radne stanice',
                'status' => 'Završeno',
                'prioritet' => 'Srednji',
                'datum_kreiranja' => '2024-01-10',
                'datum_zavrsetka' => '2024-01-12',
                'dodeljen_korisnik' => 'Ana Nikolić',
                'klijent' => 'Tech Solutions'
            ],
            [
                'id' => 3,
                'broj_naloga' => 'RN-2024-003',
                'naziv' => 'Mrežna konfiguracija',
                'opis' => 'Konfiguracija mrežne opreme za novi office',
                'status' => 'Novo',
                'prioritet' => 'Nizak',
                'datum_kreiranja' => '2024-01-20',
                'datum_zavrsetka' => null,
                'dodeljen_korisnik' => 'Petar Jovanović',
                'klijent' => 'Startup Company'
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
