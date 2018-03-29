<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOctopartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('octoparts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('part_number')->unique();
            $table->string('manufacturer');
            $table->string('description');
            $table->integer('stock')->default(0);
            // Just to check if part has price, calculate price is dificult
            $table->boolean('price')->default(0);
            $table->integer('created_by')->default(1);
            $table->integer('updated_by');
            $table->boolean('is_complete')->default(false);
            $table->string('missing_attributes');
            $table->integer('origin');
            $table->boolean('enabled')->default(true);
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
        Schema::drop('octoparts');
    }
}
