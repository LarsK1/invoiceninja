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

namespace App\Http\Requests\User;

use App\Http\Requests\Request;
use App\Http\ValidationRules\UniqueUserRule;
use App\Http\ValidationRules\User\HasValidPhoneNumber;

class UpdateUserRequest extends Request
{

    private bool $phone_has_changed = false;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->id == $this->user->id || auth()->user()->isAdmin();
    }

    public function rules()
    {
        $input = $this->all();

        $rules = [
            'password' => 'nullable|string|min:6',
        ];

        if (isset($input['email'])) {
            $rules['email'] = ['email', 'sometimes', new UniqueUserRule($this->user, $input['email'])];
        }

        if(Ninja::isHosted() && $this->phone_has_changed)
            $rules['phone'] = ['sometimes', new HasValidPhoneNumber()];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('email', $input)) {
            $input['email'] = trim($input['email']);
        }

        if (array_key_exists('first_name', $input)) {
            $input['first_name'] = strip_tags($input['first_name']);
        }

        if (array_key_exists('last_name', $input)) {
            $input['last_name'] = strip_tags($input['last_name']);
        }

        if(strlen($input['phone']) > 1 && ($this->user->phone != $input['phone']))
            $this->phone_has_changed = true;

        $this->replace($input);
    }

}
