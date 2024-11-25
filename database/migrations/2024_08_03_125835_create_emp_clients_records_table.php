<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmpClientsRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('emp_clients_records', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uni_empclient_id',50)->nullable();
            $table->string('emp_id',10);
            $table->string('client_id',20);
            $table->string('support_pin',10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('emp_clients_records');
    }
}
