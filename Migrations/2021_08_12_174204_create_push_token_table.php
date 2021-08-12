<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreatePushTokenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Capsule::schema()->create('core_push_notificator_tokens', function (Blueprint $table) {
            $table->increments('Id');

            $table->integer('IdUser')->default(0);
            $table->integer('IdAccount')->default(0);
            $table->string('Email')->default('');
            $table->string('Uid')->default('');
            $table->string('Token')->default('');

            $table->timestamp(\Aurora\System\Classes\Model::CREATED_AT)->nullable();
            $table->timestamp(\Aurora\System\Classes\Model::UPDATED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Capsule::schema()->dropIfExists('core_push_notificator_tokens');
    }
}
