<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use jdavidbakr\MailTracker\Model\SentEmail;
use Illuminate\Database\Migrations\Migration;

class AddSenderEmailAndNameToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection((new SentEmail())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('headers');
            $table->string('sender_email')->nullable()->after('sender_name');
            $table->string('recipient_name')->nullable()->after('sender_email');
            $table->string('recipient_email')->nullable()->after('recipient_email');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection((new SentEmail())->getConnectionName())->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('sender_name');
            $table->dropColumn('sender_email');
            $table->dropColumn('recipient_name');
            $table->dropColumn('recipient_email');
        });
    }
}
