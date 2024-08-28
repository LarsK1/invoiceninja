<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway\Storecove;

use App\DataMapper\Analytics\LegalEntityCreated;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Client\RequestException;
use Turbo124\Beacon\Facades\LightLogs;

enum HttpVerb: string
{
    case POST = 'post';
    case PUT = 'put';
    case GET = 'get';
    case PATCH = 'patch';
    case DELETE = 'delete';
}

class Storecove
{    
    /** @var mixed $base_url */
    private string $base_url = 'https://api.storecove.com/api/v2/';
    
    /** @var mixed $peppol_discovery */
    private array $peppol_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "peppol",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "de:lwid",
        "identifier" => "DE:VAT"
    ];
    
    /** @var mixed $dbn_discovery */
    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "gln",
        "identifier" => "1200109963131"
    ];

    public StorecoveRouter $router;

    public function __construct()
    {
        $this->router = new StorecoveRouter();
    }
    
    /**
     * Discovery
     *
     * @param  string $identifier
     * @param  string $scheme
     * @param  string $network
     * @return bool
     */
    public function discovery(string $identifier, string $scheme, string $network = 'peppol'): bool
    {
        $network_data = [];

        match ($network) {
            'peppol' => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            'dbn' => $network_data = array_merge($this->dbn_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            default => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
        };

        $uri =  "api/v2/discovery/receives";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());

        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;

    }
    
    /**
     * Unused as yet
     *
     * @param  mixed $document
     * @return void
     */
    public function sendJsonDocument($document)
    {

        $payload = [
            // "legalEntityId" => 290868,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["david@invoiceninja.com"]
            ],
            "document" => [
                "documentType" => "invoice",
            "invoice" => $document,
            ],
        ];

        $uri = "document_submissions";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        if($r->successful()) {
            return $r->json()['guid'];
        }

        return false;

    }
    
    /**
     * Send Document via StoreCove
     *
     * @param  string $document
     * @param  int $routing_id
     * @param  array $override_payload
     * 
     * @return string|null
     */
    public function sendDocument(string $document, int $routing_id, array $override_payload = []): ?string
    {

        $payload = [
            "legalEntityId" => $routing_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["david@invoiceninja.com"]
            ],
            "document" => [

            ],
        ];

        $payload = array_merge($payload, $override_payload);

        $payload['document']['documentType'] = 'invoice';
        $payload['document']["rawDocumentData"] = [
                    "document" => base64_encode($document),
                    "parse" => true,
                    "parseStrategy" => "ubl",
        ];

        $uri = "document_submissions";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        nlog($r->body());
        // nlog($r->json());

        if($r->successful()) {
            return $r->json()['guid'];
        }

        return null;

    }

    /**
     * Get Sending Evidence
     *
     * @param  string $guid
     * @return mixed
     */
    public function getSendingEvidence(string $guid)
    {
        $uri = "document_submissions/{$guid}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, [], $this->getHeaders());

        if($r->successful())
            return $r->json();

        return $r;
    }

    /**
     * CreateLegalEntity
     *
     * Creates a base entity. 
     * 
     * Following creation, you will also need to create a Peppol Identifier
     * 
     * @url https://www.storecove.com/docs/#_openapi_legalentitycreate
     * 
     * @return mixed
     */
    public function createLegalEntity(array $data, ?Company $company = null)
    {
        $uri = 'legal_entities';

        if($company){

            $data = array_merge([            
                'city' => $company->settings->city,
                'country' => $company->country()->iso_3166_2,
                'county' => $company->settings->state,
                'line1' => $company->settings->address1,
                'line2' => $company->settings->address2,
                'party_name' => $company->settings->name,
                'tax_registered' => (bool)strlen($company->settings->vat_number ?? '') > 2,
                'tenant_id' => $company->company_key,
                'zip' => $company->settings->postal_code,
            ], $data);

        }

        $company_defaults = [
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice'],
        ];

        $payload = array_merge($company_defaults, $data);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }
    
    /**
     * GetLegalEntity
     *
     * @param  int $id
     * @return mixed
     */
    public function getLegalEntity($id)
    {

        // $uri = "legal_entities";

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, []);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }
    
    /**
     * UpdateLegalEntity
     *
     * @param  int $id
     * @param  array $data
     * @return void
     */
    public function updateLegalEntity(int $id, array $data)
    {

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::PATCH)->value, $data);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }
    
    /**
     * AddIdentifier
     * 
     * Add a Peppol identifier to the legal entity
     *
     * @param  int $legal_entity_id
     * @param  string $identifier
     * @param  string $scheme
     * @return mixed
     */
    public function addIdentifier(int $legal_entity_id, string $identifier, string $scheme)
    {
        $uri = "legal_entities/{$legal_entity_id}/peppol_identifiers";

        $data = [
            "identifier" => $identifier,
            "scheme" => $scheme,
            "superscheme" => "iso6523-actorid-upis",
        ];

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $data);

        if($r->successful()) {
            $data = $r->json();

            LightLogs::create(new LegalEntityCreated($data['id'], $legal_entity_id))->batch();

            return $data;
        }

        return $r;

    }
    
    /**
     * deleteIdentifier
     *
     * @param  int $legal_entity_id
     * @return bool
     */
    public function deleteIdentifier(int $legal_entity_id): bool
    {
        $uri = "/legal_entities/{$legal_entity_id}";

        $r = $this->httpClient($uri, (HttpVerb::DELETE)->value, []);

        return $r->successful();
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    private function getHeaders(array $headers = [])
    {

        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ], $headers);

    }

    private function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        try {
            $r = Http::withToken(config('ninja.storecove_api_key'))
                    ->withHeaders($this->getHeaders($headers))
                    ->{$verb}("{$this->base_url}{$uri}", $data)->throw();
        }
        catch (ClientException $e) {
            // 4xx errors
            nlog("Client error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (ServerException $e) {
            // 5xx errors
            nlog("Server error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (RequestException $e) {
            nlog("Request error: {$e->getCode()}: " . $e->getMessage());       
        }

        return $r;
    }

}
