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

namespace App\Http\Requests\Design;

use App\Http\Requests\Request;
use App\Models\Account;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateDesignRequest extends Request
{
    use ChecksEntityStatus;

    private array $valid_entities = [
        'invoice',
        'payment',
        'client',
        'quote',
        'credit',
        'purchase_order',
        'project',
        'task'
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() && $user->account->hasFeature(Account::FEATURE_API);
    }

    public function rules()
    {
        return [
            'is_template' => 'sometimes|boolean',
            'entities' => 'sometimes|string|nullable'
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('product', $input['design']) || is_null($input['design']['product'])) {
            $input['design']['product'] = '';
        }

        if (! array_key_exists('task', $input['design']) || is_null($input['design']['task'])) {
            $input['design']['task'] = '';
        }

        if (! array_key_exists('includes', $input['design']) || is_null($input['design']['includes'])) {
            $input['design']['includes'] = '';
        }

        if (! array_key_exists('footer', $input['design']) || is_null($input['design']['footer'])) {
            $input['design']['footer'] = '';
        }

        if (! array_key_exists('header', $input['design']) || is_null($input['design']['header'])) {
            $input['design']['header'] = '';
        }

        if (! array_key_exists('body', $input['design']) || is_null($input['design']['body'])) {
            $input['design']['body'] = '';
        }

        if(array_key_exists('entities', $input)) {
            $user_entities = explode(",", $input['entities']);

            $e = [];

            foreach ($user_entities as $entity) {
                if (in_array($entity, $this->valid_entities)) {
                    $e[] = $entity;
                }
            }

            $input['entities'] = implode(",", $e);
        }


        $this->replace($input);
    }
}
