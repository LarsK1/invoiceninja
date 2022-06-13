<div>
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <span class="hidden mr-2 text-sm md:block">{{ ctrans('texts.per_page') }}</span>
            <select wire:model="per_page" class="py-1 text-sm form-select">
                <option>5</option>
                <option selected>10</option>
                <option>15</option>
                <option>20</option>
            </select>
        </div>
        <div class="flex items-center">
            <div class="mr-3">
                <input wire:model="status" value="paid" type="checkbox" class="cursor-pointer form-checkbox" id="paid-checkbox">
                <label for="paid-checkbox" class="text-sm cursor-pointer">{{ ctrans('texts.status_paid') }}</label>
            </div>
            <div class="mr-3">
                <input wire:model="status" value="unpaid" type="checkbox" class="cursor-pointer form-checkbox" id="unpaid-checkbox">
                <label for="unpaid-checkbox" class="text-sm cursor-pointer">{{ ctrans('texts.status_unpaid') }}</label>
            </div>
            <div class="mr-3">
                <input wire:model="status" value="overdue" type="checkbox" class="cursor-pointer form-checkbox" id="overdue-checkbox">
                <label for="overdue-checkbox" class="text-sm cursor-pointer">{{ ctrans('texts.past_due') }}</label>
            </div>
        </div>
    </div>
    <div class="py-2 -my-2 overflow-x-auto sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
        <div class="inline-block min-w-full overflow-hidden align-middle rounded">
            <table class="min-w-full mt-4 border border-gray-200 rounded shadow invoices-table">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <label>
                                <input type="checkbox" class="form-check form-check-parent">
                            </label>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('number')" class="cursor-pointer">
                                {{ ctrans('texts.purchase_order_number_short') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('date')" class="cursor-pointer">
                                {{ ctrans('texts.purchase_order_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('amount')" class="cursor-pointer">
                                {{ ctrans('texts.amount') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('balance')" class="cursor-pointer">
                                {{ ctrans('texts.balance') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('due_date')" class="cursor-pointer">
                                {{ ctrans('texts.due_date') }}
                            </span>
                        </th>
                        <th class="px-6 py-3 text-xs font-medium leading-4 tracking-wider text-left text-white uppercase border-b border-gray-200 bg-primary">
                            <span role="button" wire:click="sortBy('status_id')" class="cursor-pointer">
                                {{ ctrans('texts.status') }}
                            </span>
                        </th>
                        <th class="px-white-3 border-b border-gray-200 bg-primary"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchase_orders as $purchase_order)
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 text-sm font-medium leading-5 text-gray-900 whitespace-nowrap">
                                <label>
                                    <input type="checkbox" class="form-check form-check-child" data-value="{{ $purchase_order->hashed_id }}">
                                </label>
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                                {{ $purchase_order->number }}
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                                {{ $purchase_order->translateDate($purchase_order->date, $invoice->company->date_format(), $invoice->company->locale()) }}
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                                {{ App\Utils\Number::formatMoney($purchase_order->amount, $purchase_order->company) }}
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                            {{ App\Utils\Number::formatMoney($purchase_order->balance, $invoice->company) }}
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                                {{ $purchase_order->translateDate($purchase_order->due_date, $invoice->company->date_format(), $purchase_order->company->locale()) }}
                            </td>
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap">
                                {!! App\Models\PurchaseOrder::badgeForStatus($purchase_order->status) !!}
                            </td>
                            <td class="flex items-center justify-end px-6 py-4 text-sm font-medium leading-5 whitespace-nowrap">
                                <a href="{{ route('vendor.purchase_order.show', $purchase_order->hashed_id) }}" class="button-link text-primary">
                                    {{ ctrans('texts.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="bg-white group hover:bg-gray-100">
                            <td class="px-6 py-4 text-sm leading-5 text-gray-500 whitespace-nowrap" colspan="100%">
                                {{ ctrans('texts.no_results') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="flex justify-center mt-6 mb-6 md:justify-between">
        @if($invoices && $invoices->total() > 0)
            <span class="hidden text-sm text-gray-700 md:block mr-2">
                {{ ctrans('texts.showing_x_of', ['first' => $purchase_orders->firstItem(), 'last' => $purchase_orders->lastItem(), 'total' => $purchase_orders->total()]) }}
            </span>
        @endif
        {{ $purchase_orders->links('portal/ninja2020/vendor/pagination') }}
    </div>
</div>

@push('footer')
    <script src="{{ asset('js/clients/invoices/action-selectors.js') }}"></script>
@endpush
