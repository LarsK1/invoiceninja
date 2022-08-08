<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\BankIntegrationFactory;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Http\Requests\BankIntegration\AdminBankIntegrationRequest;
use App\Http\Requests\BankIntegration\CreateBankIntegrationRequest;
use App\Http\Requests\BankIntegration\DestroyBankIntegrationRequest;
use App\Http\Requests\BankIntegration\EditBankIntegrationRequest;
use App\Http\Requests\BankIntegration\ShowBankIntegrationRequest;
use App\Http\Requests\BankIntegration\StoreBankIntegrationRequest;
use App\Http\Requests\BankIntegration\UpdateBankIntegrationRequest;
use App\Models\BankIntegration;
use App\Repositories\BankIntegrationRepository;
use App\Transformers\BankIntegrationTransformer;
use Illuminate\Http\Request;


class BankIntegrationController extends BaseController
{

    protected $entity_type = BankIntegration::class;

    protected $entity_transformer = BankIntegrationTransformer::class;

    protected $bank_integration_repo;

    public function __construct(BankIntegrationRepository $bank_integration_repo)
    {
        parent::__construct();

        $this->bank_integration_repo = $bank_integration_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/bank_integrations",
     *      operationId="getBankIntegrations",
     *      tags={"bank_integrations"},
     *      summary="Gets a list of bank_integrations",
     *      description="Lists all bank integrations",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Parameter(
     *          name="rows",
     *          in="query",
     *          description="The number of bank integrations to return",
     *          example="50",
     *          required=false,
     *          @OA\Schema(
     *              type="number",
     *              format="integer",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="A list of bank integrations",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Request $request
     * @return Response|mixed
     */
    public function index(Request $request)
    {

        $bank_integrations = BankIntegration::query()->company();

        return $this->listResponse($bank_integrations);

    }

    /**
     * Display the specified resource.
     *
     * @param ShowBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="showBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Shows a bank_integration",
     *      description="Displays a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param EditBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/{id}/edit",
     *      operationId="editBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Shows a bank_integration for editing",
     *      description="Displays a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="updateBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Updates a bank_integration",
     *      description="Handles the updating of a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateBankIntegrationRequest $request, BankIntegration $bank_integration)
    {

        //stubs for updating the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), $bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBankIntegrationRequest $request
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/bank_integrations/create",
     *      operationId="getBankIntegrationsCreate",
     *      tags={"bank_integrations"},
     *      summary="Gets a new blank bank_integration object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateBankIntegrationRequest $request)
    {
        $bank_integration = BankIntegrationFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id);

        return $this->itemResponse($bank_integration);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreBankIntegrationRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations",
     *      operationId="storeBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Adds a bank_integration",
     *      description="Adds an bank_integration to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreBankIntegrationRequest $request)
    {
        //stub to store the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), BankIntegrationFactory::create(auth()->user()->company()->id, auth()->user()->id, auth()->user()->account_id));

        return $this->itemResponse($bank_integration);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/bank_integrations/{id}",
     *      operationId="deleteBankIntegration",
     *      tags={"bank_integrations"},
     *      summary="Deletes a bank_integration",
     *      description="Handles the deletion of a bank_integration by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The BankIntegration Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        $this->bank_integration_repo->delete($bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }

    /**
     * Return the remote list of accounts stored on the third part provider.
     *
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/bank_integrations/remote_accounts",
     *      operationId="getRemoteAccounts",
     *      tags={"bank_integrations"},
     *      summary="Gets the list of accounts from the remote server",
     *      description="Adds an bank_integration to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved bank_integration object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/BankIntegration"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function remoteAccounts(AdminBankIntegrationRequest $request)
    {
        // As yodlee is the first integration we don't need to perform switches yet, however
        // if we add additional providers we can reuse this class

        $bank_account_id = auth()->user()->account->bank_integration_account_id;

        if(!$bank_account_id)
            return response()->json(['message' => 'Not yet authenticated with Bank Integration service'], 400);

        $yodlee = new Yodlee($bank_account_id);

        $accounts = $yodlee->getAccounts(); 

        return response()->json($accounts, 200);
    }

    public function getTransactions(AdminBankIntegrationRequest $request)
    {
        //handle API failures we have only accounts for success

        $bank_account_id = auth()->user()->account->bank_integration_account_id;

        if(!$bank_account_id)
            return response()->json(['message' => 'Not yet authenticated with Bank Integration service'], 400);

        $yodlee = new Yodlee($bank_account_id);

        $data = [
            'CONTAINER' => 'bank',
            'categoryType' => 'INCOME, UNCATEGORIZE',
            'top' => 500,
            'fromDate' => '2000-10-10', /// YYYY-MM-DD
        ];

        $transactions = $yodlee->getTransactions($data); 

        return response()->json($transactions, 200)

    }
}