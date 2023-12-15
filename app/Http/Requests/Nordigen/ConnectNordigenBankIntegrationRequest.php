<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Nordigen;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\User;
use Cache;
use Log;

class ConnectNordigenBankIntegrationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'lang' => 'string',
            'institution_id' => 'string',
            'redirect' => 'string', // TODO: @turbo124 @todo validate, that this is a url without / at the end
        ];
    }

    // @turbo124 @todo please check for validity, when request from frontend
    public function prepareForValidation()
    {
        $input = $this->all();

        if (!array_key_exists('redirect', $input)) {
            $context = $this->getTokenContent();

            $input["redirect"] = isset($context['is_react']) && $context['is_react'] ? config('ninja.react_url') : config('ninja.app_url');

            $this->replace($input);

        }
    }
    public function getTokenContent()
    {
        if ($this->state) {
            $this->token = $this->state;
        }

        $data = Cache::get($this->token);

        return $data;
    }

    public function getContact()
    {
        MultiDB::findAndSetDbByCompanyKey($this->getTokenContent()['company_key']);

        return User::findOrFail($this->getTokenContent()['user_id']);

    }

    public function getCompany()
    {

        MultiDB::findAndSetDbByCompanyKey($this->getTokenContent()['company_key']);

        return Company::where('company_key', $this->getTokenContent()['company_key'])->firstOrFail();

    }
}
