<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrderInvitationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('vendor_contact_id');
            $table->unsignedBigInteger('purchase_order_id')->index();
            $table->string('key')->index();
            $table->string('transaction_reference')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->mediumText('email_error')->nullable();
            $table->text('signature_base64')->nullable();
            $table->datetime('signature_date')->nullable();

            $table->datetime('sent_date')->nullable();
            $table->datetime('viewed_date')->nullable();
            $table->datetime('opened_date')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('vendor_contact_id')->references('id')->on('vendor_contacts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);
            $table->unique(['vendor_contact_id', 'purchase_order_id'], 'vendor_purchase_unique');
            $table->index(['deleted_at', 'purchase_order_id', 'company_id'], 'vendor_purchase_company_index');

        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedInteger('client_id')->nullable()->change();
        });


        Company::cursor()->each(function ($company){

            $settings = $company->settings;

            $settings->purchase_order_design_id = 'Wpmbk5ezJn';
            $settings->purchase_order_footer = ''; //@implemented
            $settings->purchase_order_terms = ''; //@implemented
            $settings->purchase_order_public_notes = ''; //@implemented
            $settings->purchase_order_number_pattern = ''; //@implemented
            $settings->purchase_order_number_counter = 1; //@implemented

            $company->settings = $settings;
            $company->save();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
